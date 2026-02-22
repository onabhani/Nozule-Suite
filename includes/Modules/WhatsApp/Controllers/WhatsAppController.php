<?php

namespace Nozule\Modules\WhatsApp\Controllers;

use Nozule\Modules\WhatsApp\Models\WhatsAppLog;
use Nozule\Modules\WhatsApp\Models\WhatsAppTemplate;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppLogRepository;
use Nozule\Modules\WhatsApp\Repositories\WhatsAppTemplateRepository;
use Nozule\Modules\WhatsApp\Services\WhatsAppService;

/**
 * REST controller for WhatsApp template, log, and settings management.
 *
 * Route namespace: nozule/v1
 */
class WhatsAppController {

	private WhatsAppService $service;
	private WhatsAppTemplateRepository $templateRepo;
	private WhatsAppLogRepository $logRepo;

	private const NAMESPACE = 'nozule/v1';

	public function __construct(
		WhatsAppService $service,
		WhatsAppTemplateRepository $templateRepo,
		WhatsAppLogRepository $logRepo
	) {
		$this->service      = $service;
		$this->templateRepo = $templateRepo;
		$this->logRepo      = $logRepo;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$admin_perm = [ $this, 'checkAdminPermission' ];

		// ── Templates ─────────────────────────────────────────────

		// GET + POST /admin/whatsapp-templates.
		register_rest_route( self::NAMESPACE, '/admin/whatsapp-templates', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => $admin_perm,
				'args'                => $this->getListArgs(),
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'store' ],
				'permission_callback' => $admin_perm,
			],
		] );

		// GET + PUT/PATCH + DELETE /admin/whatsapp-templates/{id}.
		register_rest_route( self::NAMESPACE, '/admin/whatsapp-templates/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'show' ],
				'permission_callback' => $admin_perm,
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update' ],
				'permission_callback' => $admin_perm,
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'destroy' ],
				'permission_callback' => $admin_perm,
			],
		] );

		// POST /admin/whatsapp-templates/{id}/test -- send test message.
		register_rest_route( self::NAMESPACE, '/admin/whatsapp-templates/(?P<id>\d+)/test', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sendTest' ],
			'permission_callback' => $admin_perm,
		] );

		// ── Log ───────────────────────────────────────────────────

		// GET /admin/whatsapp-log -- list message log.
		register_rest_route( self::NAMESPACE, '/admin/whatsapp-log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'messageLog' ],
			'permission_callback' => $admin_perm,
			'args'                => $this->getLogListArgs(),
		] );

		// ── Settings ──────────────────────────────────────────────

		// GET + POST /admin/whatsapp-settings.
		register_rest_route( self::NAMESPACE, '/admin/whatsapp-settings', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getSettings' ],
				'permission_callback' => $admin_perm,
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'updateSettings' ],
				'permission_callback' => $admin_perm,
			],
		] );
	}

	// ── Permission Callbacks ────────────────────────────────────────

	/**
	 * Permission check: current user has manage_options capability.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ── Template Endpoints ──────────────────────────────────────────

	/**
	 * GET /admin/whatsapp-templates
	 *
	 * List WhatsApp templates with optional filters and pagination.
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->templateRepo->list( [
			'search'        => $request->get_param( 'search' ) ?? '',
			'trigger_event' => $request->get_param( 'trigger_event' ) ?? '',
			'orderby'       => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'         => $request->get_param( 'order' ) ?? 'DESC',
			'per_page'      => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'          => (int) ( $request->get_param( 'page' ) ?? 1 ),
		] );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( WhatsAppTemplate $t ) => $t->toArray(), $result['items'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
		], 200 );
	}

	/**
	 * POST /admin/whatsapp-templates
	 *
	 * Create a new WhatsApp template.
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $this->sanitizeTemplateInput( $request->get_params() );

		if ( empty( $data['name'] ) || empty( $data['slug'] ) || empty( $data['body'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Name, slug, and body are required.', 'nozule' ),
			], 400 );
		}

		// Check for duplicate slug.
		$existing = $this->templateRepo->findBySlug( $data['slug'] );
		if ( $existing ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'A template with this slug already exists.', 'nozule' ),
			], 409 );
		}

		$template = $this->templateRepo->create( $data );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to create WhatsApp template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $template->toArray(),
		], 201 );
	}

	/**
	 * GET /admin/whatsapp-templates/{id}
	 *
	 * Get a single WhatsApp template.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$template = $this->templateRepo->find( (int) $request->get_param( 'id' ) );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'WhatsApp template not found.', 'nozule' ),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $template->toArray(),
		], 200 );
	}

	/**
	 * PUT /admin/whatsapp-templates/{id}
	 *
	 * Update an existing WhatsApp template.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'WhatsApp template not found.', 'nozule' ),
			], 404 );
		}

		$data = $this->sanitizeTemplateInput( $request->get_params() );
		unset( $data['id'], $data['created_at'] );

		// If slug changed, check for duplicates.
		if ( isset( $data['slug'] ) && $data['slug'] !== $template->slug ) {
			$existing = $this->templateRepo->findBySlug( $data['slug'] );
			if ( $existing ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'A template with this slug already exists.', 'nozule' ),
				], 409 );
			}
		}

		$success = $this->templateRepo->update( $id, $data );

		if ( ! $success ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to update WhatsApp template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $this->templateRepo->findOrFail( $id )->toArray(),
		], 200 );
	}

	/**
	 * DELETE /admin/whatsapp-templates/{id}
	 *
	 * Delete a WhatsApp template.
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'WhatsApp template not found.', 'nozule' ),
			], 404 );
		}

		$deleted = $this->templateRepo->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to delete WhatsApp template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'WhatsApp template deleted.', 'nozule' ),
		], 200 );
	}

	/**
	 * POST /admin/whatsapp-templates/{id}/test
	 *
	 * Send a test WhatsApp message using sample data.
	 */
	public function sendTest( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'WhatsApp template not found.', 'nozule' ),
			], 404 );
		}

		// Get the test phone number from request or from WhatsApp settings.
		$toPhone = $request->get_param( 'phone' );
		if ( empty( $toPhone ) ) {
			$waSettings = $this->service->getSettings();
			$toPhone    = $waSettings['phone_number_id'] ?? '';
		}

		if ( empty( $toPhone ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No phone number provided for test. Please provide a phone number.', 'nozule' ),
			], 400 );
		}

		$variables = $this->getSampleVariables();
		$sent      = $this->service->sendTemplate( $template->id, $variables, $toPhone );

		if ( ! $sent ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to send test WhatsApp message.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: recipient phone number */
				__( 'Test WhatsApp message sent to %s.', 'nozule' ),
				$toPhone
			),
		], 200 );
	}

	// ── Message Log Endpoint ────────────────────────────────────────

	/**
	 * GET /admin/whatsapp-log
	 *
	 * List WhatsApp messages with pagination and filtering.
	 */
	public function messageLog( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->logRepo->list( [
			'status'   => $request->get_param( 'status' ) ?? '',
			'search'   => $request->get_param( 'search' ) ?? '',
			'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
			'order'    => $request->get_param( 'order' ) ?? 'DESC',
			'per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
			'page'     => (int) ( $request->get_param( 'page' ) ?? 1 ),
		] );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => array_map( fn( WhatsAppLog $log ) => $log->toArray(), $result['items'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
		], 200 );
	}

	// ── Settings Endpoints ──────────────────────────────────────────

	/**
	 * GET /admin/whatsapp-settings
	 *
	 * Get current WhatsApp API settings.
	 */
	public function getSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $this->service->getSettings();

		// Mask the access token for security.
		if ( ! empty( $settings['access_token'] ) ) {
			$token = $settings['access_token'];
			if ( strlen( $token ) > 8 ) {
				$settings['access_token_masked'] = substr( $token, 0, 4 ) . str_repeat( '*', strlen( $token ) - 8 ) . substr( $token, -4 );
			} else {
				$settings['access_token_masked'] = '****';
			}
		} else {
			$settings['access_token_masked'] = '';
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $settings,
		], 200 );
	}

	/**
	 * POST /admin/whatsapp-settings
	 *
	 * Update WhatsApp API settings.
	 */
	public function updateSettings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_params();
		$data   = [];

		$allowedKeys = [ 'phone_number_id', 'access_token', 'business_id', 'enabled', 'api_version' ];
		foreach ( $allowedKeys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$data[ $key ] = $params[ $key ];
			}
		}

		if ( empty( $data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No valid settings provided.', 'nozule' ),
			], 400 );
		}

		$this->service->updateSettings( $data );

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'WhatsApp settings updated.', 'nozule' ),
		], 200 );
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Sanitize incoming template fields.
	 */
	private function sanitizeTemplateInput( array $params ): array {
		$data = [];

		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( $params['name'] );
		}
		if ( isset( $params['slug'] ) ) {
			$data['slug'] = sanitize_title( $params['slug'] );
		}
		if ( array_key_exists( 'trigger_event', $params ) ) {
			$data['trigger_event'] = $params['trigger_event'] !== null
				? sanitize_text_field( $params['trigger_event'] )
				: null;
		}
		if ( isset( $params['body'] ) ) {
			$data['body'] = sanitize_textarea_field( $params['body'] );
		}
		if ( array_key_exists( 'body_ar', $params ) ) {
			$data['body_ar'] = $params['body_ar'] !== null
				? sanitize_textarea_field( $params['body_ar'] )
				: null;
		}
		if ( isset( $params['variables'] ) ) {
			$data['variables'] = is_array( $params['variables'] ) ? $params['variables'] : [];
		}
		if ( isset( $params['is_active'] ) ) {
			$data['is_active'] = (int) (bool) $params['is_active'];
		}

		return $data;
	}

	/**
	 * Get sample variables for template testing.
	 *
	 * @return array<string, string>
	 */
	private function getSampleVariables(): array {
		return [
			'guest_name'     => 'John Doe',
			'guest_phone'    => '+966501234567',
			'guest_email'    => 'john.doe@example.com',
			'booking_number' => 'NZL-2025-00001',
			'check_in'       => wp_date( 'Y-m-d', strtotime( '+7 days' ) ),
			'check_out'      => wp_date( 'Y-m-d', strtotime( '+10 days' ) ),
			'room_type'      => 'Deluxe Suite',
			'room_number'    => '301',
			'total_amount'   => '1,500.00',
			'currency'       => 'SAR',
			'hotel_name'     => get_bloginfo( 'name' ),
			'hotel_phone'    => '+966 50 000 0000',
			'hotel_email'    => get_option( 'admin_email' ),
		];
	}

	/**
	 * Argument definitions for the template list endpoint.
	 */
	private function getListArgs(): array {
		return [
			'search' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'trigger_event' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'orderby' => [
				'type'              => 'string',
				'default'           => 'created_at',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order' => [
				'type'              => 'string',
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
			'page' => [
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Argument definitions for the message log list endpoint.
	 */
	private function getLogListArgs(): array {
		return [
			'status' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'orderby' => [
				'type'              => 'string',
				'default'           => 'created_at',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order' => [
				'type'              => 'string',
				'default'           => 'DESC',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
			],
			'page' => [
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
