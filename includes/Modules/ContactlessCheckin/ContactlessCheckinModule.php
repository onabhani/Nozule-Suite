<?php

namespace Nozule\Modules\ContactlessCheckin;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\ContactlessCheckin\Controllers\ContactlessCheckinAdminController;
use Nozule\Modules\ContactlessCheckin\Controllers\ContactlessCheckinPublicController;
use Nozule\Modules\ContactlessCheckin\Repositories\CheckinRegistrationRepository;
use Nozule\Modules\ContactlessCheckin\Services\ContactlessCheckinService;
use Nozule\Modules\ContactlessCheckin\Validators\CheckinRegistrationValidator;
use Nozule\Modules\Guests\Repositories\GuestRepository;

/**
 * Contactless check-in module bootstrap (NZL-024).
 *
 * Provides pre-arrival digital registration: guests receive a token-based
 * link, upload ID documents, select room preferences, sign digitally,
 * and complete check-in before arriving at the hotel.
 *
 * Feature is opt-in — controlled by the contactless_checkin.enabled setting.
 */
class ContactlessCheckinModule extends BaseModule {

	public function register(): void {
		$this->registerRepositories();
		$this->registerValidators();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	private function registerRepositories(): void {
		$this->container->singleton( CheckinRegistrationRepository::class, function ( Container $c ) {
			return new CheckinRegistrationRepository(
				$c->get( Database::class )
			);
		} );
	}

	private function registerValidators(): void {
		$this->container->singleton( CheckinRegistrationValidator::class, function () {
			return new CheckinRegistrationValidator();
		} );
	}

	private function registerServices(): void {
		$this->container->singleton( ContactlessCheckinService::class, function ( Container $c ) {
			return new ContactlessCheckinService(
				$c->get( CheckinRegistrationRepository::class ),
				$c->get( CheckinRegistrationValidator::class ),
				$c->get( BookingRepository::class ),
				$c->get( GuestRepository::class ),
				$c->get( SettingsManager::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	private function registerControllers(): void {
		$this->container->singleton( ContactlessCheckinPublicController::class, function ( Container $c ) {
			return new ContactlessCheckinPublicController(
				$c->get( ContactlessCheckinService::class )
			);
		} );

		$this->container->singleton( ContactlessCheckinAdminController::class, function ( Container $c ) {
			return new ContactlessCheckinAdminController(
				$c->get( ContactlessCheckinService::class )
			);
		} );
	}

	private function registerHooks(): void {
		add_action( 'rest_api_init', function () {
			// Public routes are always registered (token validation handles access).
			$this->container->get( ContactlessCheckinPublicController::class )->registerRoutes();
			$this->container->get( ContactlessCheckinAdminController::class )->registerRoutes();
		} );
	}
}
