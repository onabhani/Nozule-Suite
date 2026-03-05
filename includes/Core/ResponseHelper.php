<?php

namespace Nozule\Core;

use WP_REST_Response;

/**
 * Standardized REST API response helper.
 *
 * Provides a consistent envelope for all API responses:
 *   { success: bool, data: mixed, message: string, meta: object }
 */
class ResponseHelper {

	/**
	 * Return a successful response (HTTP 200).
	 *
	 * @param mixed       $data    Response payload.
	 * @param string|null $message Optional human-readable message.
	 * @param array|null  $meta    Optional metadata (pagination, totals, etc.).
	 */
	public static function success( mixed $data = null, ?string $message = null, ?array $meta = null ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'message' => $message,
			'meta'    => $meta ?? [],
		], 200 );
	}

	/**
	 * Return a created response (HTTP 201).
	 *
	 * @param mixed       $data    The newly created resource.
	 * @param string|null $message Optional human-readable message.
	 */
	public static function created( mixed $data = null, ?string $message = null ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => $data,
			'message' => $message,
			'meta'    => [],
		], 201 );
	}

	/**
	 * Return a paginated list response (HTTP 200).
	 *
	 * @param array $items   The items for the current page.
	 * @param int   $total   Total number of items across all pages.
	 * @param int   $page    Current page number.
	 * @param int   $perPage Items per page.
	 */
	public static function paginated( array $items, int $total, int $page, int $perPage ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => true,
			'data'    => $items,
			'message' => null,
			'meta'    => [
				'total'    => $total,
				'page'     => $page,
				'per_page' => $perPage,
				'pages'    => $perPage > 0 ? (int) ceil( $total / $perPage ) : 0,
			],
		], 200 );
	}

	/**
	 * Return an error response.
	 *
	 * @param string     $message Human-readable error message.
	 * @param int        $status  HTTP status code (default 400).
	 * @param array|null $errors  Optional field-level errors.
	 */
	public static function error( string $message, int $status = 400, ?array $errors = null ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => false,
			'data'    => null,
			'message' => $message,
			'errors'  => $errors,
		], $status );
	}

	/**
	 * Return a 404 Not Found response.
	 *
	 * @param string|null $message Custom message (defaults to "Resource not found.").
	 */
	public static function notFound( ?string $message = null ): WP_REST_Response {
		return self::error(
			$message ?? __( 'Resource not found.', 'nozule' ),
			404
		);
	}

	/**
	 * Return a 403 Forbidden response.
	 *
	 * @param string|null $message Custom message (defaults to "Forbidden").
	 */
	public static function forbidden( ?string $message = null ): WP_REST_Response {
		return self::error(
			$message ?? __( 'Forbidden', 'nozule' ),
			403
		);
	}
}
