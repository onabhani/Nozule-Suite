<?php

namespace Nozule\Modules\Documents\Controllers;

use Nozule\Modules\Documents\Models\GuestDocument;
use Nozule\Modules\Documents\Services\GuestDocumentService;

/**
 * REST API controller for guest document management.
 *
 * Endpoints:
 *   GET    /admin/guests/{guest_id}/documents   — List documents for a guest
 *   POST   /admin/guests/{guest_id}/documents   — Create document (+ optional file upload)
 *   GET    /admin/documents/{id}                — Show a single document
 *   PUT    /admin/documents/{id}                — Update a document
 *   DELETE /admin/documents/{id}                — Delete a document
 *   PUT    /admin/documents/{id}/verify         — Verify a document
 *   POST   /admin/documents/parse-mrz           — Parse MRZ lines
 */
class GuestDocumentController {

	private const NAMESPACE = 'nozule/v1';

	private GuestDocumentService $service;

	public function __construct( GuestDocumentService $service ) {
		$this->service = $service;
	}

	/**
	 * Register all REST API routes for the documents module.
	 */
	public function registerRoutes(): void {
		// Guest-scoped document endpoints.
		register_rest_route( self::NAMESPACE, '/admin/guests/(?P<guest_id>\d+)/documents', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'guest_id' => [
						'required'          => true,
						'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
						'sanitize_callback' => 'absint',
					],
				],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getStoreArgs(),
			],
		] );

		// Single document endpoints.
		register_rest_route( self::NAMESPACE, '/admin/documents/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getIdArg(),
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => $this->getUpdateArgs(),
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArg(),
			],
		] );

		// Verify endpoint.
		register_rest_route( self::NAMESPACE, '/admin/documents/(?P<id>\d+)/verify', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'verify' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArg(),
			],
		] );

		// MRZ parse endpoint.
		register_rest_route( self::NAMESPACE, '/admin/documents/parse-mrz', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'parseMrz' ],
				'permission_callback' => [ $this, 'checkStaffPermission' ],
				'args'                => [
					'mrz_line1' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'mrz_line2' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	/**
	 * Permission callback: user must be staff or admin.
	 */
	public function checkStaffPermission( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );
	}

	/**
	 * Permission callback: user must be admin or have nzl_admin.
	 */
	public function checkAdminPermission( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	/**
	 * GET /admin/guests/{guest_id}/documents
	 *
	 * List all documents for a specific guest.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$guest_id  = (int) $request->get_param( 'guest_id' );
		$documents = $this->service->getDocuments( $guest_id );

		$items = array_map(
			fn( GuestDocument $doc ) => $doc->toArray(),
			$documents
		);

		return new \WP_REST_Response( [
			'data' => $items,
		], 200 );
	}

	/**
	 * POST /admin/guests/{guest_id}/documents
	 *
	 * Create a new document, optionally with a file upload.
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		$guest_id = (int) $request->get_param( 'guest_id' );
		$data     = $this->extractDocumentData( $request );

		$data['guest_id'] = $guest_id;

		// Handle file upload if present.
		$files = $request->get_file_params();

		if ( ! empty( $files['document_file'] ) ) {
			$upload_result = $this->service->uploadFile( $files['document_file'], $guest_id );

			if ( is_array( $upload_result ) ) {
				// Upload failed — return errors.
				return new \WP_REST_Response( [
					'message' => __( 'File upload failed.', 'nozule' ),
					'errors'  => $upload_result,
				], 422 );
			}

			$data['file_path'] = $upload_result;
			$data['file_type'] = strtolower( pathinfo( $upload_result, PATHINFO_EXTENSION ) );
		}

		$result = $this->service->createDocument( $data );

		if ( $result instanceof GuestDocument ) {
			return new \WP_REST_Response( $result->toArray(), 201 );
		}

		// Validation errors.
		return new \WP_REST_Response( [
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * GET /admin/documents/{id}
	 *
	 * Show a single document.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$document = $this->service->getDocument( $id );

		if ( ! $document ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Document not found.', 'nozule' ) ],
				404
			);
		}

		return new \WP_REST_Response( $document->toArray(), 200 );
	}

	/**
	 * PUT /admin/documents/{id}
	 *
	 * Update an existing document.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $this->extractDocumentData( $request );

		if ( empty( $data ) ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'No data provided for update.', 'nozule' ) ],
				400
			);
		}

		$result = $this->service->updateDocument( $id, $data );

		if ( $result instanceof GuestDocument ) {
			return new \WP_REST_Response( $result->toArray(), 200 );
		}

		// Check if it's a "not found" error.
		if ( isset( $result['general'] ) && str_contains( implode( '', $result['general'] ), 'not found' ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Document not found.', 'nozule' ),
				'errors'  => $result,
			], 404 );
		}

		return new \WP_REST_Response( [
			'message' => __( 'Validation failed.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * DELETE /admin/documents/{id}
	 *
	 * Delete a document and its file.
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$deleted = $this->service->deleteDocument( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response(
				[ 'message' => __( 'Document not found.', 'nozule' ) ],
				404
			);
		}

		return new \WP_REST_Response(
			[ 'message' => __( 'Document deleted successfully.', 'nozule' ) ],
			200
		);
	}

	/**
	 * PUT /admin/documents/{id}/verify
	 *
	 * Mark a document as verified.
	 */
	public function verify( \WP_REST_Request $request ): \WP_REST_Response {
		$id          = (int) $request->get_param( 'id' );
		$verified_by = get_current_user_id();

		$result = $this->service->verifyDocument( $id, $verified_by );

		if ( $result instanceof GuestDocument ) {
			return new \WP_REST_Response( $result->toArray(), 200 );
		}

		// Check for not found.
		if ( isset( $result['general'] ) && str_contains( implode( '', $result['general'] ), 'not found' ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Document not found.', 'nozule' ),
				'errors'  => $result,
			], 404 );
		}

		return new \WP_REST_Response( [
			'message' => __( 'Failed to verify document.', 'nozule' ),
			'errors'  => $result,
		], 422 );
	}

	/**
	 * POST /admin/documents/parse-mrz
	 *
	 * Parse MRZ lines and return extracted data.
	 */
	public function parseMrz( \WP_REST_Request $request ): \WP_REST_Response {
		$mrz_line1 = $request->get_param( 'mrz_line1' );
		$mrz_line2 = $request->get_param( 'mrz_line2' );

		if ( empty( $mrz_line1 ) || empty( $mrz_line2 ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'Both MRZ lines are required.', 'nozule' ),
			], 400 );
		}

		$parsed = $this->service->parseMRZ( $mrz_line1, $mrz_line2 );

		return new \WP_REST_Response( $parsed, 200 );
	}

	/**
	 * Extract document-related fields from the request body.
	 */
	private function extractDocumentData( \WP_REST_Request $request ): array {
		$fields = [
			'document_type', 'document_number',
			'first_name', 'first_name_ar', 'last_name', 'last_name_ar',
			'nationality', 'issuing_country',
			'issue_date', 'expiry_date', 'date_of_birth',
			'gender', 'mrz_line1', 'mrz_line2',
			'file_path', 'file_type', 'thumbnail_path',
			'ocr_data', 'ocr_status',
			'verified', 'verified_by', 'verified_at',
			'notes',
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
	private function getIdArg(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Argument definitions for the store endpoint.
	 */
	private function getStoreArgs(): array {
		return [
			'guest_id'        => [
				'required'          => true,
				'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
				'sanitize_callback' => 'absint',
			],
			'document_type'   => [
				'type'     => 'string',
				'required' => true,
				'enum'     => GuestDocument::ALLOWED_TYPES,
			],
			'document_number' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'first_name'      => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'first_name_ar'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'last_name'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'last_name_ar'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'nationality'     => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'issuing_country' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'issue_date'      => [
				'type'   => 'string',
				'format' => 'date',
			],
			'expiry_date'     => [
				'type'   => 'string',
				'format' => 'date',
			],
			'date_of_birth'   => [
				'type'   => 'string',
				'format' => 'date',
			],
			'gender'          => [
				'type' => 'string',
				'enum' => [ 'male', 'female', 'other' ],
			],
			'mrz_line1'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'mrz_line2'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'notes'           => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			],
		];
	}

	/**
	 * Argument definitions for the update endpoint.
	 */
	private function getUpdateArgs(): array {
		$args = $this->getStoreArgs();

		// On update, no fields are required at the REST level.
		foreach ( $args as &$arg ) {
			unset( $arg['required'] );
		}

		// Remove guest_id — it cannot be changed on update.
		unset( $args['guest_id'] );

		$args['id'] = [
			'required'          => true,
			'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
			'sanitize_callback' => 'absint',
		];

		return $args;
	}
}
