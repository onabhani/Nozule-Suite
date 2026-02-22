<?php

namespace Nozule\Modules\Loyalty\Repositories;

use Nozule\Core\Database;
use Nozule\Modules\Loyalty\Models\LoyaltyMember;
use Nozule\Modules\Loyalty\Models\LoyaltyReward;
use Nozule\Modules\Loyalty\Models\LoyaltyTier;
use Nozule\Modules\Loyalty\Models\LoyaltyTransaction;

/**
 * Repository for loyalty program database operations.
 *
 * Operates on four tables: loyalty_tiers, loyalty_members,
 * loyalty_transactions, loyalty_rewards.
 */
class LoyaltyRepository {

	private Database $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	// ------------------------------------------------------------------
	// Table name helpers
	// ------------------------------------------------------------------

	private function tiersTable(): string {
		return $this->db->table( 'loyalty_tiers' );
	}

	private function membersTable(): string {
		return $this->db->table( 'loyalty_members' );
	}

	private function transactionsTable(): string {
		return $this->db->table( 'loyalty_transactions' );
	}

	private function rewardsTable(): string {
		return $this->db->table( 'loyalty_rewards' );
	}

	private function guestsTable(): string {
		return $this->db->table( 'guests' );
	}

	// ==================================================================
	// TIERS
	// ==================================================================

	/**
	 * Get all tiers ordered by min_points ascending.
	 *
	 * @return LoyaltyTier[]
	 */
	public function getTiers(): array {
		$rows = $this->db->getResults(
			"SELECT * FROM {$this->tiersTable()} ORDER BY min_points ASC"
		);

		return LoyaltyTier::fromRows( $rows );
	}

	/**
	 * Get a single tier by ID.
	 */
	public function getTier( int $id ): ?LoyaltyTier {
		$row = $this->db->getRow(
			"SELECT * FROM {$this->tiersTable()} WHERE id = %d",
			$id
		);

		return $row ? LoyaltyTier::fromRow( $row ) : null;
	}

	/**
	 * Get the lowest tier (least min_points).
	 */
	public function getLowestTier(): ?LoyaltyTier {
		$row = $this->db->getRow(
			"SELECT * FROM {$this->tiersTable()} ORDER BY min_points ASC LIMIT 1"
		);

		return $row ? LoyaltyTier::fromRow( $row ) : null;
	}

	/**
	 * Get the tier that a member qualifies for based on lifetime points.
	 */
	public function getTierForPoints( int $lifetimePoints ): ?LoyaltyTier {
		$row = $this->db->getRow(
			"SELECT * FROM {$this->tiersTable()} WHERE min_points <= %d ORDER BY min_points DESC LIMIT 1",
			$lifetimePoints
		);

		return $row ? LoyaltyTier::fromRow( $row ) : null;
	}

	/**
	 * Create or update a tier.
	 *
	 * @return LoyaltyTier|false
	 */
	public function saveTier( array $data ) {
		$now = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;

			$result = $this->db->update( 'loyalty_tiers', $data, [ 'id' => $id ] );
			if ( $result === false ) {
				return false;
			}

			return $this->getTier( $id );
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$id = $this->db->insert( 'loyalty_tiers', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getTier( $id );
	}

	/**
	 * Delete a tier.
	 */
	public function deleteTier( int $id ): bool {
		return $this->db->delete( 'loyalty_tiers', [ 'id' => $id ] ) !== false;
	}

	// ==================================================================
	// MEMBERS
	// ==================================================================

	/**
	 * List members with search, tier filter, and pagination.
	 *
	 * @param array $filters {
	 *     @type string $search   Search by guest name or email.
	 *     @type int    $tier_id  Filter by tier ID.
	 *     @type int    $per_page Results per page. Default 20.
	 *     @type int    $page     Page number (1-based). Default 1.
	 * }
	 * @return array{ members: array[], total: int, pages: int }
	 */
	public function getMembers( array $filters = [], int $page = 1 ): array {
		$per_page = (int) ( $filters['per_page'] ?? 20 );
		$offset   = ( $page - 1 ) * $per_page;
		$where    = [];
		$params   = [];

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->db->wpdb()->esc_like( $filters['search'] ) . '%';
			$where[]  = '(g.first_name LIKE %s OR g.last_name LIKE %s OR g.email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $filters['tier_id'] ) ) {
			$where[]  = 'm.tier_id = %d';
			$params[] = (int) $filters['tier_id'];
		}

		$where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_params = $params;
		$total = (int) $this->db->getVar(
			"SELECT COUNT(*)
			 FROM {$this->membersTable()} m
			 INNER JOIN {$this->guestsTable()} g ON g.id = m.guest_id
			 {$where_clause}",
			...$count_params
		);

		$params[] = $per_page;
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT m.*,
			        g.first_name, g.last_name, g.email, g.phone,
			        t.name AS tier_name, t.name_ar AS tier_name_ar, t.color AS tier_color
			 FROM {$this->membersTable()} m
			 INNER JOIN {$this->guestsTable()} g ON g.id = m.guest_id
			 LEFT JOIN {$this->tiersTable()} t ON t.id = m.tier_id
			 {$where_clause}
			 ORDER BY m.joined_at DESC
			 LIMIT %d OFFSET %d",
			...$params
		);

