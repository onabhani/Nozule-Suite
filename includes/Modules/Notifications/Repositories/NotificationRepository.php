<?php

namespace Venezia\Modules\Notifications\Repositories;

use Venezia\Core\BaseRepository;
use Venezia\Core\Database;
use Venezia\Modules\Notifications\Models\Notification;

/**
 * Repository for notification database operations.
 */
class NotificationRepository extends BaseRepository {

	protected string $table = 'notifications';
	protected string $model = Notification::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	/**
	 * Find a notification by ID.
	 */
	public function find( int $id ): ?Notification {
		$result = parent::find( $id );

		return $result instanceof Notification ? $result : null;
	}

	/**
	 * Get all notifications for a specific booking.
	 *
	 * @return Notification[]
	 */
	public function getByBookingId( int $booking_id ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$booking_id
		);

		return Notification::fromRows( $rows );
	}

	/**
	 * Get all notifications for a specific guest.
	 *
	 * @return Notification[]
	 */
	public function getByGuestId( int $guest_id ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guest_id
		);

		return Notification::fromRows( $rows );
	}

	/**
	 * Get queued notifications ready for processing.
	 *
	 * @param int $limit Maximum number of notifications to retrieve.
	 * @return Notification[]
	 */
	public function getQueued( int $limit = 50 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			WHERE status = 'queued'
			AND attempts < %d
			ORDER BY created_at ASC
			LIMIT %d",
			Notification::MAX_ATTEMPTS,
			$limit
		);

		return Notification::fromRows( $rows );
	}

	/**
	 * Create a new notification record.
	 *
	 * @return Notification|false
	 */
	public function create( array $data ): Notification|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['status']     = $data['status'] ?? 'queued';
		$data['attempts']   = $data['attempts'] ?? 0;

		// Encode template_vars as JSON if provided as an array.
		if ( isset( $data['template_vars'] ) && is_array( $data['template_vars'] ) ) {
			$data['template_vars'] = wp_json_encode( $data['template_vars'] );
		}

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a notification record.
	 */
	public function update( int $id, array $data ): bool {
		// Encode template_vars as JSON if provided as an array.
		if ( isset( $data['template_vars'] ) && is_array( $data['template_vars'] ) ) {
			$data['template_vars'] = wp_json_encode( $data['template_vars'] );
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Update the status of a notification.
	 *
	 * @param int    $id     Notification ID.
	 * @param string $status New status value.
	 */
	public function updateStatus( int $id, string $status ): bool {
		return $this->update( $id, [ 'status' => $status ] );
	}

	/**
	 * Get notifications filtered by status.
	 *
	 * @param string $status   Notification status to filter by.
	 * @param int    $per_page Number of results per page.
	 * @param int    $page     Page number (1-based).
	 * @return array{ notifications: Notification[], total: int, pages: int }
	 */
	public function getByStatus( string $status, int $per_page = 20, int $page = 1 ): array {
		$table  = $this->tableName();
		$offset = ( $page - 1 ) * $per_page;

		$total = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE status = %s",
			$status
		);

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$status,
			$per_page,
			$offset
		);

		return [
			'notifications' => Notification::fromRows( $rows ),
			'total'         => $total,
			'pages'         => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	/**
	 * Get the most recent notifications of a specific type.
	 *
	 * @param string $type  Notification type to filter by.
	 * @param int    $limit Maximum number of results.
	 * @return Notification[]
	 */
	public function getRecentByType( string $type, int $limit = 10 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE type = %s ORDER BY created_at DESC LIMIT %d",
			$type,
			$limit
		);

		return Notification::fromRows( $rows );
	}

	/**
	 * Get the most recent notifications of a specific channel.
	 *
	 * @param string $channel Notification channel to filter by.
	 * @param int    $limit   Maximum number of results.
	 * @return Notification[]
	 */
	public function getRecentByChannel( string $channel, int $limit = 10 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE channel = %s ORDER BY created_at DESC LIMIT %d",
			$channel,
			$limit
		);

		return Notification::fromRows( $rows );
	}

	/**
	 * Mark a notification as sent.
	 *
	 * @param int         $id          Notification ID.
	 * @param string|null $external_id External service reference ID.
	 */
	public function markAsSent( int $id, ?string $external_id = null ): bool {
		$data = [
			'status'  => 'sent',
			'sent_at' => current_time( 'mysql' ),
		];

		if ( $external_id !== null ) {
			$data['external_id'] = $external_id;
		}

		return $this->update( $id, $data );
	}

	/**
	 * Mark a notification as delivered.
	 *
	 * @param int $id Notification ID.
	 */
	public function markAsDelivered( int $id ): bool {
		return $this->update( $id, [
			'status'       => 'delivered',
			'delivered_at' => current_time( 'mysql' ),
		] );
	}

	/**
	 * Mark a notification as failed.
	 *
	 * @param int    $id            Notification ID.
	 * @param string $error_message Description of what went wrong.
	 */
	public function markAsFailed( int $id, string $error_message = '' ): bool {
		$table = $this->tableName();

		// Atomically increment attempts and set failure status.
		$result = $this->db->query(
			"UPDATE {$table}
			SET status = 'failed',
				attempts = attempts + 1,
				error_message = %s
			WHERE id = %d",
			$error_message,
			$id
		);

		return $result !== false;
	}

	/**
	 * Increment the attempt counter and reset status to queued for retry.
	 *
	 * @param int    $id            Notification ID.
	 * @param string $error_message Description of the last attempt failure.
	 */
	public function incrementAttemptAndRequeue( int $id, string $error_message = '' ): bool {
		$table = $this->tableName();

		$result = $this->db->query(
			"UPDATE {$table}
			SET status = CASE WHEN attempts + 1 >= %d THEN 'failed' ELSE 'queued' END,
				attempts = attempts + 1,
				error_message = %s
			WHERE id = %d",
			Notification::MAX_ATTEMPTS,
			$error_message,
			$id
		);

		return $result !== false;
	}

	/**
	 * Check whether a notification of a specific type has already been sent for a booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Notification type.
	 * @param string $channel    Notification channel.
	 */
	public function hasBeenSent( int $booking_id, string $type, string $channel = 'email' ): bool {
		$table = $this->tableName();
		$count = $this->db->getVar(
			"SELECT COUNT(*) FROM {$table}
			WHERE booking_id = %d
			AND type = %s
			AND channel = %s
			AND status IN ('sent', 'delivered', 'queued', 'sending')",
			$booking_id,
			$type,
			$channel
		);

		return (int) $count > 0;
	}

	/**
	 * Cancel all queued notifications for a specific booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int Number of notifications cancelled.
	 */
	public function cancelForBooking( int $booking_id ): int {
		$table = $this->tableName();

		$result = $this->db->query(
			"UPDATE {$table}
			SET status = 'cancelled'
			WHERE booking_id = %d
			AND status = 'queued'",
			$booking_id
		);

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Delete old notification records.
	 *
	 * @param int $days Number of days to retain.
	 * @return int Number of records deleted.
	 */
	public function deleteOlderThan( int $days = 180 ): int {
		$table = $this->tableName();

		$result = $this->db->query(
			"DELETE FROM {$table}
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
			AND status IN ('sent', 'delivered', 'failed', 'cancelled')",
			$days
		);

		return is_int( $result ) ? $result : 0;
	}

	/**
	 * Get notification statistics grouped by status.
	 *
	 * @return array<string, int>
	 */
	public function getStats(): array {
		$table   = $this->tableName();
		$results = $this->db->getResults(
			"SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status"
		);

		$stats = [];
		foreach ( $results as $row ) {
			$stats[ $row->status ] = (int) $row->total;
		}

		return $stats;
	}

	/**
	 * List notifications with pagination, optional filtering, and sorting.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing notifications.
	 *
	 *     @type string $type       Filter by notification type.
	 *     @type string $channel    Filter by notification channel.
	 *     @type string $status     Filter by status.
	 *     @type int    $booking_id Filter by booking ID.
	 *     @type int    $guest_id   Filter by guest ID.
	 *     @type string $orderby    Column to order by. Default 'created_at'.
	 *     @type string $order      Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page   Results per page. Default 20.
	 *     @type int    $page       Page number (1-based). Default 1.
	 * }
	 * @return array{ notifications: Notification[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'type'       => '',
			'channel'    => '',
			'status'     => '',
			'booking_id' => 0,
			'guest_id'   => 0,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
			'per_page'   => 20,
			'page'       => 1,
		];

		$args   = wp_parse_args( $args, $defaults );
		$table  = $this->tableName();
		$offset = ( $args['page'] - 1 ) * $args['per_page'];
		$where  = [];
		$params = [];

		// Build filter clauses.
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}

		if ( ! empty( $args['channel'] ) ) {
			$where[]  = 'channel = %s';
			$params[] = $args['channel'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['booking_id'] ) ) {
			$where[]  = 'booking_id = %d';
			$params[] = (int) $args['booking_id'];
		}

		if ( ! empty( $args['guest_id'] ) ) {
			$where[]  = 'guest_id = %d';
			$params[] = (int) $args['guest_id'];
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Sanitize ordering.
		$allowed_columns = [
			'id', 'type', 'channel', 'status', 'attempts', 'sent_at', 'created_at',
		];
		$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where_clause}",
			...$count_params
		);

		// Fetch results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'notifications' => Notification::fromRows( $rows ),
			'total'         => $total,
			'pages'         => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}
}
