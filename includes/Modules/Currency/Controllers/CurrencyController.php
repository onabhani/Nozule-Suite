<?php

namespace Nozule\Modules\Currency\Controllers;

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

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Create a new currency.
	 */
	public function store( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->extractCurrencyData( $request );

		$result = $this->service->createCurrency( $data );

		if ( $result instanceof Currency ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Currency created successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Get a single currency.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$currency = $this->service->getCurrency( $id );

		if ( ! $currency ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Currency not found.', 'nozule' ),
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $currency->toArray(),
		], 200 );
	}

	/**
	 * Update an existing currency.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractCurrencyData( $request );

		$result = $this->service->updateCurrency( $id, $data );

		if ( $result instanceof Currency ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Currency updated successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 200 );
		}

		if ( isset( $result['id'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $result['id'][0],
				'errors'  => $result,
			], 404 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Delete a currency.
	 */
	public function destroy( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->deleteCurrency( $id );

		if ( $result === true ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Currency deleted successfully.', 'nozule' ),
			], 200 );
		}

		$status_code = isset( $result['id'] ) ? 404 : 422;

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to delete currency.', 'nozule' ),
			'errors'  => $result,
		], $status_code );
	}

	/**
	 * Set a currency as the default.
	 */
	public function setDefault( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->setDefaultCurrency( $id );

		if ( $result ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Default currency updated successfully.', 'nozule' ),
			], 200 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to set default currency.', 'nozule' ),
		], 404 );
	}

	/**
	 * List exchange rate history for a currency pair.
	 */
	public function ratesIndex( WP_REST_Request $request ): WP_REST_Response {
		$from  = sanitize_text_field( $request->get_param( 'from' ) ?? '' );
		$to    = sanitize_text_field( $request->get_param( 'to' ) ?? '' );
		$limit = absint( $request->get_param( 'limit' ) ?? 30 );

		if ( empty( $from ) || empty( $to ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Both "from" and "to" currency codes are required.', 'nozule' ),
			], 400 );
		}

		$history = $this->service->getExchangeHistory( $from, $to, $limit );

		$data = array_map(
			fn( ExchangeRate $rate ) => $rate->toArray(),
			$history
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'total'   => count( $data ),
		], 200 );
	}

	/**
	 * Create a new exchange rate record.
	 */
	public function ratesStore( WP_REST_Request $request ): WP_REST_Response {
		$from = sanitize_text_field( $request->get_param( 'from_currency' ) ?? '' );
		$to   = sanitize_text_field( $request->get_param( 'to_currency' ) ?? '' );
		$rate = (float) ( $request->get_param( 'rate' ) ?? 0 );

		if ( empty( $from ) || empty( $to ) || $rate <= 0 ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'from_currency, to_currency, and a positive rate are required.', 'nozule' ),
			], 400 );
		}

		$result = $this->service->updateExchangeRate( $from, $to, $rate );

		if ( $result instanceof ExchangeRate ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Exchange rate saved successfully.', 'nozule' ),
				'data'    => $result->toArray(),
			], 201 );
		}

		return new WP_REST_Response( [
			'success' => false,
			'message' => __( 'Failed to save exchange rate.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * Convert an amount between currencies (public endpoint).
	 */
	public function convert( WP_REST_Request $request ): WP_REST_Response {
		$amount = (float) ( $request->get_param( 'amount' ) ?? 0 );
		$from   = sanitize_text_field( $request->get_param( 'from' ) ?? '' );
		$to     = sanitize_text_field( $request->get_param( 'to' ) ?? '' );

		if ( empty( $from ) || empty( $to ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Both "from" and "to" currency codes are required.', 'nozule' ),
			], 400 );
		}

		$converted = $this->service->convert( $amount, $from, $to );
		$rate      = $this->service->getExchangeRate( $from, $to );

		// Attempt to format the result using the target currency.
		$to_currency = $this->service->getCurrencyByCode( $to );
		$formatted   = $to_currency
			? $to_currency->formatAmount( $converted )
			: number_format( $converted, 2, '.', ',' );

		return new WP_REST_Response( [
			'success'   => true,
			'data'      => [
				'original_amount'  => $amount,
				'converted_amount' => round( $converted, 6 ),
				'formatted'        => $formatted,
				'from'             => strtoupper( $from ),
				'to'               => strtoupper( $to ),
				'rate'             => $rate,
			],
		], 200 );
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