		// Convert rows to arrays with joined data.
		$members = array_map( function ( $row ) {
			$data = (array) $row;
			$data['id']              = (int) $data['id'];
			$data['guest_id']        = (int) $data['guest_id'];
			$data['tier_id']         = (int) $data['tier_id'];
			$data['points_balance']  = (int) $data['points_balance'];
			$data['lifetime_points'] = (int) $data['lifetime_points'];
			return $data;
		}, $rows );

		return [
			'members' => $members,
			'total'   => $total,
			'pages'   => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	/**
	 * Get a single member by ID with guest and tier data.
	 */
	public function getMember( int $id ): ?array {
		$row = $this->db->getRow(
			"SELECT m.*,
			        g.first_name, g.last_name, g.email, g.phone,
			        t.name AS tier_name, t.name_ar AS tier_name_ar, t.color AS tier_color
			 FROM {$this->membersTable()} m
			 INNER JOIN {$this->guestsTable()} g ON g.id = m.guest_id
			 LEFT JOIN {$this->tiersTable()} t ON t.id = m.tier_id
			 WHERE m.id = %d",
			$id
		);

		if ( ! $row ) {
			return null;
		}

		$data = (array) $row;
		$data['id']              = (int) $data['id'];
		$data['guest_id']        = (int) $data['guest_id'];
		$data['tier_id']         = (int) $data['tier_id'];
		$data['points_balance']  = (int) $data['points_balance'];
		$data['lifetime_points'] = (int) $data['lifetime_points'];

		return $data;
	}

	/**
	 * Get a member by guest ID.
	 */
	public function getMemberByGuest( int $guestId ): ?LoyaltyMember {
		$row = $this->db->getRow(
			"SELECT * FROM {$this->membersTable()} WHERE guest_id = %d LIMIT 1",
			$guestId
		);

		return $row ? LoyaltyMember::fromRow( $row ) : null;
	}

	/**
	 * Create or update a member.
	 *
	 * @return LoyaltyMember|false
	 */
	public function saveMember( array $data ) {
		$now = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;

			$result = $this->db->update( 'loyalty_members', $data, [ 'id' => $id ] );
			if ( $result === false ) {
				return false;
			}

			$row = $this->db->getRow(
				"SELECT * FROM {$this->membersTable()} WHERE id = %d",
				$id
			);

			return $row ? LoyaltyMember::fromRow( $row ) : false;
		}

		$data['joined_at'] = $data['joined_at'] ?? $now;
		$data['updated_at']  = $now;

		$id = $this->db->insert( 'loyalty_members', $data );
		if ( $id === false ) {
			return false;
		}

		$row = $this->db->getRow(
			"SELECT * FROM {$this->membersTable()} WHERE id = %d",
			$id
		);

		return $row ? LoyaltyMember::fromRow( $row ) : false;
	}

