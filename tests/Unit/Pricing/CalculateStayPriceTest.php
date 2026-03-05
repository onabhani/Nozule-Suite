<?php

namespace Nozule\Tests\Unit\Pricing;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Pricing\Models\PricingResult;
use Nozule\Modules\Pricing\Models\RatePlan;
use Nozule\Modules\Pricing\Models\SeasonalRate;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;
use Nozule\Modules\Pricing\Repositories\SeasonalRateRepository;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\Rooms\Models\RoomType;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;
use PHPUnit\Framework\TestCase;

class CalculateStayPriceTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private RatePlanRepository $ratePlanRepo;
	private SeasonalRateRepository $seasonalRepo;
	private InventoryRepository $inventoryRepo;
	private RoomTypeRepository $roomTypeRepo;
	private SettingsManager $settings;
	private CacheManager $cache;
	private EventDispatcher $events;
	private PricingService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->ratePlanRepo  = Mockery::mock( RatePlanRepository::class );
		$this->seasonalRepo  = Mockery::mock( SeasonalRateRepository::class );
		$this->inventoryRepo = Mockery::mock( InventoryRepository::class );
		$this->roomTypeRepo  = Mockery::mock( RoomTypeRepository::class );
		$this->settings      = Mockery::mock( SettingsManager::class );
		$this->cache         = Mockery::mock( CacheManager::class );
		$this->events        = Mockery::mock( EventDispatcher::class );

		$this->service = new PricingService(
			$this->ratePlanRepo,
			$this->seasonalRepo,
			$this->inventoryRepo,
			$this->roomTypeRepo,
			$this->settings,
			$this->cache,
			$this->events
		);
	}

	/**
	 * Helper: create a RatePlan model with sensible defaults.
	 */
	private function makeRatePlan( array $overrides = [] ): RatePlan {
		return new RatePlan( array_merge( [
			'id'             => 1,
			'name'           => 'Standard',
			'status'         => 'active',
			'modifier_value' => 0,
			'modifier_type'  => 'percentage',
			'min_stay'       => 1,
			'max_stay'       => 0,
			'room_type_id'   => null, // applies to all
		], $overrides ) );
	}

	/**
	 * Helper: create a RoomType model.
	 */
	private function makeRoomType( array $overrides = [] ): RoomType {
		return new RoomType( array_merge( [
			'id'                => 1,
			'base_price'        => 200.0,
			'base_occupancy'    => 2,
			'max_occupancy'     => 4,
			'extra_adult_price' => 50.0,
			'extra_child_price' => 30.0,
			'status'            => 'active',
		], $overrides ) );
	}

	/**
	 * Set up common mock expectations for a basic pricing calculation.
	 */
	private function setupBasicMocks( RatePlan $ratePlan, RoomType $roomType, array $seasonalRates = [] ): void {
		// Rate plan resolution (uses default).
		$this->ratePlanRepo->shouldReceive( 'getDefaultForRoomType' )
			->andReturn( $ratePlan );

		// Seasonal rates.
		$this->seasonalRepo->shouldReceive( 'getForDateRange' )
			->andReturn( $seasonalRates );
		if ( empty( $seasonalRates ) ) {
			$this->seasonalRepo->shouldReceive( 'getActiveForDate' )
				->andReturn( [] );
		}

		// No inventory price overrides — fall back to room type base_price.
		$this->inventoryRepo->shouldReceive( 'getForDate' )
			->andReturn( null );

		// Room type lookup (used by calculateExtraPersonCharge and base price resolution).
		$this->roomTypeRepo->shouldReceive( 'find' )
			->with( $roomType->id )
			->andReturn( $roomType );

		// Settings.
		$this->settings->shouldReceive( 'get' )
			->with( 'pricing.service_fee_rate', 0 )
			->andReturn( 0 );
		$this->settings->shouldReceive( 'get' )
			->with( 'pricing.tax_rate', 0 )
			->andReturn( 0 );
		$this->settings->shouldReceive( 'get' )
			->with( 'currency.default', 'USD' )
			->andReturn( 'SAR' );
		$this->settings->shouldReceive( 'get' )
			->with( 'currency.exchange_rate', 1.0 )
			->andReturn( 1.0 );
		$this->settings->shouldReceive( 'get' )
			->with( 'pricing.extra_adult_charge', 0 )
			->andReturn( 0 );
		$this->settings->shouldReceive( 'get' )
			->with( 'pricing.extra_child_charge', 0 )
			->andReturn( 0 );

		// Event dispatcher stubs.
		$this->events->shouldReceive( 'filter' )
			->withArgs( fn( string $name ) => $name === 'pricing/discount' )
			->andReturnUsing( fn( $name, $discount ) => $discount );
		$this->events->shouldReceive( 'filter' )
			->withArgs( fn( string $name ) => $name === 'pricing/nightly_rate' )
			->andReturnUsing( fn( $name, $price ) => $price );
		$this->events->shouldReceive( 'dispatch' )
			->withArgs( fn( string $name ) => $name === 'pricing/calculated' );
	}

	/**
	 * Base rate × nights = correct total with no modifiers.
	 */
	public function testBaseRateTimesNightsEqualsCorrectTotal(): void {
		$ratePlan = $this->makeRatePlan();
		$roomType = $this->makeRoomType( [ 'base_price' => 200.0 ] );
		$this->setupBasicMocks( $ratePlan, $roomType );

		// 3 nights, 2 adults (= base occupancy), 0 children, no tax, no fees.
		$result = $this->service->calculateStayPrice( 1, '2026-04-01', '2026-04-04', 2 );

		$this->assertInstanceOf( PricingResult::class, $result );
		$this->assertSame( 600.0, $result->subtotal );  // 200 × 3
		$this->assertSame( 0.0, $result->taxes );
		$this->assertSame( 0.0, $result->fees );
		$this->assertSame( 600.0, $result->total );
		$this->assertCount( 3, $result->nightlyRates );
		$this->assertSame( 'SAR', $result->currency );
	}

	/**
	 * Seasonal modifier applied correctly (percentage increase).
	 */
	public function testSeasonalModifierAppliedCorrectly(): void {
		$ratePlan = $this->makeRatePlan();
		$roomType = $this->makeRoomType( [ 'base_price' => 200.0 ] );

		// 20% seasonal increase — applies to all 3 nights.
		$seasonalRate = new SeasonalRate( [
			'id'             => 1,
			'status'         => 'active',
			'modifier_value' => 20,
			'modifier_type'  => 'percentage',
			'rate_plan_id'   => 1,
			'days_of_week'   => null, // applies every day
			'start_date'     => '2026-04-01',
			'end_date'       => '2026-04-30',
		] );

		$this->setupBasicMocks( $ratePlan, $roomType, [ $seasonalRate ] );

		// 200 base + 20% seasonal = 240/night × 3 nights = 720.
		$result = $this->service->calculateStayPrice( 1, '2026-04-01', '2026-04-04', 2 );

		$this->assertSame( 720.0, $result->subtotal );
		$this->assertSame( 720.0, $result->total );
	}

	/**
	 * Extra person charge applied when guests exceed base occupancy.
	 */
	public function testExtraPersonChargeAppliedWhenGuestsExceedBaseOccupancy(): void {
		$ratePlan = $this->makeRatePlan();
		$roomType = $this->makeRoomType( [
			'base_price'        => 200.0,
			'base_occupancy'    => 2,
			'extra_adult_price' => 50.0,
			'extra_child_price' => 30.0,
		] );
		$this->setupBasicMocks( $ratePlan, $roomType );

		// 3 adults (1 extra) + 1 child, 3 nights.
		// Extra charge = (1 × 50 + 1 × 30) × 3 = 240.
		$result = $this->service->calculateStayPrice( 1, '2026-04-01', '2026-04-04', 3, 1 );

		$this->assertSame( 600.0, $result->subtotal );  // 200 × 3
		$this->assertSame( 240.0, $result->fees );       // (50 + 30) × 3
		$this->assertSame( 840.0, $result->total );       // 600 + 240
	}
}
