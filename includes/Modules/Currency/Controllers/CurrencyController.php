<?php

namespace Nozule\Modules\Currency\Controllers;

use Nozule\Core\ResponseHelper;
use Nozule\Modules\Currency\Models\Currency;
use Nozule\Modules\Currency\Models\ExchangeRate;
use Nozule\Modules\Currency\Services\CurrencyService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for currency and exchange rate management.
 *
 * Routes:
 *   GET    /nozule/v1/admin/currencies              List active currencies (staff)
 *   POST   /nozule/v1/admin/currencies              Create currency (admin)
 *   GET    /nozule/v1/admin/currencies/{id}         Get single currency (staff)
 *   PUT    /nozule/v1/admin/currencies/{id}         Update currency (admin)
 *   DELETE /nozule/v1/admin/currencies/{id}         Delete currency (admin)
 *   PUT    /nozule/v1/admin/currencies/{id}/default Set as default (admin)
 *   GET    /nozule/v1/admin/exchange-rates           List exchange rates (staff)
 *   POST   /nozule/v1/admin/exchange-rates           Create exchange rate (admin)
 *   POST   /nozule/v1/currencies/convert             Convert amount (public)
 */
class CurrencyController {

	private const NAMESPACE = 'nozule/v1';

	private CurrencyService $service;

