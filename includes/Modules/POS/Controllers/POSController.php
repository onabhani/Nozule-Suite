<?php

namespace Nozule\Modules\POS\Controllers;

use Nozule\Modules\POS\Models\POSItem;
use Nozule\Modules\POS\Models\POSOrder;
use Nozule\Modules\POS\Models\POSOutlet;
use Nozule\Modules\POS\Services\POSService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for POS administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/pos/outlets               List outlets
 *   POST   /nozule/v1/admin/pos/outlets               Create / update outlet
 *   DELETE /nozule/v1/admin/pos/outlets/{id}           Delete outlet
 *   GET    /nozule/v1/admin/pos/items                  List items (filter by outlet_id)
 *   POST   /nozule/v1/admin/pos/items                  Create / update item
 *   DELETE /nozule/v1/admin/pos/items/{id}             Delete item
 *   GET    /nozule/v1/admin/pos/orders                 List orders (filtered)
 *   POST   /nozule/v1/admin/pos/orders                 Create new order
 *   GET    /nozule/v1/admin/pos/orders/{id}            Get order detail with items
 *   PUT    /nozule/v1/admin/pos/orders/{id}/status     Update order status
 *   POST   /nozule/v1/admin/pos/orders/{id}/post-to-folio  Post order to room folio
 *   GET    /nozule/v1/admin/pos/stats                  Daily revenue summary
 */
class POSController {

	private POSService $posService;

