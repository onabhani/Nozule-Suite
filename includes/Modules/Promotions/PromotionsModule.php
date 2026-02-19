<?php

namespace Nozule\Modules\Promotions;

use Nozule\Core\BaseModule;
use Nozule\Core\Database;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Promotions\Controllers\PromoCodeController;
use Nozule\Modules\Promotions\Models\PromoCode;
use Nozule\Modules\Promotions\Repositories\PromoCodeRepository;
use Nozule\Modules\Promotions\Services\PromoCodeService;
use Nozule\Modules\Promotions\Validators\PromoCodeValidator;

/**
 * Promotions module bootstrap.
 *
 * Registers all services, repositories, validators, and controllers
 * for the promo codes / discounts feature (NZL-006).
 */
class PromotionsModule extends BaseModule {

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
			PromoCodeRepository::class,
			fn() => new PromoCodeRepository(
				$this->container->get( Database::class )
			)
		);

		$this->container->singleton(
			PromoCodeValidator::class,
			fn() => new PromoCodeValidator(
				$this->container->get( PromoCodeRepository::class )
			)
		);

		$this->container->singleton(
			PromoCodeService::class,
			fn() => new PromoCodeService(
				$this->container->get( PromoCodeRepository::class ),
				$this->container->get( PromoCodeValidator::class ),
				$this->container->get( EventDispatcher::class ),
				$this->container->get( Logger::class )
			)
		);

		$this->container->singleton(
			PromoCodeController::class,
			fn() => new PromoCodeController(
				$this->container->get( PromoCodeService::class )
			)
		);
	}

	/**
	 * Register WordPress hooks for this module.
	 */
	private function registerHooks(): void {
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

		// Hook into the pricing discount filter to auto-apply promo codes.
		$events = $this->container->get( EventDispatcher::class );
		$events->addFilter( 'pricing/calculate_discount', [ $this, 'applyPromoDiscount' ], 20 );
	}

	/**
	 * Callback to register REST API routes.
	 */
	public function registerRestRoutes(): void {
		$controller = $this->container->get( PromoCodeController::class );
		$controller->registerRoutes();
	}

	/**
	 * Filter callback to apply promo code discount during pricing calculation.
	 *
	 * Expected $context structure:
	 * [
	 *     'discount'   => float,     // Current discount amount.
	 *     'subtotal'   => float,     // Booking subtotal.
	 *     'nights'     => int,       // Number of nights.
	 *     'promo_code' => string,    // The promo code string (if any).
	 *     'guest_id'   => int|null,  // Guest ID (if available).
	 * ]
	 *
	 * @param array $context The pricing context array.
	 * @return array Modified context with updated discount.
	 */
	public function applyPromoDiscount( array $context ): array {
		$promoCodeString = $context['promo_code'] ?? '';

		if ( empty( $promoCodeString ) ) {
			return $context;
		}

		$service  = $this->container->get( PromoCodeService::class );
		$subtotal = $context['subtotal'] ?? 0.0;
		$nights   = $context['nights'] ?? 1;
		$guestId  = $context['guest_id'] ?? null;

		$result = $service->validateCode( $promoCodeString, $subtotal, $nights, $guestId );

		if ( ! $result instanceof PromoCode ) {
			return $context;
		}

		$discount = $service->applyDiscount( $result, $subtotal );

		$context['discount']       = ( $context['discount'] ?? 0.0 ) + $discount;
		$context['promo_discount'] = $discount;
		$context['promo_id']       = $result->id;

		return $context;
	}
}