	public function __construct( CurrencyService $service ) {
		$this->service = $service;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// List and create currencies.
		register_rest_route( self::NAMESPACE, '/admin/currencies', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Single currency operations.
		register_rest_route( self::NAMESPACE, '/admin/currencies/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArgs(),
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Set default currency.
		register_rest_route( self::NAMESPACE, '/admin/currencies/(?P<id>\d+)/default', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'setDefault' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// Exchange rates: list and create.
		register_rest_route( self::NAMESPACE, '/admin/exchange-rates', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'ratesIndex' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getRatesIndexArgs(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'ratesStore' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// Public conversion endpoint.
		register_rest_route( self::NAMESPACE, '/currencies/convert', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'convert' ],
				'permission_callback' => '__return_true',
				'args'                => $this->getConvertArgs(),
			],
		] );
	}

	/**
	 * List all active currencies.
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$currencies = $this->service->getCurrencies();

		$data = array_map(
			fn( Currency $currency ) => $currency->toArray(),
			$currencies
		);

		return ResponseHelper::success( $data, null, [ 'total' => count( $data ) ] );
	}

	/**
	 * Create a new currency.
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractCurrencyData( $request );

		$result = $this->service->createCurrency( $data );

		if ( $result instanceof Currency ) {
			return ResponseHelper::created( $result->toArray(), __( 'Currency created successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Get a single currency.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$currency = $this->service->getCurrency( $id );

		if ( ! $currency ) {
			return ResponseHelper::notFound( __( 'Currency not found.', 'nozule' ) );
		}

		return ResponseHelper::success( $currency->toArray() );
	}

	/**
	 * Update an existing currency.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractCurrencyData( $request );

		$result = $this->service->updateCurrency( $id, $data );

		if ( $result instanceof Currency ) {
			return ResponseHelper::success( $result->toArray(), __( 'Currency updated successfully.', 'nozule' ) );
		}

		if ( isset( $result['id'] ) ) {
			return ResponseHelper::error( $result['id'][0], 404, $result );
		}

		return ResponseHelper::error( __( 'Validation failed.', 'nozule' ), 422, $result );
	}

	/**
	 * Delete a currency.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deleteCurrency( $id );

		if ( $result === true ) {
			return ResponseHelper::success( null, __( 'Currency deleted successfully.', 'nozule' ) );
		}

		$status_code = isset( $result['id'] ) ? 404 : 422;

		return ResponseHelper::error( __( 'Failed to delete currency.', 'nozule' ), $status_code, $result );
	}

	/**
	 * Set a currency as the default.
	 */
	public function setDefault( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->setDefaultCurrency( $id );

		if ( $result ) {
			return ResponseHelper::success( null, __( 'Default currency updated successfully.', 'nozule' ) );
		}

		return ResponseHelper::notFound( __( 'Failed to set default currency.', 'nozule' ) );
	}

	/**
	 * List exchange rate history for a currency pair.
	 */
	public function ratesIndex( WP_REST_Request $request ): WP_REST_Response {
		$from  = sanitize_text_field( $request->get_param( 'from' ) ?? '' );
		$to    = sanitize_text_field( $request->get_param( 'to' ) ?? '' );
		$limit = absint( $request->get_param( 'limit' ) ?? 30 );

		if ( empty( $from ) || empty( $to ) ) {
			return ResponseHelper::error( __( 'Both "from" and "to" currency codes are required.', 'nozule' ), 400 );
		}

		$history = $this->service->getExchangeHistory( $from, $to, $limit );

		$data = array_map(
			fn( ExchangeRate $rate ) => $rate->toArray(),
			$history
		);

		return ResponseHelper::success( $data, null, [ 'total' => count( $data ) ] );
	}

	/**
	 * Create a new exchange rate record.
	 */
	public function ratesStore( WP_REST_Request $request ): WP_REST_Response {
		$from = sanitize_text_field( $request->get_param( 'from_currency' ) ?? '' );
		$to   = sanitize_text_field( $request->get_param( 'to_currency' ) ?? '' );
		$rate = (float) ( $request->get_param( 'rate' ) ?? 0 );

		if ( empty( $from ) || empty( $to ) || $rate <= 0 ) {
			return ResponseHelper::error( __( 'from_currency, to_currency, and a positive rate are required.', 'nozule' ), 400 );
		}

		$result = $this->service->updateExchangeRate( $from, $to, $rate );

		if ( $result instanceof ExchangeRate ) {
			return ResponseHelper::created( $result->toArray(), __( 'Exchange rate saved successfully.', 'nozule' ) );
		}

		return ResponseHelper::error( __( 'Failed to save exchange rate.', 'nozule' ), 422, $result );
	}

	/**
	 * Convert an amount between currencies (public endpoint).
	 */
	public function convert( WP_REST_Request $request ): WP_REST_Response {
		$amount = (float) ( $request->get_param( 'amount' ) ?? 0 );
		$from   = sanitize_text_field( $request->get_param( 'from' ) ?? '' );
		$to     = sanitize_text_field( $request->get_param( 'to' ) ?? '' );

		if ( empty( $from ) || empty( $to ) ) {
			return ResponseHelper::error( __( 'Both "from" and "to" currency codes are required.', 'nozule' ), 400 );
		}

		$converted = $this->service->convert( $amount, $from, $to );
		$rate      = $this->service->getExchangeRate( $from, $to );

		// Attempt to format the result using the target currency.
		$to_currency = $this->service->getCurrencyByCode( $to );
		$formatted   = $to_currency
			? $to_currency->formatAmount( $converted )
			: number_format( $converted, 2, '.', ',' );

		return ResponseHelper::success( [
			'original_amount'  => $amount,
			'converted_amount' => round( $converted, 6 ),
			'formatted'        => $formatted,
			'from'             => strtoupper( $from ),
			'to'               => strtoupper( $to ),
			'rate'             => $rate,
		] );
	}

	/**
	 * Extract currency fields from the request body.
	 */
	private function extractCurrencyData( WP_REST_Request $request ): array {
		$fields = [
			'code', 'name', 'name_ar', 'symbol', 'symbol_ar',
			'decimal_places', 'exchange_rate', 'is_default', 'is_active', 'sort_order',
		];

		$data = [];
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( $value !== null ) {
				$data[ $field ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Common ID argument definition.
	 */
	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => fn( $value ) => is_numeric( $value ) && $value > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Argument definitions for the exchange rates index endpoint.
	 */
	private function getRatesIndexArgs(): array {
		return [
			'from'  => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'to'    => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'limit' => [
				'type'              => 'integer',
				'default'           => 30,
				'minimum'           => 1,
				'maximum'           => 365,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Argument definitions for the public conversion endpoint.
	 */
	private function getConvertArgs(): array {
		return [
			'amount' => [
				'type'     => 'number',
				'required' => true,
			],
			'from'   => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
			'to'     => [
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}

	/**
	 * Permission callback: require nzl_staff capability.
	 */
	public function checkStaffPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );
	}

	/**
	 * Permission callback: require nzl_admin capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}
}
