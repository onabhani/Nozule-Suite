<?php

namespace Nozule\Modules\Branding;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Branding\Controllers\BrandController;
use Nozule\Modules\Branding\Repositories\BrandRepository;
use Nozule\Modules\Branding\Services\BrandService;

/**
 * Branding module bootstrap (NZL-041).
 *
 * Registers all services, repositories, and controllers
 * for the white-label / multi-brand feature.
 */
class BrandingModule extends BaseModule {

	/**
	 * Register the module's bindings, services, and hooks.
	 */
	public function register(): void {
		$this->registerBindings();
		$this->registerHooks();
	}

	/**
	 * Register service container bindings.
	 */
	private function registerBindings(): void {
		$this->container->singleton(
			BrandRepository::class,
			fn() => new BrandRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			BrandService::class,
			fn() => new BrandService(
				$this->container->get( BrandRepository::class ),
				$this->container->get( EventDispatcher::class ),
				$this->container->get( Logger::class )
			)
		);

		$this->container->singleton(
			BrandController::class,
			fn() => new BrandController(
				$this->container->get( BrandService::class )
			)
		);
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		// Register REST routes.
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

		// Inject brand CSS variables on public pages.
		add_action( 'wp_head', [ $this, 'injectBrandCSS' ] );

		// Inject brand accent in admin if configured.
		add_action( 'admin_head', [ $this, 'injectAdminBrandCSS' ] );

		// Provide brand data for email templates via filter.
		$events = $this->container->get( EventDispatcher::class );
		$events->addFilter( 'messaging/email_brand_data', [ $this, 'provideBrandForEmails' ] );
	}

	/**
	 * Callback to register REST API routes.
	 */
	public function registerRestRoutes(): void {
		$controller = $this->container->get( BrandController::class );
		$controller->registerRoutes();
	}

	/**
	 * Inject brand CSS variables into wp_head for public pages.
	 */
	public function injectBrandCSS(): void {
		$service = $this->container->get( BrandService::class );
		$service->applyBrand();
	}

	/**
	 * Inject brand accent into admin_head if configured.
	 */
	public function injectAdminBrandCSS(): void {
		$service = $this->container->get( BrandService::class );
		$service->applyAdminBrand();
	}

	/**
	 * Filter callback to provide brand data for email templates.
	 *
	 * @param array|null $brandData Existing brand data (may be null).
	 * @return array|null Brand data for emails.
	 */
	public function provideBrandForEmails( $brandData ) {
		$service   = $this->container->get( BrandService::class );
		$emailData = $service->getBrandForEmails();

		return $emailData ?: $brandData;
	}
}
