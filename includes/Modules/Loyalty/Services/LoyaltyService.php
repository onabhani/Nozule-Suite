<?php

namespace Nozule\Modules\Loyalty\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Modules\Loyalty\Models\LoyaltyMember;
use Nozule\Modules\Loyalty\Models\LoyaltyReward;
use Nozule\Modules\Loyalty\Models\LoyaltyTransaction;
use Nozule\Modules\Loyalty\Repositories\LoyaltyRepository;

/**
 * Service layer for loyalty program business logic.
 */
class LoyaltyService {

	private LoyaltyRepository $repository;
	private EventDispatcher $events;

	public function __construct( LoyaltyRepository $repository, EventDispatcher $events ) {
		$this->repository = $repository;
		$this->events     = $events;
	}

	// ==================================================================
	// ENROLLMENT
	// ==================================================================

	/**
	 * Enroll a guest in the loyalty program at the lowest tier.
	 *
	 * @param int $guestId Guest ID to enroll.
	 * @return LoyaltyMember The newly created member.
	 * @throws \RuntimeException If the guest is already enrolled or no tiers exist.
	 */
	public function enrollGuest( int $guestId ): LoyaltyMember {
		$existing = $this->repository->getMemberByGuest( $guestId );
		if ( $existing ) {
			throw new \RuntimeException(
				__( 'This guest is already enrolled in the loyalty program.', 'nozule' )
			);
		}

		$lowestTier = $this->repository->getLowestTier();
		if ( ! $lowestTier ) {
			throw new \RuntimeException(
				__( 'No loyalty tiers have been configured. Please create at least one tier.', 'nozule' )
			);
		}

		$member = $this->repository->saveMember( [
			'guest_id'        => $guestId,
			'tier_id'         => $lowestTier->id,
			'points_balance'  => 0,
			'lifetime_points' => 0,
		] );

		if ( ! $member ) {
			throw new \RuntimeException(
				__( 'Failed to enroll guest in the loyalty program.', 'nozule' )
			);
		}

		$this->events->dispatch( 'loyalty/member_enrolled', $member );

		return $member;
	}

	// ==================================================================
	// POINTS MANAGEMENT
	// ==================================================================

	/**
	 * Award points to a member for a booking.
	 *
	 * Points are calculated at 1 point per currency unit spent (configurable).
	 *
	 * @param int   $memberId  Loyalty member ID.
	 * @param int   $bookingId Booking ID.
	 * @param float $amount    Amount spent.
	 * @return LoyaltyTransaction The earn transaction.
	 * @throws \RuntimeException On failure.
	 */
	public function awardPoints( int $memberId, int $bookingId, float $amount ): LoyaltyTransaction {
		$rate   = $this->getPointsRate();
		$points = (int) floor( $amount * $rate );

		if ( $points <= 0 ) {
			throw new \RuntimeException(
				__( 'Amount too small to earn points.', 'nozule' )
			);
		}

		$memberRow = $this->repository->getMember( $memberId );
		if ( ! $memberRow ) {
			throw new \RuntimeException(
				sprintf( __( 'Loyalty member with ID %d not found.', 'nozule' ), $memberId )
			);
		}

		// Use atomic SQL increment to prevent race conditions.
		$updated = $this->repository->atomicAdjustPoints( $memberId, $points, true );

		if ( ! $updated ) {
			throw new \RuntimeException(
				__( 'Failed to update points balance.', 'nozule' )
			);
		}

		// Read back the new balance for the transaction log.
		$newBalance = $this->repository->getMemberPointsBalance( $memberId );

		$transaction = $this->repository->addTransaction( [
			'member_id'     => $memberId,
			'type'          => LoyaltyTransaction::TYPE_EARN,
			'points'        => $points,
			'balance_after' => $newBalance,
			'booking_id'    => $bookingId,
			'description'   => sprintf(
				/* translators: 1: Points earned, 2: Booking ID */
				__( 'Earned %1$d points for booking #%2$d', 'nozule' ),
				$points,
				$bookingId
			),
			'created_by'    => get_current_user_id(),
		] );

		if ( ! $transaction ) {
			throw new \RuntimeException(
				__( 'Failed to record points transaction.', 'nozule' )
			);
		}

		// Check for tier upgrade.
		$this->evaluateTierUpgrade( $memberId );

		$this->events->dispatch( 'loyalty/points_awarded', $memberId, $points, $bookingId );

		return $transaction;
	}

