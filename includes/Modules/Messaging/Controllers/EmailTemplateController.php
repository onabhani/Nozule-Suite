<?php

namespace Nozule\Modules\Messaging\Controllers;

use Nozule\Modules\Messaging\Models\EmailLog;
use Nozule\Modules\Messaging\Models\EmailTemplate;
use Nozule\Modules\Messaging\Repositories\EmailLogRepository;
use Nozule\Modules\Messaging\Repositories\EmailTemplateRepository;
use Nozule\Modules\Messaging\Services\EmailService;

/**
 * REST controller for email template and email log management.
 *
 * Route namespace: nozule/v1
 */
class EmailTemplateController {

	private EmailService $service;
	private EmailTemplateRepository $templateRepo;
	private EmailLogRepository $logRepo;

	private const NAMESPACE = 'nozule/v1';

	public function __construct(
		EmailService $service,
		EmailTemplateRepository $templateRepo,
		EmailLogRepository $logRepo
	) {
		$this->service      = $service;
		$this->templateRepo = $templateRepo;
		$this->logRepo      = $logRepo;
	}

	/**
	 * Register REST routes.
	 */
	public function registerRoutes(): void {
		$staff_perm = [ $this, 'checkStaffPermission' ];
		$admin_perm = [ $this, 'checkAdminPermission' ];

		// GET /admin/email-templates -- list templates.
		register_rest_route( self::NAMESPACE, '/admin/email-templates', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'index' ],
			'permission_callback' => $staff_perm,
			'args'                => $this->getListArgs(),
		] );

		// POST /admin/email-templates -- create template.
		register_rest_route( self::NAMESPACE, '/admin/email-templates', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'store' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/email-templates/(?P<id>\d+) -- get single template.
		register_rest_route( self::NAMESPACE, '/admin/email-templates/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'show' ],
			'permission_callback' => $staff_perm,
		] );

		// PUT /admin/email-templates/(?P<id>\d+) -- update template.
		register_rest_route( self::NAMESPACE, '/admin/email-templates/(?P<id>\d+)', [
			'methods'             => 'PUT, PATCH',
			'callback'            => [ $this, 'update' ],
			'permission_callback' => $admin_perm,
		] );

		// DELETE /admin/email-templates/(?P<id>\d+) -- delete template.
		register_rest_route( self::NAMESPACE, '/admin/email-templates/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'destroy' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/email-templates/(?P<id>\d+)/preview -- preview rendered template.
		register_rest_route( self::NAMESPACE, '/admin/email-templates/(?P<id>\d+)/preview', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'preview' ],
			'permission_callback' => $admin_perm,
		] );

		// POST /admin/email-templates/(?P<id>\d+)/test -- send test email.
		register_rest_route( self::NAMESPACE, '/admin/email-templates/(?P<id>\d+)/test', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sendTest' ],
			'permission_callback' => $admin_perm,
		] );

		// GET /admin/email-log -- list sent emails.
		register_rest_route( self::NAMESPACE, '/admin/email-log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'emailLog' ],
			'permission_callback' => $staff_perm,
			'args'                => $this->getLogListArgs(),
		] );
	}

	// ── Permission Callbacks ────────────────────────────────────────

	/**
	 * Permission check: current user has nzl_staff capability.
	 */
	public function checkStaffPermission(): bool {
		return current_user_can( 'nzl_staff' );
	}

	/**
	 * Permission check: current user has manage_options capability.
	 */
	public function checkAdminPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ── Template Endpoints ──────────────────────────────────────────

	/**
	 * GET /admin/email-templates
	 *
	 * List email templates with optional filters and pagination.
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
			'data'    => array_map( fn( EmailTemplate $t ) => $t->toArray(), $result['items'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
		], 200 );
	}

	/**
	 * POST /admin/email-templates
	 *
	 * Create a new email template.
	 */
	public function store( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $this->sanitizeTemplateInput( $request->get_params() );

		if ( empty( $data['name'] ) || empty( $data['slug'] ) || empty( $data['subject'] ) || empty( $data['body'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Name, slug, subject, and body are required.', 'nozule' ),
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
				'message' => __( 'Failed to create email template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $template->toArray(),
		], 201 );
	}

	/**
	 * GET /admin/email-templates/{id}
	 *
	 * Get a single email template.
	 */
	public function show( \WP_REST_Request $request ): \WP_REST_Response {
		$template = $this->templateRepo->find( (int) $request->get_param( 'id' ) );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Email template not found.', 'nozule' ),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $template->toArray(),
		], 200 );
	}

	/**
	 * PUT /admin/email-templates/{id}
	 *
	 * Update an existing email template.
	 */
	public function update( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Email template not found.', 'nozule' ),
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
				'message' => __( 'Failed to update email template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => $this->templateRepo->findOrFail( $id )->toArray(),
		], 200 );
	}

	/**
	 * DELETE /admin/email-templates/{id}
	 *
	 * Delete an email template.
	 */
	public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Email template not found.', 'nozule' ),
			], 404 );
		}

		$deleted = $this->templateRepo->delete( $id );

		if ( ! $deleted ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to delete email template.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => __( 'Email template deleted.', 'nozule' ),
		], 200 );
	}

	/**
	 * POST /admin/email-templates/{id}/preview
	 *
	 * Render a template with sample data and return the result.
	 */
	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Email template not found.', 'nozule' ),
			], 404 );
		}

		// Use provided variables or build sample data.
		$variables = $request->get_param( 'variables' );
		if ( ! is_array( $variables ) || empty( $variables ) ) {
			$variables = $this->getSampleVariables();
		}

		$locale = $variables['locale'] ?? 'en';

		if ( $locale === 'ar' && ! empty( $template->subject_ar ) && ! empty( $template->body_ar ) ) {
			$subject = $template->subject_ar;
			$body    = $template->body_ar;
		} else {
			$subject = $template->subject;
			$body    = $template->body;
		}

		$renderedSubject = $this->service->renderTemplate( $subject, $variables );
		$renderedBody    = $this->service->renderTemplate( $body, $variables );

		return new \WP_REST_Response( [
			'success' => true,
			'data'    => [
				'subject' => $renderedSubject,
				'body'    => $renderedBody,
			],
		], 200 );
	}

	/**
	 * POST /admin/email-templates/{id}/test
	 *
	 * Send a test email to the current admin user.
	 */
	public function sendTest( \WP_REST_Request $request ): \WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$template = $this->templateRepo->find( $id );

		if ( ! $template ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Email template not found.', 'nozule' ),
			], 404 );
		}

		$currentUser = wp_get_current_user();
		$toEmail     = $currentUser->user_email;

		if ( empty( $toEmail ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Your user account does not have an email address.', 'nozule' ),
			], 400 );
		}

		$variables = $this->getSampleVariables();
		$sent      = $this->service->sendTemplate( $template->id, $variables, $toEmail );

		if ( ! $sent ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to send test email.', 'nozule' ),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: recipient email address */
				__( 'Test email sent to %s.', 'nozule' ),
				$toEmail
			),
		], 200 );
	}

	// ── Email Log Endpoint ──────────────────────────────────────────

	/**
	 * GET /admin/email-log
	 *
	 * List sent emails with pagination and filtering.
	 */
	public function emailLog( \WP_REST_Request $request ): \WP_REST_Response {
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
			'data'    => array_map( fn( EmailLog $log ) => $log->toArray(), $result['items'] ),
			'meta'    => [
				'total' => $result['total'],
				'pages' => $result['pages'],
			],
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
		if ( isset( $params['subject'] ) ) {
			$data['subject'] = sanitize_text_field( $params['subject'] );
		}
		if ( array_key_exists( 'subject_ar', $params ) ) {
			$data['subject_ar'] = $params['subject_ar'] !== null
				? sanitize_text_field( $params['subject_ar'] )
				: null;
		}
		if ( isset( $params['body'] ) ) {
			$data['body'] = wp_kses_post( $params['body'] );
		}
		if ( array_key_exists( 'body_ar', $params ) ) {
			$data['body_ar'] = $params['body_ar'] !== null
				? wp_kses_post( $params['body_ar'] )
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
	 * Get sample variables for template preview/testing.
	 *
	 * @return array<string, string>
	 */
	private function getSampleVariables(): array {
		return [
			'guest_name'     => 'John Doe',
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
	 * Argument definitions for the email log list endpoint.
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