	/**
	 * Get the total count of members matching optional filters.
	 */
	public function getCount( array $filters = [] ): int {
		$where  = [];
		$params = [];

		if ( ! empty( $filters['tier_id'] ) ) {
			$where[]  = 'tier_id = %d';
			$params[] = (int) $filters['tier_id'];
		}

		$where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		return (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$this->membersTable()} {$where_clause}",
			...$params
		);
	}

	/**
	 * Update member points balance and lifetime points after a transaction.
	 */
	public function updateMemberPoints( int $memberId, int $pointsBalance, int $lifetimePoints ): bool {
		$result = $this->db->update(
			'loyalty_members',
			[
				'points_balance'  => $pointsBalance,
				'lifetime_points' => $lifetimePoints,
				'updated_at'      => current_time( 'mysql' ),
			],
			[ 'id' => $memberId ]
		);

		return $result !== false;
	}

	/**
	 * Update a member's tier.
	 */
	public function updateMemberTier( int $memberId, int $tierId ): bool {
		$result = $this->db->update(
			'loyalty_members',
			[
				'tier_id'    => $tierId,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $memberId ]
		);

		return $result !== false;
	}

	// ==================================================================
	// TRANSACTIONS
	// ==================================================================

	/**
	 * Get transactions for a member, newest first.
	 *
	 * @return LoyaltyTransaction[]
	 */
	public function getTransactions( int $memberId, int $page = 1, int $perPage = 20 ): array {
		$offset = ( $page - 1 ) * $perPage;

		$rows = $this->db->getResults(
			"SELECT * FROM {$this->transactionsTable()}
			 WHERE member_id = %d
			 ORDER BY created_at DESC
			 LIMIT %d OFFSET %d",
			$memberId,
			$perPage,
			$offset
		);

		return LoyaltyTransaction::fromRows( $rows );
	}

	/**
	 * Add a transaction record.
	 *
	 * @return LoyaltyTransaction|false
	 */
	public function addTransaction( array $data ) {
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );

		$id = $this->db->insert( 'loyalty_transactions', $data );
		if ( $id === false ) {
			return false;
		}

		$row = $this->db->getRow(
			"SELECT * FROM {$this->transactionsTable()} WHERE id = %d",
			$id
		);

		return $row ? LoyaltyTransaction::fromRow( $row ) : false;
	}

	/**
	 * Get a member's current points balance from the members table.
	 */
	public function getMemberPointsBalance( int $memberId ): int {
		return (int) $this->db->getVar(
			"SELECT points_balance FROM {$this->membersTable()} WHERE id = %d",
			$memberId
		);
	}

	// ==================================================================
	// REWARDS
	// ==================================================================

	/**
	 * Get all rewards.
	 *
	 * @return LoyaltyReward[]
	 */
	public function getRewards(): array {
		$rows = $this->db->getResults(
			"SELECT * FROM {$this->rewardsTable()} ORDER BY points_cost ASC"
		);

		return LoyaltyReward::fromRows( $rows );
	}

	/**
	 * Get a single reward by ID.
	 */
	public function getReward( int $id ): ?LoyaltyReward {
		$row = $this->db->getRow(
			"SELECT * FROM {$this->rewardsTable()} WHERE id = %d",
			$id
		);

		return $row ? LoyaltyReward::fromRow( $row ) : null;
	}

	/**
	 * Create or update a reward.
	 *
	 * @return LoyaltyReward|false
	 */
	public function saveReward( array $data ) {
		$now = current_time( 'mysql' );

		if ( ! empty( $data['id'] ) ) {
			$id = (int) $data['id'];
			unset( $data['id'] );
			$data['updated_at'] = $now;

			$result = $this->db->update( 'loyalty_rewards', $data, [ 'id' => $id ] );
			if ( $result === false ) {
				return false;
			}

			return $this->getReward( $id );
		}

		$data['created_at'] = $now;
		$data['updated_at'] = $now;

		$id = $this->db->insert( 'loyalty_rewards', $data );
		if ( $id === false ) {
			return false;
		}

		return $this->getReward( $id );
	}

	/**
	 * Delete a reward.
	 */
	public function deleteReward( int $id ): bool {
		return $this->db->delete( 'loyalty_rewards', [ 'id' => $id ] ) !== false;
	}

	// ==================================================================
	// STATS
	// ==================================================================

	/**
	 * Get dashboard statistics.
	 *
	 * @return array{
	 *     total_members: int,
	 *     points_issued: int,
	 *     rewards_redeemed: int,
	 *     active_this_month: int
	 * }
	 */
	public function getStats(): array {
		$total_members = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$this->membersTable()}"
		);

		$points_issued = (int) $this->db->getVar(
			"SELECT COALESCE(SUM(points), 0) FROM {$this->transactionsTable()} WHERE type = 'earn'"
		);

		$rewards_redeemed = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$this->transactionsTable()} WHERE type = 'redeem'"
		);

		$month_start = gmdate( 'Y-m-01' );
		$active_this_month = (int) $this->db->getVar(
			"SELECT COUNT(DISTINCT member_id) FROM {$this->transactionsTable()} WHERE created_at >= %s",
			$month_start
		);

		return [
			'total_members'     => $total_members,
			'points_issued'     => $points_issued,
			'rewards_redeemed'  => $rewards_redeemed,
			'active_this_month' => $active_this_month,
		];
	}
}
