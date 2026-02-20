<?php

namespace Nozule\Modules\Reviews;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Messaging\Services\EmailService;
use Nozule\Modules\Reviews\Controllers\ReviewController;
use Nozule\Modules\Reviews\Repositories\ReviewRepository;
use Nozule\Modules\Reviews\Services\ReviewService;

/**
 * Reviews module bootstrap.
 *
 * Registers all services, repositories, and controllers related to
 * post-checkout review solicitation. Hooks into booking lifecycle
 * events and schedules WP-Cron for processing queued review emails.
 */
class ReviewModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerServices();
		$this->registerHooks();
	}

	/**
	 * Bind module services into the DI container.
	 */
	private function registerServices(): void {
		// Repository.
		$this->container->singleton( ReviewRepository::class, function ( Container $c ) {
			return new ReviewRepository( $c->get( Database::class ) );
		} );

		// Core service.
		$this->container->singleton( ReviewService::class, function ( Container $c ) {
			return new ReviewService(
				$c->get( ReviewRepository::class ),
				$c->get( EmailService::class ),
				$c->get( SettingsManager::class ),
				$c->get( Logger::class ),
				$c->get( Database::class )
			);
		} );

		// Controller.
		$this->container->singleton( ReviewController::class, function ( Container $c ) {
			return new ReviewController(
				$c->get( ReviewService::class ),
				$c->get( ReviewRepository::class )
			);
		} );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function registerHooks(): void {
		// Register REST routes on rest_api_init.
		add_action( 'rest_api_init', function () {
			$this->container->get( ReviewController::class )->registerRoutes();
		} );

		// ── Booking lifecycle: queue review request on checkout ──────
		add_action( 'nozule/booking/checked_out', function ( int $bookingId ) {
			$this->queueReviewRequest( $bookingId );
		} );

		// ── WP-Cron: process pending review requests ────────────────
		add_action( 'nzl_process_review_requests', function () {
			$this->container->get( ReviewService::class )->processPendingRequests();
		} );

		// Register custom cron interval (every 15 minutes).
		add_filter( 'cron_schedules', function ( array $schedules ) {
			if ( ! isset( $schedules['nzl_every_15_minutes'] ) ) {
				$schedules['nzl_every_15_minutes'] = [
					'interval' => 15 * MINUTE_IN_SECONDS,
					'display'  => __( 'Every 15 Minutes', 'nozule' ),
				];
			}
			return $schedules;
		} );

		// Schedule the cron event if not already scheduled.
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'nzl_process_review_requests' ) ) {
				wp_schedule_event( time(), 'nzl_every_15_minutes', 'nzl_process_review_requests' );
			}
		} );
	}

	/**
	 * Queue a review request for a checked-out booking.
	 *
	 * @param int $bookingId The booking ID.
	 */
	private function queueReviewRequest( int $bookingId ): void {
		try {
			/** @var ReviewService $reviewService */
			$reviewService = $this->container->get( ReviewService::class );
			$reviewService->queueReviewRequest( $bookingId );
		} catch ( \Throwable $e ) {
			/** @var Logger $logger */
			$logger = $this->container->get( Logger::class );
			$logger->error( "Failed to queue review request for booking {$bookingId}", [
				'error' => $e->getMessage(),
			] );
		}
	}
}
