<?php

namespace Nozule\Modules\Integrations\Controllers;

use Nozule\Modules\Integrations\Services\IntegrationService;
use Nozule\Modules\Integrations\Services\OdooConnector;
use Nozule\Modules\Integrations\Services\WebhookConnector;

/**
 * REST API controller for integration management.
 */
class IntegrationController {

	private const NAMESPACE = 'nozule/v1';

	private IntegrationService $service;
	private OdooConnector $odoo;
	private WebhookConnector $webhook;

	public function __construct(
		IntegrationService $service,
		OdooConnector $odoo,
		WebhookConnector $webhook
	) {
		$this->service = $service;
		$this->odoo    = $odoo;
		$this->webhook = $webhook;
	}

	public function registerRoutes(): void {
		register_rest_route( self::NAMESPACE, '/admin/integrations/test', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'testConnection' ],
			'permission_callback' => [ $this, 'checkPermission' ],
			'args'                => [
				'provider' => [
					'required' => true,
					'type'     => 'string',
					'enum'     => [ 'odoo', 'webhook' ],
				],
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/integrations/status', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'getStatus' ],
			'permission_callback' => [ $this, 'checkPermission' ],
		] );
	}

	public function checkPermission(): bool {
		return current_user_can( 'nzl_admin' );
	}

	/**
	 * POST /admin/integrations/test
	 */
	public function testConnection( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = $request->get_param( 'provider' );

		switch ( $provider ) {
			case 'odoo':
				$result = $this->odoo->testConnection();
				break;

			case 'webhook':
				$result = $this->webhook->testConnection();
				break;

			default:
				$result = [
					'success' => false,
					'message' => __( 'Unknown provider.', 'nozule' ),
				];
		}

		$status = $result['success'] ? 200 : 422;

		return new \WP_REST_Response( $result, $status );
	}

	/**
	 * GET /admin/integrations/status
	 */
	public function getStatus( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'enabled'  => $this->service->isEnabled(),
			'provider' => $this->service->getProvider(),
		], 200 );
	}
}
