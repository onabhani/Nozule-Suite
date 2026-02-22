<?php

namespace Nozule\Modules\POS\Services;

use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Billing\Models\FolioItem;
use Nozule\Modules\Billing\Repositories\FolioItemRepository;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\POS\Models\POSItem;
use Nozule\Modules\POS\Models\POSOrder;
use Nozule\Modules\POS\Models\POSOutlet;
use Nozule\Modules\POS\Repositories\POSRepository;

/**
 * Service layer orchestrating POS operations.
 *
 * Handles order creation, posting charges to room folios,
 * and order lifecycle management.
 */
class POSService {

	private POSRepository $repository;
	private Database $db;
	private FolioRepository $folioRepository;
	private FolioItemRepository $folioItemRepository;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		POSRepository $repository,
		Database $db,
		FolioRepository $folioRepository,
		FolioItemRepository $folioItemRepository,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository         = $repository;
		$this->db                 = $db;
		$this->folioRepository    = $folioRepository;
		$this->folioItemRepository = $folioItemRepository;
		$this->events             = $events;
		$this->logger             = $logger;
	}

	// =========================================================================
	// Outlet CRUD (pass-through with validation)
	// =========================================================================

	/**
	 * Get all outlets.
	 *
	 * @return POSOutlet[]
	 */
	public function getOutlets(): array {
		return $this->repository->getOutlets();
	}

	/**
	 * Get a single outlet.
	 */
	public function getOutlet( int $id ): ?POSOutlet {
		return $this->repository->getOutlet( $id );
	}

	/**
	 * Save (create or update) an outlet.
	 *
	 * @return POSOutlet|array POSOutlet on success, error array on failure.
	 */
	public function saveOutlet( array $data ) {
		// Validate type.
		if ( isset( $data['type'] ) && ! in_array( $data['type'], POSOutlet::validTypes(), true ) ) {
			return [ 'type' => [ __( 'Invalid outlet type.', 'nozule' ) ] ];
		}

		// Validate status.
		if ( isset( $data['status'] ) && ! in_array( $data['status'], POSOutlet::validStatuses(), true ) ) {
			return [ 'status' => [ __( 'Invalid outlet status.', 'nozule' ) ] ];
		}

		// Required name for new outlets.
		if ( empty( $data['id'] ) && empty( $data['name'] ) ) {
			return [ 'name' => [ __( 'Outlet name is required.', 'nozule' ) ] ];
		}

		$result = $this->repository->saveOutlet( $data );
		if ( ! $result ) {
			$this->logger->error( 'Failed to save POS outlet', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to save outlet.', 'nozule' ) ] ];
		}

		return $result;
	}

	/**
	 * Delete an outlet.
	 */
	public function deleteOutlet( int $id ): true|array {
		$outlet = $this->repository->getOutlet( $id );
		if ( ! $outlet ) {
			return [ 'id' => [ __( 'Outlet not found.', 'nozule' ) ] ];
		}

		// Check for existing items.
		$itemCount = $this->repository->countOutletItems( $id );
		if ( $itemCount > 0 ) {
			return [ 'id' => [ __( 'Cannot delete outlet that has items. Remove items first.', 'nozule' ) ] ];
		}

		$success = $this->repository->deleteOutlet( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete outlet.', 'nozule' ) ] ];
		}

		$this->logger->info( 'POS outlet deleted', [ 'outlet_id' => $id ] );
		return true;
	}

	// =========================================================================
	// Item CRUD (pass-through with validation)
	// =========================================================================

	/**
	 * Get items, optionally filtered by outlet.
	 *
	 * @return POSItem[]
	 */
	public function getItems( ?int $outletId = null ): array {
		return $this->repository->getItems( $outletId );
	}

	/**
	 * Get a single item.
	 */
	public function getItem( int $id ): ?POSItem {
		return $this->repository->getItem( $id );
	}

	/**
	 * Save (create or update) an item.
	 *
	 * @return POSItem|array POSItem on success, error array on failure.
	 */
	public function saveItem( array $data ) {
		// Validate outlet exists for new items.
		if ( empty( $data['id'] ) ) {
			if ( empty( $data['outlet_id'] ) ) {
				return [ 'outlet_id' => [ __( 'Outlet is required.', 'nozule' ) ] ];
			}
			$outlet = $this->repository->getOutlet( (int) $data['outlet_id'] );
			if ( ! $outlet ) {
				return [ 'outlet_id' => [ __( 'Outlet not found.', 'nozule' ) ] ];
			}
			if ( empty( $data['name'] ) ) {
				return [ 'name' => [ __( 'Item name is required.', 'nozule' ) ] ];
			}
			if ( ! isset( $data['price'] ) || (float) $data['price'] < 0 ) {
				return [ 'price' => [ __( 'A valid price is required.', 'nozule' ) ] ];
			}
		}

		if ( isset( $data['status'] ) && ! in_array( $data['status'], POSItem::validStatuses(), true ) ) {
			return [ 'status' => [ __( 'Invalid item status.', 'nozule' ) ] ];
		}

		$result = $this->repository->saveItem( $data );
		if ( ! $result ) {
			$this->logger->error( 'Failed to save POS item', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to save item.', 'nozule' ) ] ];
		}

		return $result;
	}

	/**
	 * Delete an item.
	 */
	public function deleteItem( int $id ): true|array {
		$item = $this->repository->getItem( $id );
		if ( ! $item ) {
			return [ 'id' => [ __( 'Item not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->deleteItem( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete item.', 'nozule' ) ] ];
		}

		$this->logger->info( 'POS item deleted', [ 'item_id' => $id ] );
		return true;
	}

	// =========================================================================
	// Order Operations
	// =========================================================================

	/**
	 * Create a new POS order.
	 *
	 * @param int         $outletId   The outlet originating this order.
	 * @param array       $items      Array of [{item_id, quantity}].
	 * @param string|null $roomNumber Optional room number.
	 * @param int|null    $bookingId  Optional booking ID.
	 * @param int|null    $guestId    Optional guest ID.
	 * @param string|null $notes      Optional notes.
	 *
	 * @return POSOrder|array POSOrder on success, error array on failure.
	 */
	public function createOrder(
		int $outletId,
		array $items,
		?string $roomNumber = null,
		?int $bookingId = null,
		?int $guestId = null,
		?string $notes = null
	) {
		// Validate outlet.
		$outlet = $this->repository->getOutlet( $outletId );
		if ( ! $outlet ) {
			return [ 'outlet_id' => [ __( 'Outlet not found.', 'nozule' ) ] ];
		}

		if ( ! $outlet->isActive() ) {
			return [ 'outlet_id' => [ __( 'Outlet is inactive.', 'nozule' ) ] ];
		}

		if ( empty( $items ) ) {
			return [ 'items' => [ __( 'At least one item is required.', 'nozule' ) ] ];
		}

		// Validate and resolve each item.
		$resolvedItems = [];
		$subtotal      = 0.0;

		foreach ( $items as $entry ) {
			$itemId  = (int) ( $entry['item_id'] ?? 0 );
			$qty     = (int) ( $entry['quantity'] ?? 1 );

			if ( $itemId <= 0 || $qty <= 0 ) {
				return [ 'items' => [ __( 'Invalid item data.', 'nozule' ) ] ];
			}

			$posItem = $this->repository->getItem( $itemId );
			if ( ! $posItem ) {
				return [ 'items' => [ sprintf(
					/* translators: %d: item ID */
					__( 'Item #%d not found.', 'nozule' ),
					$itemId
				) ] ];
			}

			if ( (int) $posItem->outlet_id !== $outletId ) {
				return [ 'items' => [ sprintf(
					/* translators: %s: item name */
					__( 'Item "%s" does not belong to the selected outlet.', 'nozule' ),
					$posItem->name
				) ] ];
			}

			$lineTotal = round( (float) $posItem->price * $qty, 2 );
			$subtotal += $lineTotal;

			$resolvedItems[] = [
				'item'     => $posItem,
				'quantity' => $qty,
				'subtotal' => $lineTotal,
			];
		}

		$total = round( $subtotal, 2 );

		// Begin transaction.
		$this->db->beginTransaction();

		try {
			// Create order.
			$order = $this->repository->createOrder( [
				'outlet_id'   => $outletId,
				'room_number' => $roomNumber ? sanitize_text_field( $roomNumber ) : null,
				'booking_id'  => $bookingId,
				'guest_id'    => $guestId,
				'items_count' => count( $resolvedItems ),
				'subtotal'    => $subtotal,
				'tax_total'   => 0,
				'total'       => $total,
				'status'      => POSOrder::STATUS_OPEN,
				'notes'       => $notes ? sanitize_textarea_field( $notes ) : null,
				'created_by'  => get_current_user_id() ?: null,
			] );

			if ( ! $order ) {
				$this->db->rollback();
				return [ 'general' => [ __( 'Failed to create order.', 'nozule' ) ] ];
			}

			// Create order items.
			foreach ( $resolvedItems as $resolved ) {
				$posItem = $resolved['item'];
				$result = $this->repository->createOrderItem( [
					'order_id'   => $order->id,
					'item_id'    => $posItem->id,
					'item_name'  => $posItem->name,
					'quantity'   => $resolved['quantity'],
					'unit_price' => (float) $posItem->price,
					'subtotal'   => $resolved['subtotal'],
				] );

				if ( ! $result ) {
					$this->db->rollback();
					return [ 'general' => [ __( 'Failed to create order items.', 'nozule' ) ] ];
				}
			}

			$this->db->commit();
		} catch ( \Throwable $e ) {
			$this->db->rollback();
			$this->logger->error( 'POS order creation failed', [
				'outlet_id' => $outletId,
				'error'     => $e->getMessage(),
			] );
			return [ 'general' => [ __( 'An unexpected error occurred while creating the order.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'pos/order_created', $order );
		$this->logger->info( 'POS order created', [
			'order_id'     => $order->id,
			'order_number' => $order->order_number,
			'outlet_id'    => $outletId,
			'total'        => $total,
		] );

		return $order;
	}

	/**
	 * Get order detail with items and outlet info.
	 *
	 * @return array|null
	 */
	public function getOrderSummary( int $orderId ): ?array {
		$order = $this->repository->getOrder( $orderId );
		if ( ! $order ) {
			return null;
		}

		$items  = $this->repository->getOrderItems( $orderId );
		$outlet = $this->repository->getOutlet( $order->outlet_id );

		return [
			'order'  => $order->toArray(),
			'items'  => array_map( function ( $item ) {
				return $item->toArray();
			}, $items ),
			'outlet' => $outlet ? $outlet->toArray() : null,
		];
	}

	/**
	 * Post an order's total to the booking's folio.
	 *
	 * @return POSOrder|array POSOrder on success, error array on failure.
	 */
	public function postToFolio( int $orderId ) {
		$order = $this->repository->getOrder( $orderId );
		if ( ! $order ) {
			return [ 'id' => [ __( 'Order not found.', 'nozule' ) ] ];
		}

		if ( $order->isPosted() ) {
			return [ 'status' => [ __( 'Order has already been posted to folio.', 'nozule' ) ] ];
		}

		if ( $order->isCancelled() ) {
			return [ 'status' => [ __( 'Cannot post a cancelled order.', 'nozule' ) ] ];
		}

		if ( ! $order->booking_id ) {
			return [ 'booking_id' => [ __( 'Order has no associated booking. Assign a room/booking first.', 'nozule' ) ] ];
		}

		// Find the open folio for this booking.
		$folio = $this->folioRepository->findByBooking( $order->booking_id );
		if ( ! $folio ) {
			return [ 'folio' => [ __( 'No open folio found for this booking. Please create a folio first.', 'nozule' ) ] ];
		}

		if ( ! $folio->isOpen() ) {
			return [ 'folio' => [ __( 'The folio for this booking is closed or voided.', 'nozule' ) ] ];
		}

		// Build description.
		$outlet = $this->repository->getOutlet( $order->outlet_id );
		$outletName = $outlet ? $outlet->name : __( 'POS', 'nozule' );

		$description = sprintf(
			/* translators: 1: outlet name, 2: order number */
			__( '%1$s - Order %2$s', 'nozule' ),
			$outletName,
			$order->order_number
		);

		// Create the folio item.
		$folioItem = $this->folioItemRepository->create( [
			'folio_id'    => $folio->id,
			'category'    => FolioItem::CAT_SERVICE,
			'description' => $description,
			'quantity'    => 1,
			'unit_price'  => (float) $order->total,
			'subtotal'    => (float) $order->total,
			'tax_json'    => '[]',
			'tax_total'   => 0,
			'total'       => (float) $order->total,
			'date'        => current_time( 'Y-m-d' ),
			'posted_by'   => get_current_user_id() ?: null,
		] );

		if ( ! $folioItem ) {
			$this->logger->error( 'Failed to post POS order to folio', [
				'order_id' => $orderId,
				'folio_id' => $folio->id,
			] );
			return [ 'general' => [ __( 'Failed to post charge to folio.', 'nozule' ) ] ];
		}

		// Recalculate folio totals.
		$this->folioRepository->recalculateTotals( $folio->id );

		// Mark order as posted and store the folio_item reference.
		$this->repository->updateOrder( $orderId, [
			'status'        => POSOrder::STATUS_POSTED,
			'folio_item_id' => $folioItem->id,
		] );

		$updatedOrder = $this->repository->getOrder( $orderId );

		$this->events->dispatch( 'pos/order_posted_to_folio', $updatedOrder, $folioItem );
		$this->logger->info( 'POS order posted to folio', [
			'order_id'      => $orderId,
			'order_number'  => $order->order_number,
			'folio_id'      => $folio->id,
			'folio_item_id' => $folioItem->id,
			'total'         => $order->total,
		] );

		return $updatedOrder;
	}

	/**
	 * Cancel an order and reverse the folio charge if it was posted.
	 *
	 * @return POSOrder|array POSOrder on success, error array on failure.
	 */
	public function cancelOrder( int $orderId ) {
		$order = $this->repository->getOrder( $orderId );
		if ( ! $order ) {
			return [ 'id' => [ __( 'Order not found.', 'nozule' ) ] ];
		}

		if ( $order->isCancelled() ) {
			return [ 'status' => [ __( 'Order is already cancelled.', 'nozule' ) ] ];
		}

		// If the order was posted to folio, reverse the charge.
		if ( $order->isPosted() && $order->folio_item_id ) {
			$folioItem = $this->folioItemRepository->find( $order->folio_item_id );
			if ( $folioItem ) {
				$this->folioItemRepository->delete( $order->folio_item_id );
				// Recalculate folio totals.
				$this->folioRepository->recalculateTotals( $folioItem->folio_id );

				$this->logger->info( 'Reversed folio charge for cancelled POS order', [
					'order_id'      => $orderId,
					'folio_item_id' => $order->folio_item_id,
					'folio_id'      => $folioItem->folio_id,
				] );
			}
		}

		$this->repository->updateOrder( $orderId, [
			'status'        => POSOrder::STATUS_CANCELLED,
			'folio_item_id' => null,
		] );

		$updatedOrder = $this->repository->getOrder( $orderId );

		$this->logger->info( 'POS order cancelled', [
			'order_id'     => $orderId,
			'order_number' => $order->order_number,
		] );

		return $updatedOrder;
	}

	/**
	 * Get filtered orders.
	 *
	 * @return array{items: POSOrder[], total: int, page: int, total_pages: int}
	 */
	public function getOrders( array $filters = [], int $page = 1, int $perPage = 20 ): array {
		return $this->repository->getOrders( $filters, $page, $perPage );
	}

	/**
	 * Get a single order.
	 */
	public function getOrder( int $id ): ?POSOrder {
		return $this->repository->getOrder( $id );
	}

	/**
	 * Get orders by booking ID.
	 *
	 * @return POSOrder[]
	 */
	public function getOrdersByBooking( int $bookingId ): array {
		return $this->repository->getOrdersByBooking( $bookingId );
	}

	/**
	 * Get daily summary statistics.
	 *
	 * @return array{date: string, total_orders: int, total_revenue: float, by_outlet: array}
	 */
	public function getDailySummary( string $date ): array {
		return $this->repository->getDailySummary( $date );
	}

	/**
	 * Get revenue for a specific outlet over a date range.
	 *
	 * @return array{order_count: int, revenue: float}
	 */
	public function getOutletRevenue( int $outletId, string $dateFrom, string $dateTo ): array {
		return $this->repository->getOutletRevenue( $outletId, $dateFrom, $dateTo );
	}
}
