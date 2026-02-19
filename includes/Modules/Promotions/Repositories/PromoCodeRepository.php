<?php

namespace Nozule\Modules\Promotions\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Promotions\Models\PromoCode;

/**
 * Repository for promo code database operations.
 */
class PromoCodeRepository extends BaseRepository {

	protected string $table = 'promo_codes';
	protected string $model = PromoCode::class;

	/**
	 * Find a promo code by its unique code string.
	 *
	 * @param string $code The promo code string.
	 */
	public function findByCode( string $code ): ?PromoCode {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE code = %s LIMIT 1",
			$code
		);

		return $row ? PromoCode::fromRow( $row ) : null;
	}

	/**
	 * Get all active promo codes that are within their valid date range.
	 *
	 * @return PromoCode[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$today = current_time( 'Y-m-d' );

		$rows = $this->db->getResults(
			"SELECT * FROM {$table}
			 WHERE is_active = 1
			   AND ( valid_from IS NULL OR valid_from <= %s )
			   AND ( valid_to IS NULL OR valid_to >= %s )
			 ORDER BY created_at DESC",
			$today,
			$today
		);

		return PromoCode::fromRows( $rows );
	}

	/**
	 * List promo codes with pagination and optional filters.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing promo codes.
	 *
	 *     @type string $status    Filter by status: 'active', 'inactive', 'expired', or '' for all.
	 *     @type string $search    Search query matching code, name, or name_ar.
	 *     @type string $date_from Filter codes valid from this date onward.
	 *     @type string $date_to   Filter codes valid up to this date.
	 *     @type string $orderby   Column to order by. Default 'created_at'.
	 *     @type string $order     Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page  Results per page. Default 20.
	 *     @type int    $page      Page number (1-based). Default 1.
	 * }
	 * @return array{ items: PromoCode[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'status'   => '',
			'search'   => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'page'     => 1,
		];

		$args       = wp_parse_args( $args, $defaults );
		$table      = $this->tableName();
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];
		$today      = current_time( 'Y-m-d' );

		// Status filter.
		if ( $args['status'] === 'active' ) {
			$conditions[] = 'is_active = 1';
			$conditions[] = '( valid_from IS NULL OR valid_from <= %s )';
			$params[]     = $today;
			$conditions[] = '( valid_to IS NULL OR valid_to >= %s )';
			$params[]     = $today;
		} elseif ( $args['status'] === 'inactive' ) {
			$conditions[] = 'is_active = 0';
		} elseif ( $args['status'] === 'expired' ) {
			$conditions[] = 'valid_to IS NOT NULL AND valid_to < %s';
			$params[]     = $today;
		}

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
			$conditions[] = '( code LIKE %s OR name LIKE %s OR name_ar LIKE %s )';
			$params[]     = $like;
			$params[]     = $like;
			$params[]     = $like;
		}

		// Date range filter.
		if ( ! empty( $args['date_from'] ) ) {
			$conditions[] = '( valid_from IS NULL OR valid_from >= %s )';
			$params[]     = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$conditions[] = '( valid_to IS NULL OR valid_to <= %s )';
			$params[]     = $args['date_to'];
		}

		// Build WHERE clause.
		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		// Sanitize ordering.
		$allowed_columns = [
			'id', 'code', 'name', 'discount_type', 'discount_value',
			'max_uses', 'used_count', 'valid_from', 'valid_to',
			'is_active', 'created_at',
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
			'items' => PromoCode::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}

	/**
	 * Increment the used_count for a promo code.
	 *
	 * @param int $id The promo code ID.
	 */
	public function incrementUsedCount( int $id ): bool {
		$table = $this->tableName();

		$result = $this->db->query(
			"UPDATE {$table}
			 SET used_count = used_count + 1,
			     updated_at = %s
			 WHERE id = %d",
			current_time( 'mysql' ),
			$id
		);

		return $result !== false;
	}

	/**
	 * Create a new promo code record.
	 *
	 * @return PromoCode|false
	 */
	public function create( array $data ): PromoCode|false {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );
		$data['used_count'] = $data['used_count'] ?? 0;

		// Encode JSON fields if provided as arrays.
		if ( isset( $data['applicable_room_types'] ) && is_array( $data['applicable_room_types'] ) ) {
			$data['applicable_room_types'] = wp_json_encode( $data['applicable_room_types'] );
		}

		if ( isset( $data['applicable_sources'] ) && is_array( $data['applicable_sources'] ) ) {
			$data['applicable_sources'] = wp_json_encode( $data['applicable_sources'] );
		}

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update an existing promo code record.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		// Encode JSON fields if provided as arrays.
		if ( isset( $data['applicable_room_types'] ) && is_array( $data['applicable_room_types'] ) ) {
			$data['applicable_room_types'] = wp_json_encode( $data['applicable_room_types'] );
		}

		if ( isset( $data['applicable_sources'] ) && is_array( $data['applicable_sources'] ) ) {
			$data['applicable_sources'] = wp_json_encode( $data['applicable_sources'] );
		}

		return parent::update( $id, $data );
	}

	/**
	 * Count the number of times a specific guest has used a promo code.
	 *
	 * This method checks the booking_logs or a dedicated usage table.
	 * For now, returns 0 as a stub — integrate with booking records as needed.
	 *
	 * @param int $promoId The promo code ID.
	 * @param int $guestId The guest ID.
	 */
	public function getGuestUsageCount( int $promoId, int $guestId ): int {
		/**
		 * Filter to retrieve guest-specific promo usage count.
		 *
		 * Other modules (e.g., Bookings) can hook in to supply the actual count
		 * by querying their own tables.
		 */
		return (int) apply_filters(
			'nozule/promotions/guest_usage_count',
			0,
			$promoId,
			$guestId
		);
	}
}