	public function __construct( POSService $posService ) {
		$this->posService = $posService;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$namespace = 'nozule/v1';

		// ── Outlets ──────────────────────────────────────────────────────

		register_rest_route( $namespace, '/admin/pos/outlets', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listOutlets' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveOutlet' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/pos/outlets/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'deleteOutlet' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// ── Items ────────────────────────────────────────────────────────

		register_rest_route( $namespace, '/admin/pos/items', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listItems' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => [
					'outlet_id' => [
						'required'          => false,
						'validate_callback' => function ( $v ) { return is_numeric( $v ); },
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveItem' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/pos/items/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'deleteItem' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// ── Orders ───────────────────────────────────────────────────────

		register_rest_route( $namespace, '/admin/pos/orders', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listOrders' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => [
					'outlet_id' => [
						'required'          => false,
						'validate_callback' => function ( $v ) { return is_numeric( $v ); },
						'sanitize_callback' => 'absint',
					],
					'status' => [
						'required'          => false,
						'validate_callback' => function ( $v ) {
							return in_array( $v, POSOrder::validStatuses(), true );
						},
						'sanitize_callback' => 'sanitize_text_field',
					],
					'booking_id' => [
						'required'          => false,
						'validate_callback' => function ( $v ) { return is_numeric( $v ); },
						'sanitize_callback' => 'absint',
					],
					'room_number' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'date_from' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'date_to' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'page' => [
						'required'          => false,
						'validate_callback' => function ( $v ) { return is_numeric( $v ); },
						'sanitize_callback' => 'absint',
						'default'           => 1,
					],
					'per_page' => [
						'required'          => false,
						'validate_callback' => function ( $v ) { return is_numeric( $v ) && $v > 0 && $v <= 100; },
						'sanitize_callback' => 'absint',
						'default'           => 20,
					],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'createOrder' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( $namespace, '/admin/pos/orders/(?P<id>\d+)', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getOrder' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		register_rest_route( $namespace, '/admin/pos/orders/(?P<id>\d+)/status', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'updateOrderStatus' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		register_rest_route( $namespace, '/admin/pos/orders/(?P<id>\d+)/post-to-folio', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'postToFolio' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => $this->getIdArgs(),
		] );

		// ── Stats ────────────────────────────────────────────────────────

		register_rest_route( $namespace, '/admin/pos/stats', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getStats' ],
			'permission_callback' => [ $this, 'checkAdminPermission' ],
			'args'                => [
				'date' => [
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	// =====================================================================
	// Outlets
	// =====================================================================

	/**
	 * List all outlets with item counts.
	 */
	public function listOutlets( WP_REST_Request $request ): WP_REST_Response {
		$outlets = $this->posService->getOutlets();

		$data = array_map( function ( POSOutlet $outlet ) {
			$arr = $outlet->toArray();
			$arr['item_count'] = $this->posService->getItems( $outlet->id );
			$arr['item_count'] = count( $arr['item_count'] );
			return $arr;
		}, $outlets );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Create or update an outlet.
	 */
	public function saveOutlet( WP_REST_Request $request ): WP_REST_Response {
		$data = [
			'name'        => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'name_ar'     => $request->get_param( 'name_ar' ) ? sanitize_text_field( $request->get_param( 'name_ar' ) ) : null,
			'type'        => sanitize_text_field( $request->get_param( 'type' ) ?? POSOutlet::TYPE_RESTAURANT ),
			'description' => $request->get_param( 'description' ) ? sanitize_textarea_field( $request->get_param( 'description' ) ) : null,
			'status'      => sanitize_text_field( $request->get_param( 'status' ) ?? POSOutlet::STATUS_ACTIVE ),
			'sort_order'  => absint( $request->get_param( 'sort_order' ) ?? 0 ),
		];

		// Include ID for updates.
		$id = $request->get_param( 'id' );
		if ( $id ) {
			$data['id'] = absint( $id );
		}

		$result = $this->posService->saveOutlet( $data );

		if ( $result instanceof POSOutlet ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => $id
					? __( 'Outlet updated successfully.', 'nozule' )
					: __( 'Outlet created successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], $id ? 200 : 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Delete an outlet.
	 */
	public function deleteOutlet( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->posService->deleteOutlet( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Outlet deleted successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete outlet.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	// =====================================================================
	// Items
	// =====================================================================

	/**
	 * List items, optionally filtered by outlet.
	 */
	public function listItems( WP_REST_Request $request ): WP_REST_Response {
		$outletId = $request->get_param( 'outlet_id' ) ? absint( $request->get_param( 'outlet_id' ) ) : null;
		$items    = $this->posService->getItems( $outletId );

		$data = array_map( function ( POSItem $item ) {
			return $item->toArray();
		}, $items );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Create or update an item.
	 */
	public function saveItem( WP_REST_Request $request ): WP_REST_Response {
		$data = [
			'outlet_id'  => absint( $request->get_param( 'outlet_id' ) ?? 0 ),
			'name'       => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'name_ar'    => $request->get_param( 'name_ar' ) ? sanitize_text_field( $request->get_param( 'name_ar' ) ) : null,
			'category'   => $request->get_param( 'category' ) ? sanitize_text_field( $request->get_param( 'category' ) ) : null,
			'price'      => (float) ( $request->get_param( 'price' ) ?? 0 ),
			'status'     => sanitize_text_field( $request->get_param( 'status' ) ?? POSItem::STATUS_ACTIVE ),
			'sort_order' => absint( $request->get_param( 'sort_order' ) ?? 0 ),
		];

		$id = $request->get_param( 'id' );
		if ( $id ) {
			$data['id'] = absint( $id );
		}

		$result = $this->posService->saveItem( $data );

		if ( $result instanceof POSItem ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => $id
					? __( 'Item updated successfully.', 'nozule' )
					: __( 'Item created successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], $id ? 200 : 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Delete an item.
	 */
	public function deleteItem( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->posService->deleteItem( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Item deleted successfully.', 'nozule' ),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete item.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	// =====================================================================
	// Orders
	// =====================================================================

	/**
	 * List orders with filters and pagination.
	 */
	public function listOrders( WP_REST_Request $request ): WP_REST_Response {
		$filters = [];

		if ( $request->get_param( 'outlet_id' ) ) {
			$filters['outlet_id'] = absint( $request->get_param( 'outlet_id' ) );
		}
		if ( $request->get_param( 'status' ) ) {
			$filters['status'] = sanitize_text_field( $request->get_param( 'status' ) );
		}
		if ( $request->get_param( 'booking_id' ) ) {
			$filters['booking_id'] = absint( $request->get_param( 'booking_id' ) );
		}
		if ( $request->get_param( 'room_number' ) ) {
			$filters['room_number'] = sanitize_text_field( $request->get_param( 'room_number' ) );
		}
		if ( $request->get_param( 'date_from' ) ) {
			$filters['date_from'] = sanitize_text_field( $request->get_param( 'date_from' ) );
		}
		if ( $request->get_param( 'date_to' ) ) {
			$filters['date_to'] = sanitize_text_field( $request->get_param( 'date_to' ) );
		}

		$page    = absint( $request->get_param( 'page' ) ) ?: 1;
		$perPage = absint( $request->get_param( 'per_page' ) ) ?: 20;

		$result = $this->posService->getOrders( $filters, $page, $perPage );

		$items = array_map( function ( POSOrder $order ) {
			return $order->toArray();
		}, $result['items'] );

		return new WP_REST_Response( [
			'success'    => true,
			'data'       => $items,
			'pagination' => [
				'total'       => $result['total'],
				'page'        => $result['page'],
				'total_pages' => $result['total_pages'],
			],
		], 200 );
	}

	/**
	 * Create a new order.
	 */
	public function createOrder( WP_REST_Request $request ): WP_REST_Response {
		$outletId   = absint( $request->get_param( 'outlet_id' ) );
		$items      = $request->get_param( 'items' ) ?: [];
		$roomNumber = $request->get_param( 'room_number' ) ? sanitize_text_field( $request->get_param( 'room_number' ) ) : null;
		$bookingId  = $request->get_param( 'booking_id' ) ? absint( $request->get_param( 'booking_id' ) ) : null;
		$guestId    = $request->get_param( 'guest_id' ) ? absint( $request->get_param( 'guest_id' ) ) : null;
		$notes      = $request->get_param( 'notes' ) ? sanitize_textarea_field( $request->get_param( 'notes' ) ) : null;

		if ( ! is_array( $items ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Items must be an array.', 'nozule' ),
			], 422 );
		}

		$result = $this->posService->createOrder( $outletId, $items, $roomNumber, $bookingId, $guestId, $notes );

		if ( $result instanceof POSOrder ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Order created successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to create order.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Get order detail with items and outlet info.
	 */
	public function getOrder( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->posService->getOrderSummary( $id );

		if ( ! $result ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Order not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $result,
		], 200 );
	}

	/**
	 * Update order status (cancel).
	 */
	public function updateOrderStatus( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

		if ( ! in_array( $status, POSOrder::validStatuses(), true ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Invalid status.', 'nozule' ),
			], 422 );
		}

		// Only cancellation is handled via status update; posting is a separate route.
		if ( $status === POSOrder::STATUS_CANCELLED ) {
			$result = $this->posService->cancelOrder( $id );
		} else {
			// For other statuses, just return the order.
			$order = $this->posService->getOrder( $id );
			if ( ! $order ) {
				return new WP_REST_Response( [
					'success' => false,
					'message' => __( 'Order not found.', 'nozule' ),
				], 404 );
			}
			$result = $order;
		}

		if ( $result instanceof POSOrder ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Order status updated.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to update order status.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	/**
	 * Post an order to the room folio.
	 */
	public function postToFolio( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->posService->postToFolio( $id );

		if ( $result instanceof POSOrder ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Order posted to folio successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		$statusCode = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to post order to folio.', 'nozule' ),
			'errors'  => $result,
		], $statusCode );
	}

	// =====================================================================
	// Stats
	// =====================================================================

	/**
	 * Get daily revenue summary.
	 */
	public function getStats( WP_REST_Request $request ): WP_REST_Response {
		$date = $request->get_param( 'date' ) ?: current_time( 'Y-m-d' );

		$summary = $this->posService->getDailySummary( $date );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $summary,
		], 200 );
	}

	// =====================================================================
	// Helpers
	// =====================================================================

	/**
	 * Common ID argument definition.
	 */
	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => function ( $value ) { return is_numeric( $value ) && $value > 0; },
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Permission callback: require nzl_admin or manage_options.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
