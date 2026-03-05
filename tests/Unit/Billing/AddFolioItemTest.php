<?php

namespace Nozule\Tests\Unit\Billing;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Billing\Models\Folio;
use Nozule\Modules\Billing\Models\FolioItem;
use Nozule\Modules\Billing\Repositories\FolioItemRepository;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\Billing\Services\FolioService;
use Nozule\Modules\Billing\Services\TaxService;
use Nozule\Modules\Billing\Validators\FolioValidator;
use PHPUnit\Framework\TestCase;

class AddFolioItemTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private FolioRepository $folioRepo;
	private FolioItemRepository $folioItemRepo;
	private TaxService $taxService;
	private FolioValidator $folioValidator;
	private SettingsManager $settings;
	private EventDispatcher $events;
	private Logger $logger;
	private FolioService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->folioRepo     = Mockery::mock( FolioRepository::class );
		$this->folioItemRepo = Mockery::mock( FolioItemRepository::class );
		$this->taxService    = Mockery::mock( TaxService::class );
		$this->folioValidator = Mockery::mock( FolioValidator::class );
		$this->settings      = Mockery::mock( SettingsManager::class );
		$this->events        = Mockery::mock( EventDispatcher::class );
		$this->logger        = Mockery::mock( Logger::class );

		$this->service = new FolioService(
			$this->folioRepo,
			$this->folioItemRepo,
			$this->taxService,
			$this->folioValidator,
			$this->settings,
			$this->events,
			$this->logger
		);
	}

	/**
	 * Tax calculated correctly for a room charge category.
	 */
	public function testTaxCalculatedCorrectlyForRoomCharge(): void {
		$folioId = 10;
		$data    = [
			'category'    => FolioItem::CAT_ROOM_CHARGE,
			'description' => 'Night 1',
			'quantity'    => 1,
			'unit_price'  => 200.00,
			'date'        => '2026-04-01',
		];

		$folio = new Folio( [ 'id' => $folioId, 'status' => Folio::STATUS_OPEN ] );
		$item  = new FolioItem( [
			'id'        => 50,
			'folio_id'  => $folioId,
			'category'  => FolioItem::CAT_ROOM_CHARGE,
			'subtotal'  => 200.00,
			'tax_total' => 30.00,
			'total'     => 230.00,
		] );

		// Validation passes.
		$this->folioValidator->shouldReceive( 'validateAddItem' )
			->once()
			->andReturn( true );

		// Folio lookup — open folio.
		$this->folioRepo->shouldReceive( 'find' )
			->with( $folioId )
			->once()
			->andReturn( $folio );

		// Tax calculation: 15% VAT on room charge.
		$this->taxService->shouldReceive( 'calculateTaxes' )
			->once()
			->with( 200.00, FolioItem::CAT_ROOM_CHARGE )
			->andReturn( [
				'taxes'     => [ [ 'name' => 'VAT', 'rate' => 15.0, 'amount' => 30.00 ] ],
				'total_tax' => 30.00,
			] );

		// Item created successfully.
		$this->folioItemRepo->shouldReceive( 'create' )
			->once()
			->with( Mockery::on( function ( array $record ) {
				return $record['subtotal'] === 200.00
					&& $record['tax_total'] === 30.00
					&& $record['total'] === 230.00
					&& $record['category'] === FolioItem::CAT_ROOM_CHARGE;
			} ) )
			->andReturn( $item );

		// Folio totals recalculated.
		$this->folioRepo->shouldReceive( 'recalculateTotals' )
			->once()
			->with( $folioId );

		// Side effects.
		$this->events->shouldReceive( 'dispatch' )
			->once()
			->with( 'billing/item_added', $item, $folio );
		$this->logger->shouldReceive( 'info' )->once();

		$result = $this->service->addItem( $folioId, $data );

		$this->assertInstanceOf( FolioItem::class, $result );
		$this->assertSame( 230.00, $result->total );
		$this->assertSame( 30.00, $result->tax_total );
	}

	/**
	 * Folio total updates after item is added (recalculateFolioTotals called).
	 */
	public function testFolioTotalUpdatesAfterItemAdded(): void {
		$folioId = 10;
		$data    = [
			'category'    => FolioItem::CAT_EXTRA,
			'description' => 'Minibar',
			'quantity'    => 2,
			'unit_price'  => 15.00,
			'date'        => '2026-04-01',
		];

		$folio = new Folio( [ 'id' => $folioId, 'status' => Folio::STATUS_OPEN ] );
		$item  = new FolioItem( [
			'id'        => 51,
			'folio_id'  => $folioId,
			'category'  => FolioItem::CAT_EXTRA,
			'subtotal'  => 30.00,
			'tax_total' => 4.50,
			'total'     => 34.50,
		] );

		$this->folioValidator->shouldReceive( 'validateAddItem' )->once()->andReturn( true );
		$this->folioRepo->shouldReceive( 'find' )->with( $folioId )->once()->andReturn( $folio );

		// Tax on extras: 15% on 30 = 4.50.
		$this->taxService->shouldReceive( 'calculateTaxes' )
			->once()
			->with( 30.00, FolioItem::CAT_EXTRA )
			->andReturn( [
				'taxes'     => [ [ 'name' => 'VAT', 'rate' => 15.0, 'amount' => 4.50 ] ],
				'total_tax' => 4.50,
			] );

		$this->folioItemRepo->shouldReceive( 'create' )->once()->andReturn( $item );

		// The key assertion: folio totals are recalculated.
		$this->folioRepo->shouldReceive( 'recalculateTotals' )
			->once()
			->with( $folioId );

		$this->events->shouldReceive( 'dispatch' )->once();
		$this->logger->shouldReceive( 'info' )->once();

		$result = $this->service->addItem( $folioId, $data );

		$this->assertInstanceOf( FolioItem::class, $result );
	}

	/**
	 * Discount items do not have tax applied.
	 */
	public function testDiscountItemsDoNotHaveTaxApplied(): void {
		$folioId = 10;
		$data    = [
			'category'    => FolioItem::CAT_DISCOUNT,
			'description' => 'Loyalty discount',
			'quantity'    => 1,
			'unit_price'  => -50.00,
			'date'        => '2026-04-01',
		];

		$folio = new Folio( [ 'id' => $folioId, 'status' => Folio::STATUS_OPEN ] );
		$item  = new FolioItem( [
			'id'        => 52,
			'folio_id'  => $folioId,
			'category'  => FolioItem::CAT_DISCOUNT,
			'subtotal'  => 50.00,
			'tax_total' => 0.00,
			'total'     => 50.00,
		] );

		$this->folioValidator->shouldReceive( 'validateAddItem' )->once()->andReturn( true );
		$this->folioRepo->shouldReceive( 'find' )->with( $folioId )->once()->andReturn( $folio );

		// Tax service should NOT be called for discounts.
		$this->taxService->shouldNotReceive( 'calculateTaxes' );

		$this->folioItemRepo->shouldReceive( 'create' )
			->once()
			->with( Mockery::on( function ( array $record ) {
				// Tax should be zero.
				return $record['tax_total'] === 0.0
					// Discount stored as positive (abs).
					&& $record['subtotal'] === 50.00;
			} ) )
			->andReturn( $item );

		$this->folioRepo->shouldReceive( 'recalculateTotals' )->once();
		$this->events->shouldReceive( 'dispatch' )->once();
		$this->logger->shouldReceive( 'info' )->once();

		$result = $this->service->addItem( $folioId, $data );

		$this->assertInstanceOf( FolioItem::class, $result );
	}
}
