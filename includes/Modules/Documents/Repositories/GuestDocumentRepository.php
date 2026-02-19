<?php

namespace Nozule\Modules\Documents\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Documents\Models\GuestDocument;

/**
 * Repository for guest document database operations.
 */
class GuestDocumentRepository extends BaseRepository {

	protected string $table = 'guest_documents';
	protected string $model = GuestDocument::class;

	/**
	 * Get all documents for a specific guest.
	 *
	 * @param int $guestId Guest ID.
	 * @return GuestDocument[]
	 */
	public function getByGuest( int $guestId ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE guest_id = %d ORDER BY created_at DESC",
			$guestId
		);

		return GuestDocument::fromRows( $rows );
	}

	/**
	 * Find a document by its document number.
	 *
	 * @param string $number Document number (e.g. passport number).
	 */
	public function findByDocumentNumber( string $number ): ?GuestDocument {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE document_number = %s LIMIT 1",
			$number
		);

		return $row ? GuestDocument::fromRow( $row ) : null;
	}

	/**
	 * Get documents expiring within the given number of days.
	 *
	 * Only returns documents that have not yet expired.
	 *
	 * @param int $days Number of days to look ahead.
	 * @return GuestDocument[]
	 */
	public function getExpiringSoon( int $days = 30 ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE expiry_date IS NOT NULL
			   AND expiry_date >= CURDATE()
			   AND expiry_date <= DATE_ADD( CURDATE(), INTERVAL %d DAY )
			 ORDER BY expiry_date ASC",
			$days
		);

		return GuestDocument::fromRows( $rows );
	}

	/**
	 * List documents with pagination and optional filters.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing documents.
	 *
	 *     @type int    $guest_id      Filter by guest ID.
	 *     @type string $document_type Filter by document type.
	 *     @type int    $verified      Filter by verification status (0 or 1).
	 *     @type string $orderby       Column to order by. Default 'created_at'.
	 *     @type string $order         Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page      Results per page. Default 20.
	 *     @type int    $page          Page number (1-based). Default 1.
	 * }
	 * @return array{ items: GuestDocument[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'guest_id'      => 0,
			'document_type' => '',
			'verified'      => null,
			'orderby'       => 'created_at',
			'order'         => 'DESC',
			'per_page'      => 20,
			'page'          => 1,
		];

		$args       = wp_parse_args( $args, $defaults );
		$table      = $this->tableName();
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];

		// Build WHERE conditions.
		if ( ! empty( $args['guest_id'] ) ) {
			$conditions[] = 'guest_id = %d';
			$params[]     = (int) $args['guest_id'];
		}

		if ( ! empty( $args['document_type'] ) ) {
			$conditions[] = 'document_type = %s';
			$params[]     = $args['document_type'];
		}

		if ( $args['verified'] !== null && $args['verified'] !== '' ) {
			$conditions[] = 'verified = %d';
			$params[]     = (int) $args['verified'];
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		// Sanitize ordering.
		$allowed_columns = [
			'id', 'guest_id', 'document_type', 'document_number',
			'expiry_date', 'verified', 'created_at', 'updated_at',
		];
		$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where}",
			...$count_params
		);

		// Fetch results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'items' => GuestDocument::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	/**
	 * Create a new document record.
	 *
	 * @return GuestDocument|false
	 */
	public function create( array $data ): GuestDocument|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		// Encode ocr_data as JSON if provided as an array.
		if ( isset( $data['ocr_data'] ) && is_array( $data['ocr_data'] ) ) {
			$data['ocr_data'] = wp_json_encode( $data['ocr_data'] );
		}

		// Set defaults.
		$data['document_type'] = $data['document_type'] ?? GuestDocument::TYPE_PASSPORT;
		$data['ocr_status']    = $data['ocr_status'] ?? GuestDocument::OCR_NONE;
		$data['verified']      = $data['verified'] ?? 0;

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update an existing document record.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		// Encode ocr_data as JSON if provided as an array.
		if ( isset( $data['ocr_data'] ) && is_array( $data['ocr_data'] ) ) {
			$data['ocr_data'] = wp_json_encode( $data['ocr_data'] );
		}

		return parent::update( $id, $data );
	}
}
