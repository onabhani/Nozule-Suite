<?php

namespace Nozule\Modules\Documents;

use Nozule\Core\BaseModule;
use Nozule\Core\Container;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Documents\Controllers\GuestDocumentController;
use Nozule\Modules\Documents\Models\GuestDocument;
use Nozule\Modules\Documents\Repositories\GuestDocumentRepository;
use Nozule\Modules\Documents\Services\GuestDocumentService;
use Nozule\Modules\Documents\Validators\GuestDocumentValidator;

/**
 * Documents module bootstrap.
 *
 * Registers repositories, validators, services, and controllers
 * for guest ID/passport document management.
 */
class DocumentsModule extends BaseModule {

	/**
	 * Register the module's services and hooks.
	 */
	public function register(): void {
		$this->registerRepositories();
		$this->registerValidators();
		$this->registerServices();
		$this->registerControllers();
		$this->registerHooks();
	}

	private function registerRepositories(): void {
		$this->container->singleton( GuestDocumentRepository::class, function ( Container $c ) {
			return new GuestDocumentRepository(
				$c->get( Database::class )
			);
		} );
	}

	private function registerValidators(): void {
		$this->container->singleton( GuestDocumentValidator::class, function () {
			return new GuestDocumentValidator();
		} );
	}

	private function registerServices(): void {
		$this->container->singleton( GuestDocumentService::class, function ( Container $c ) {
			return new GuestDocumentService(
				$c->get( GuestDocumentRepository::class ),
				$c->get( GuestDocumentValidator::class ),
				$c->get( EventDispatcher::class ),
				$c->get( Logger::class )
			);
		} );
	}

	private function registerControllers(): void {
		$this->container->singleton( GuestDocumentController::class, function ( Container $c ) {
			return new GuestDocumentController(
				$c->get( GuestDocumentService::class )
			);
		} );
	}

	private function registerHooks(): void {
		add_action( 'rest_api_init', function () {
			$this->container->get( GuestDocumentController::class )->registerRoutes();
		} );
	}
}