	/**
	 * Redeem a reward for a member.
	 *
	 * @param int $memberId Loyalty member ID.
	 * @param int $rewardId Reward ID to redeem.
	 * @return array{ transaction: LoyaltyTransaction, reward: LoyaltyReward }
	 * @throws \RuntimeException On failure.
	 */
	public function redeemReward( int $memberId, int $rewardId ): array {
		$reward = $this->repository->getReward( $rewardId );
		if ( ! $reward ) {
			throw new \RuntimeException(
				__( 'Reward not found.', 'nozule' )
			);
		}

		if ( ! $reward->is_active ) {
			throw new \RuntimeException(
				__( 'This reward is no longer available.', 'nozule' )
			);
		}

		// Use atomic SQL decrement to prevent race conditions.
		// atomicAdjustPoints checks balance >= cost before deducting.
		$updated = $this->repository->atomicAdjustPoints( $memberId, -$reward->points_cost, false );

		if ( ! $updated ) {
			$balance = $this->repository->getMemberPointsBalance( $memberId );
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: Required points, 2: Current balance */
					__( 'Insufficient points. Requires %1$d, current balance is %2$d.', 'nozule' ),
					$reward->points_cost,
					$balance
				)
			);
		}

		$newBalance = $this->repository->getMemberPointsBalance( $memberId );

		$transaction = $this->repository->addTransaction( [
			'member_id'     => $memberId,
			'type'          => LoyaltyTransaction::TYPE_REDEEM,
			'points'        => -$reward->points_cost,
			'balance_after' => $newBalance,
			'reward_id'     => $rewardId,
			'description'   => sprintf(
				/* translators: 1: Reward name, 2: Points cost */
				__( 'Redeemed "%1$s" for %2$d points', 'nozule' ),
				$reward->name,
				$reward->points_cost
			),
			'created_by'    => get_current_user_id(),
		] );

		if ( ! $transaction ) {
			throw new \RuntimeException(
				__( 'Failed to process redemption.', 'nozule' )
			);
		}

		$this->events->dispatch( 'loyalty/reward_redeemed', $memberId, $rewardId );

		return [
			'transaction' => $transaction,
			'reward'      => $reward,
		];
	}

	/**
	 * Manually adjust points for a member (admin action).
	 *
	 * @param int    $memberId    Loyalty member ID.
	 * @param int    $points      Points to adjust (positive or negative).
	 * @param string $description Reason for adjustment.
	 * @return LoyaltyTransaction The adjustment transaction.
	 * @throws \RuntimeException On failure.
	 */
	public function adjustPoints( int $memberId, int $points, string $description ): LoyaltyTransaction {
		if ( $points === 0 ) {
			throw new \RuntimeException(
				__( 'Points adjustment cannot be zero.', 'nozule' )
			);
		}

		$memberRow = $this->repository->getMember( $memberId );
		if ( ! $memberRow ) {
			throw new \RuntimeException(
				sprintf( __( 'Loyalty member with ID %d not found.', 'nozule' ), $memberId )
			);
		}

		// Use atomic SQL adjustment to prevent race conditions.
		$updated = $this->repository->atomicAdjustPoints( $memberId, $points, $points > 0 );

		if ( ! $updated ) {
			$balance = $this->repository->getMemberPointsBalance( $memberId );
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: Current balance */
					__( 'Adjustment would result in negative balance. Current balance: %d', 'nozule' ),
					$balance
				)
			);
		}

		$newBalance = $this->repository->getMemberPointsBalance( $memberId );

		$transaction = $this->repository->addTransaction( [
			'member_id'     => $memberId,
			'type'          => LoyaltyTransaction::TYPE_ADJUST,
			'points'        => $points,
			'balance_after' => $newBalance,
			'description'   => $description,
			'created_by'    => get_current_user_id(),
		] );

		if ( ! $transaction ) {
			throw new \RuntimeException(
				__( 'Failed to record points adjustment.', 'nozule' )
			);
		}

		// Check for tier upgrade if points were added.
		if ( $points > 0 ) {
			$this->evaluateTierUpgrade( $memberId );
		}

		$this->events->dispatch( 'loyalty/points_adjusted', $memberId, $points );

		return $transaction;
	}

	// ==================================================================
	// TIER MANAGEMENT
	// ==================================================================

	/**
	 * Evaluate whether a member qualifies for a tier upgrade.
	 *
	 * @param int $memberId Loyalty member ID.
	 * @return bool True if the member was upgraded.
	 */
	public function evaluateTierUpgrade( int $memberId ): bool {
		$memberRow = $this->repository->getMember( $memberId );
		if ( ! $memberRow ) {
			return false;
		}

		$lifetimePoints = (int) $memberRow['lifetime_points'];
		$currentTierId  = (int) $memberRow['tier_id'];

		$qualifiedTier = $this->repository->getTierForPoints( $lifetimePoints );
		if ( ! $qualifiedTier || $qualifiedTier->id === $currentTierId ) {
			return false;
		}

		// Only upgrade, never downgrade.
		$currentTier = $this->repository->getTier( $currentTierId );
		if ( $currentTier && $qualifiedTier->min_points <= $currentTier->min_points ) {
			return false;
		}

		$this->repository->updateMemberTier( $memberId, $qualifiedTier->id );

		$this->events->dispatch( 'loyalty/tier_upgraded', $memberId, $qualifiedTier->id );

		return true;
	}

	// ==================================================================
	// CONFIGURATION
	// ==================================================================

	/**
	 * Get the points-per-currency-unit rate.
	 *
	 * Defaults to 1 point per currency unit. Can be overridden via filter.
	 *
	 * @return float Points rate.
	 */
	public function getPointsRate(): float {
		$rate = (float) $this->events->filter( 'loyalty/points_rate', 1.0 );

		return max( 0.01, $rate );
	}
}
