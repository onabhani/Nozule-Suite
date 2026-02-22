<?php

namespace Nozule\Modules\Loyalty\Controllers;

use Nozule\Modules\Loyalty\Models\LoyaltyReward;
use Nozule\Modules\Loyalty\Models\LoyaltyTier;
use Nozule\Modules\Loyalty\Models\LoyaltyTransaction;
use Nozule\Modules\Loyalty\Repositories\LoyaltyRepository;
use Nozule\Modules\Loyalty\Services\LoyaltyService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for loyalty program administration.
 *
 * Routes:
 *   GET    /nozule/v1/admin/loyalty/members              List members
 *   GET    /nozule/v1/admin/loyalty/members/{id}         Get member detail + transactions
 *   POST   /nozule/v1/admin/loyalty/members              Enroll a guest
 *   POST   /nozule/v1/admin/loyalty/members/{id}/adjust  Adjust points manually
 *   POST   /nozule/v1/admin/loyalty/members/{id}/redeem/{rewardId}  Redeem reward
 *   GET    /nozule/v1/admin/loyalty/tiers                List tiers
 *   POST   /nozule/v1/admin/loyalty/tiers                Create/update tier
 *   DELETE /nozule/v1/admin/loyalty/tiers/{id}           Delete tier
 *   GET    /nozule/v1/admin/loyalty/rewards              List rewards
 *   POST   /nozule/v1/admin/loyalty/rewards              Create/update reward
 *   DELETE /nozule/v1/admin/loyalty/rewards/{id}         Delete reward
 *   GET    /nozule/v1/admin/loyalty/stats                Dashboard stats
 */
class LoyaltyController {

	private const NAMESPACE = 'nozule/v1';

	private LoyaltyService $service;
	private LoyaltyRepository $repository;

	public function __construct( LoyaltyService $service, LoyaltyRepository $repository ) {
		$this->service    = $service;
		$this->repository = $repository;
	}

