<?php

namespace Nozule\Modules\POS\Repositories;

use Nozule\Core\Database;
use Nozule\Modules\POS\Models\POSItem;
use Nozule\Modules\POS\Models\POSOrder;
use Nozule\Modules\POS\Models\POSOrderItem;
use Nozule\Modules\POS\Models\POSOutlet;

/**
 * Repository for POS CRUD and querying.
 *
 * Manages four tables: pos_outlets, pos_items, pos_orders, pos_order_items.
 */
class POSRepository {

	private Database $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	// =========================================================================
	// Outlets
	// =========================================================================

	/**
	 * Get all outlets, ordered by sort_order then name.
	 *
	 * @return POSOutlet[]
	 */
	public function getOutlets(): array {
		$table = $this->db->table( 'pos_outlets' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC"
		);

		return POSOutlet::fromRows( $rows );
	}

	/**
	 * Get a single outlet by ID.
	 */
	public function getOutlet( int $id ): ?POSOutlet {
		$table = $this->db->table( 'pos_outlets' );
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? POSOutlet::fromRow( $row ) : null;
	}

	/**
	 * Create or update an outlet.
	 *
	 * @return POSOutlet|false
	 */
	public function saveOutlet( array $data ) {
		$now = current_time( 'mysql', true );

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;
			$result = $this->db->update( 'pos_outlets', $data, [ 'id' => $id ] );
			return $result !== false ? $this->getOutlet( $id ) : false;
		}

		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'pos_outlets', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getOutlet( $id );
	}

	/**
	 * Delete an outlet by ID.
	 */
	public function deleteOutlet( int $id ): bool {
		return $this->db->delete( 'pos_outlets', [ 'id' => $id ] ) !== false;
	}

	/**
	 * Count items belonging to an outlet.
	 */
	public function countOutletItems( int $outletId ): int {
		$table = $this->db->table( 'pos_items' );
		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE outlet_id = %d",
			$outletId
		);
	}

	// =========================================================================
	// Items
	// =========================================================================

	/**
	 * Get items, optionally filtered by outlet.
	 *
	 * @return POSItem[]
	 */
	public function getItems( ?int $outletId = null ): array {
		$table = $this->db->table( 'pos_items' );

		if ( $outletId ) {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table} WHERE outlet_id = %d ORDER BY sort_order ASC, name ASC",
				$outletId
			);
		} else {
			$rows = $this->db->getResults(
				"SELECT * FROM {$table} ORDER BY outlet_id ASC, sort_order ASC, name ASC"
			);
		}

		return POSItem::fromRows( $rows );
	}

	/**
	 * Get a single item by ID.
	 */
	public function getItem( int $id ): ?POSItem {
		$table = $this->db->table( 'pos_items' );
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? POSItem::fromRow( $row ) : null;
	}

	/**
	 * Create or update an item.
	 *
	 * @return POSItem|false
	 */
	public function saveItem( array $data ) {
		$now = current_time( 'mysql', true );

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;
			$result = $this->db->update( 'pos_items', $data, [ 'id' => $id ] );
			return $result !== false ? $this->getItem( $id ) : false;
		}

		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'pos_items', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getItem( $id );
	}

	/**
	 * Delete an item by ID.
	 */
	public function deleteItem( int $id ): bool {
		return $this->db->delete( 'pos_items', [ 'id' => $id ] ) !== false;
	}

	// =========================================================================
	// Orders
	// =========================================================================

	/**
	 * Get orders with optional filtering and pagination.
	 *
	 * @param array $filters Associative array of filter keys: outlet_id, status, booking_id, room_number, date_from, date_to.
	 * @param int   $page    Page number (1-based).
	 * @param int   $perPage Items per page.
	 *
	 * @return array{items: POSOrder[], total: int, page: int, total_pages: int}
	 */
	public function getOrders( array $filters = [], int $page = 1, int $perPage = 20 ): array {
		$table      = $this->db->table( 'pos_orders' );
		$conditions = [];
		$values     = [];

		if ( ! empty( $filters['outlet_id'] ) ) {
			$conditions[] = 'outlet_id = %d';
			$values[]     = (int) $filters['outlet_id'];
		}

		if ( ! empty( $filters['status'] ) ) {
			$conditions[] = 'status = %s';
			$values[]     = $filters['status'];
		}

		if ( ! empty( $filters['booking_id'] ) ) {
			$conditions[] = 'booking_id = %d';
			$values[]     = (int) $filters['booking_id'];
		}

		if ( ! empty( $filters['room_number'] ) ) {
			$conditions[] = 'room_number = %s';
			$values[]     = $filters['room_number'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$conditions[] = 'DATE(created_at) >= %s';
			$values[]     = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$conditions[] = 'DATE(created_at) <= %s';
			$values[]     = $filters['date_to'];
		}

		$where = '';
		if ( ! empty( $conditions ) ) {
			$where = 'WHERE ' . implode( ' AND ', $conditions );
		}

		// Count total.
		$countSql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( ! empty( $values ) ) {
			$total = (int) $this->db->getVar( $countSql, ...$values );
		} else {
			$total = (int) $this->db->getVar( $countSql );
		}

		$totalPages = max( 1, (int) ceil( $total / $perPage ) );
		$page       = max( 1, min( $page, $totalPages ) );
		$offset     = ( $page - 1 ) * $perPage;

		// Fetch rows.
		$sql = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$allValues = array_merge( $values, [ $perPage, $offset ] );
		$rows = $this->db->getResults( $sql, ...$allValues );

		return [
			'items'       => POSOrder::fromRows( $rows ),
			'total'       => $total,
			'page'        => $page,
			'total_pages' => $totalPages,
		];
	}

	/**
	 * Get a single order by ID.
	 */
	public function getOrder( int $id ): ?POSOrder {
		$table = $this->db->table( 'pos_orders' );
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? POSOrder::fromRow( $row ) : null;
	}

	/**
	 * Create an order.
	 *
	 * @return POSOrder|false
	 */
	public function createOrder( array $data ) {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		if ( empty( $data['order_number'] ) ) {
			$data['order_number'] = $this->generateOrderNumber();
		}

		$id = $this->db->insert( 'pos_orders', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getOrder( $id );
	}

	/**
	 * Update an order.
	 */
	public function updateOrder( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		return $this->db->update( 'pos_orders', $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Update order status.
	 */
	public function updateOrderStatus( int $id, string $status ): bool {
		return $this->updateOrder( $id, [ 'status' => $status ] );
	}

	/**
	 * Get orders by booking ID.
	 *
	 * @return POSOrder[]
	 */
	public function getOrdersByBooking( int $bookingId ): array {
		$table = $this->db->table( 'pos_orders' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC",
			$bookingId
		);

		return POSOrder::fromRows( $rows );
	}

	// =========================================================================
	// Order Items
	// =========================================================================

	/**
	 * Get all items for an order.
	 *
	 * @return POSOrderItem[]
	 */
	public function getOrderItems( int $orderId ): array {
		$table = $this->db->table( 'pos_order_items' );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC",
			$orderId
		);

		return POSOrderItem::fromRows( $rows );
	}

	/**
	 * Create an order item.
	 *
	 * @return POSOrderItem|false
	 */
	public function createOrderItem( array $data ) {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;

		$id = $this->db->insert( 'pos_order_items', $data );
		if ( $id === false ) {
			return false;
		}

		$table = $this->db->table( 'pos_order_items' );
		$row   = $this->db->getRow( "SELECT * FROM {$table} WHERE id = %d", $id );
		return $row ? POSOrderItem::fromRow( $row ) : false;
	}

	/**
	 * Delete all items for an order.
	 */
	public function deleteOrderItems( int $orderId ): bool {
		$table = $this->db->table( 'pos_order_items' );
		return $this->db->query(
			"DELETE FROM {$table} WHERE order_id = %d",
			$orderId
		) !== false;
	}

	// =========================================================================
	// Statistics
	// =========================================================================

	/**
	 * Get daily revenue summary for a given date.
	 *
	 * @return array{total_orders: int, total_revenue: float, by_outlet: array}
	 */
	public function getDailySummary( string $date ): array {
		$orders_table  = $this->db->table( 'pos_orders' );
		$outlets_table = $this->db->table( 'pos_outlets' );

		// Overall totals for the date (exclude cancelled).
		$overall = $this->db->getRow(
			"SELECT COUNT(*) AS total_orders, COALESCE(SUM(total), 0) AS total_revenue
			FROM {$orders_table}
			WHERE DATE(created_at) = %s AND status != 'cancelled'",
			$date
		);

		// Per-outlet breakdown.
		$byOutlet = $this->db->getResults(
			"SELECT o.id AS outlet_id, o.name AS outlet_name, o.type AS outlet_type,
			        COUNT(ord.id) AS order_count, COALESCE(SUM(ord.total), 0) AS revenue
			FROM {$outlets_table} o
			LEFT JOIN {$orders_table} ord ON ord.outlet_id = o.id
			     AND DATE(ord.created_at) = %s AND ord.status != 'cancelled'
			GROUP BY o.id
			ORDER BY o.sort_order ASC, o.name ASC",
			$date
		);

		$outletData = [];
		foreach ( $byOutlet as $row ) {
			$outletData[] = [
				'outlet_id'   => (int) $row->outlet_id,
				'outlet_name' => $row->outlet_name,
				'outlet_type' => $row->outlet_type,
				'order_count' => (int) $row->order_count,
				'revenue'     => (float) $row->revenue,
			];
		}

		return [
			'date'          => $date,
			'total_orders'  => (int) ( $overall->total_orders ?? 0 ),
			'total_revenue' => (float) ( $overall->total_revenue ?? 0 ),
			'by_outlet'     => $outletData,
		];
	}

	/**
	 * Get revenue for a specific outlet in a date range.
	 *
	 * @return array{order_count: int, revenue: float}
	 */
	public function getOutletRevenue( int $outletId, string $dateFrom, string $dateTo ): array {
		$table = $this->db->table( 'pos_orders' );

		$row = $this->db->getRow(
			"SELECT COUNT(*) AS order_count, COALESCE(SUM(total), 0) AS revenue
			FROM {$table}
			WHERE outlet_id = %d AND DATE(created_at) >= %s AND DATE(created_at) <= %s AND status != 'cancelled'",
			$outletId,
			$dateFrom,
			$dateTo
		);

		return [
			'order_count' => (int) ( $row->order_count ?? 0 ),
			'revenue'     => (float) ( $row->revenue ?? 0 ),
		];
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Generate a unique order number in the format POS-YYYY-NNNNN.
	 */
	private function generateOrderNumber(): string {
		$table = $this->db->table( 'pos_orders' );
		$year  = gmdate( 'Y' );

		$count = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} WHERE order_number LIKE %s",
			'POS-' . $year . '-%'
		);

		$next = $count + 1;

		return sprintf( 'POS-%s-%05d', $year, $next );
	}
}