	/**
	 * Register REST API routes.
	 */
	public function registerRoutes(): void {
		// ----- MEMBERS -----
		register_rest_route( self::NAMESPACE, '/admin/loyalty/members', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listMembers' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getMemberListArgs(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'enrollMember' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/loyalty/members/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getMember' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/loyalty/members/(?P<id>\d+)/adjust', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'adjustPoints' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/loyalty/members/(?P<id>\d+)/redeem/(?P<rewardId>\d+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'redeemReward' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		// ----- TIERS -----
		register_rest_route( self::NAMESPACE, '/admin/loyalty/tiers', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listTiers' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveTier' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/loyalty/tiers/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'deleteTier' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// ----- REWARDS -----
		register_rest_route( self::NAMESPACE, '/admin/loyalty/rewards', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'listRewards' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'saveReward' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/admin/loyalty/rewards/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'deleteReward' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
				'args'                => $this->getIdArgs(),
			],
		] );

		// ----- STATS -----
		register_rest_route( self::NAMESPACE, '/admin/loyalty/stats', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'getStats' ],
				'permission_callback' => [ $this, 'checkAdminPermission' ],
			],
		] );
	}

	// ==================================================================
	// MEMBERS
	// ==================================================================

	/**
	 * List loyalty members with search, tier filter, and pagination.
	 */
	public function listMembers( WP_REST_Request $request ): WP_REST_Response {
		$filters = [
			'search'   => $request->get_param( 'search' ) ?? '',
			'tier_id'  => $request->get_param( 'tier_id' ) ? (int) $request->get_param( 'tier_id' ) : 0,
			'per_page' => $request->get_param( 'per_page' ) ?? 20,
		];

		$page   = (int) ( $request->get_param( 'page' ) ?? 1 );
		$result = $this->repository->getMembers( $filters, $page );

		return new WP_REST_Response( [
			'success' => true,
			'data'    => [
				'items'      => $result['members'],
				'pagination' => [
					'page'        => $page,
					'per_page'    => (int) ( $filters['per_page'] ),
					'total'       => $result['total'],
					'total_pages' => $result['pages'],
				],
			],
		], 200 );
	}

	/**
	 * Get a single member with transaction history.
	 */
	public function getMember( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$member = $this->repository->getMember( $id );

		if ( ! $member ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Loyalty member not found.', 'nozule' ),
			], 404 );
		}

		$transactions = $this->repository->getTransactions( $id, 1, 50 );
		$member['transactions'] = array_map(
			function ( LoyaltyTransaction $tx ) {
				return $tx->toArray();
			},
			$transactions
		);

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $member,
		], 200 );
	}

	/**
	 * Enroll a guest as a loyalty member.
	 */
	public function enrollMember( WP_REST_Request $request ): WP_REST_Response {
		$guestId = (int) $request->get_param( 'guest_id' );

		if ( ! $guestId ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Guest ID is required.', 'nozule' ),
			], 400 );
		}

		try {
			$member = $this->service->enrollGuest( $guestId );

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Guest enrolled successfully.', 'nozule' ),
				'data'    => $member->toArray(),
			], 201 );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Adjust points for a member.
	 */
	public function adjustPoints( WP_REST_Request $request ): WP_REST_Response {
		$memberId    = (int) $request->get_param( 'id' );
		$points      = (int) $request->get_param( 'points' );
		$description = sanitize_text_field( $request->get_param( 'description' ) ?? '' );

		if ( ! $points ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Points value is required and cannot be zero.', 'nozule' ),
			], 400 );
		}

		if ( empty( $description ) ) {
			$description = __( 'Manual adjustment', 'nozule' );
		}

		try {
			$transaction = $this->service->adjustPoints( $memberId, $points, $description );

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'Points adjusted successfully.', 'nozule' ),
				'data'    => $transaction->toArray(),
			], 200 );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Redeem a reward for a member.
	 */
	public function redeemReward( WP_REST_Request $request ): WP_REST_Response {
		$memberId = (int) $request->get_param( 'id' );
		$rewardId = (int) $request->get_param( 'rewardId' );

		try {
			$result = $this->service->redeemReward( $memberId, $rewardId );

			return new WP_REST_Response( [
				'success' => true,
				'message' => sprintf(
					/* translators: %s: Reward name */
					__( 'Reward "%s" redeemed successfully.', 'nozule' ),
					$result['reward']->name
				),
				'data' => [
					'transaction' => $result['transaction']->toArray(),
					'reward'      => $result['reward']->toArray(),
				],
			], 200 );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			], 400 );
		}
	}

	// ==================================================================
	// TIERS
	// ==================================================================

	/**
	 * List all loyalty tiers.
	 */
	public function listTiers( WP_REST_Request $request ): WP_REST_Response {
		$tiers = $this->repository->getTiers();

		return new WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				function ( LoyaltyTier $tier ) {
					return $tier->toArray();
				},
				$tiers
			),
		], 200 );
	}

	/**
	 * Create or update a tier.
	 */
	public function saveTier( WP_REST_Request $request ): WP_REST_Response {
		$data = [
			'name'             => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'name_ar'          => sanitize_text_field( $request->get_param( 'name_ar' ) ?? '' ),
			'min_points'       => (int) ( $request->get_param( 'min_points' ) ?? 0 ),
			'discount_percent' => (float) ( $request->get_param( 'discount_percent' ) ?? 0 ),
			'benefits'         => sanitize_textarea_field( $request->get_param( 'benefits' ) ?? '' ),
			'benefits_ar'      => sanitize_textarea_field( $request->get_param( 'benefits_ar' ) ?? '' ),
			'color'            => sanitize_hex_color( $request->get_param( 'color' ) ?? '#CD7F32' ) ?: '#CD7F32',
			'sort_order'       => (int) ( $request->get_param( 'sort_order' ) ?? 0 ),
		];

		if ( empty( $data['name'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Tier name is required.', 'nozule' ),
			], 400 );
		}

		$id = $request->get_param( 'id' );
		if ( $id ) {
			$data['id'] = (int) $id;
		}

		$tier = $this->repository->saveTier( $data );

		if ( ! $tier ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to save tier.', 'nozule' ),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => $id
				? __( 'Tier updated successfully.', 'nozule' )
				: __( 'Tier created successfully.', 'nozule' ),
			'data'    => $tier->toArray(),
		], $id ? 200 : 201 );
	}

	/**
	 * Delete a tier.
	 */
	public function deleteTier( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		// Check if any members are in this tier.
		$memberCount = $this->repository->getCount( [ 'tier_id' => $id ] );
		if ( $memberCount > 0 ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: Number of members */
					__( 'Cannot delete tier: %d members are currently assigned to it.', 'nozule' ),
					$memberCount
				),
			], 400 );
		}

		$result = $this->repository->deleteTier( $id );

		if ( ! $result ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to delete tier.', 'nozule' ),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Tier deleted successfully.', 'nozule' ),
		], 200 );
	}

	// ==================================================================
	// REWARDS
	// ==================================================================

	/**
	 * List all rewards.
	 */
	public function listRewards( WP_REST_Request $request ): WP_REST_Response {
		$rewards = $this->repository->getRewards();

		return new WP_REST_Response( [
			'success' => true,
			'data'    => array_map(
				function ( LoyaltyReward $reward ) {
					return $reward->toArray();
				},
				$rewards
			),
		], 200 );
	}

	/**
	 * Create or update a reward.
	 */
	public function saveReward( WP_REST_Request $request ): WP_REST_Response {
		$data = [
			'name'           => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'name_ar'        => sanitize_text_field( $request->get_param( 'name_ar' ) ?? '' ),
			'points_cost'    => (int) ( $request->get_param( 'points_cost' ) ?? 0 ),
			'type'           => sanitize_text_field( $request->get_param( 'type' ) ?? 'discount' ),
			'value'          => sanitize_text_field( $request->get_param( 'value' ) ?? '' ),
			'description'    => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
			'description_ar' => sanitize_textarea_field( $request->get_param( 'description_ar' ) ?? '' ),
			'is_active'      => $request->get_param( 'is_active' ) !== null
				? ( $request->get_param( 'is_active' ) ? 1 : 0 )
				: 1,
		];

		if ( empty( $data['name'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Reward name is required.', 'nozule' ),
			], 400 );
		}

		if ( $data['points_cost'] <= 0 ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Points cost must be greater than zero.', 'nozule' ),
			], 400 );
		}

		if ( ! in_array( $data['type'], LoyaltyReward::validTypes(), true ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Invalid reward type.', 'nozule' ),
			], 400 );
		}

		$id = $request->get_param( 'id' );
		if ( $id ) {
			$data['id'] = (int) $id;
		}

		$reward = $this->repository->saveReward( $data );

		if ( ! $reward ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to save reward.', 'nozule' ),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => $id
				? __( 'Reward updated successfully.', 'nozule' )
				: __( 'Reward created successfully.', 'nozule' ),
			'data'    => $reward->toArray(),
		], $id ? 200 : 201 );
	}

	/**
	 * Delete a reward.
	 */
	public function deleteReward( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->repository->deleteReward( $id );

		if ( ! $result ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Failed to delete reward.', 'nozule' ),
			], 500 );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => __( 'Reward deleted successfully.', 'nozule' ),
		], 200 );
	}

	// ==================================================================
	// STATS
	// ==================================================================

	/**
	 * Get loyalty dashboard statistics.
	 */
	public function getStats( WP_REST_Request $request ): WP_REST_Response {
		$stats = $this->repository->getStats();

		return new WP_REST_Response( [
			'success' => true,
			'data'    => $stats,
		], 200 );
	}

	// ==================================================================
	// PERMISSIONS
	// ==================================================================

	/**
	 * Permission callback: require manage_options capability.
	 */
	public function checkAdminPermission( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'nzl_admin' );
	}

	// ==================================================================
	// ARG DEFINITIONS
	// ==================================================================

	/**
	 * Common ID argument definition.
	 */
	private function getIdArgs(): array {
		return [
			'id' => [
				'required'          => true,
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Argument definitions for the member list endpoint.
	 */
	private function getMemberListArgs(): array {
		return [
			'search'   => [
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'tier_id'  => [
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
