<?php

namespace Nozule\Tests\Unit\Rooms;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Rooms\Repositories\InventoryRepository;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;
use Nozule\Modules\Rooms\Services\AvailabilityService;
use PHPUnit\Framework\TestCase;

class DeductInventoryTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private InventoryRepository $inventoryRepo;
	private RoomTypeRepository $roomTypeRepo;
	private CacheManager $cache;
	private EventDispatcher $events;
	private Logger $logger;
	private AvailabilityService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->inventoryRepo = Mockery::mock( InventoryRepository::class );
		$this->roomTypeRepo  = Mockery::mock( RoomTypeRepository::class );
		$this->cache         = Mockery::mock( CacheManager::class );
		$this->events        = Mockery::mock( EventDispatcher::class );
		$this->logger        = Mockery::mock( Logger::class );

		$this->service = new AvailabilityService(
			$this->inventoryRepo,
			$this->roomTypeRepo,
			$this->cache,
			$this->events,
			$this->logger
		);
	}

	/**
	 * Inventory decrements correctly across date range.
	 */
	public function testInventoryDecrementsCorrectlyAcrossDateRange(): void {
		$roomTypeId = 1;
		$checkIn    = '2026-04-01';
		$checkOut   = '2026-04-04'; // 3 nights

		$this->inventoryRepo->shouldReceive( 'beginTransaction' )->once();

		// deductRooms succeeds.
		$this->inventoryRepo->shouldReceive( 'deductRooms' )
			->once()
			->with( $roomTypeId, $checkIn, $checkOut, 1 )
			->andReturn( true );

		$this->inventoryRepo->shouldReceive( 'commit' )->once();

		// Cache invalidation.
		$this->cache->shouldReceive( 'invalidateTag' )
			->once()
			->with( 'availability' );
		$this->cache->shouldReceive( 'delete' )
			->times( 3 ); // one per night

		// Event dispatched.
		$this->events->shouldReceive( 'dispatch' )
			->once()
			->with( 'rooms/inventory_deducted', $roomTypeId, $checkIn, $checkOut, 1 );

		// Logging.
		$this->logger->shouldReceive( 'info' )->once();

		$result = $this->service->deductInventory( $roomTypeId, $checkIn, $checkOut );

		$this->assertTrue( $result );
	}

	/**
	 * Partial deduction failure rolls back all dates.
	 */
	public function testPartialDeductionFailureRollsBackAllDates(): void {
		$roomTypeId = 1;
		$checkIn    = '2026-04-01';
		$checkOut   = '2026-04-04';

		$this->inventoryRepo->shouldReceive( 'beginTransaction' )->once();

		// deductRooms returns false (e.g. insufficient rooms on one date).
		$this->inventoryRepo->shouldReceive( 'deductRooms' )
			->once()
			->with( $roomTypeId, $checkIn, $checkOut, 1 )
			->andReturn( false );

		// Transaction rolled back — not committed.
		$this->inventoryRepo->shouldReceive( 'rollback' )->once();
		$this->inventoryRepo->shouldNotReceive( 'commit' );

		// Warning logged.
		$this->logger->shouldReceive( 'warning' )
			->once()
			->with( 'Inventory deduction failed - insufficient availability', Mockery::type( 'array' ) );

		// No events dispatched, no cache invalidated.
		$this->events->shouldNotReceive( 'dispatch' );
		$this->cache->shouldNotReceive( 'invalidateTag' );

		$result = $this->service->deductInventory( $roomTypeId, $checkIn, $checkOut );

		$this->assertFalse( $result );
	}

	/**
	 * Exception during deduction rolls back and returns false.
	 */
	public function testExceptionDuringDeductionRollsBackAndReturnsFalse(): void {
		$this->inventoryRepo->shouldReceive( 'beginTransaction' )->once();

		$this->inventoryRepo->shouldReceive( 'deductRooms' )
			->once()
			->andThrow( new \RuntimeException( 'DB connection lost' ) );

		$this->inventoryRepo->shouldReceive( 'rollback' )->once();
		$this->inventoryRepo->shouldNotReceive( 'commit' );

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( 'Inventory deduction error', Mockery::on(
				fn( $ctx ) => $ctx['error'] === 'DB connection lost'
			) );

		$result = $this->service->deductInventory( 1, '2026-04-01', '2026-04-04' );

		$this->assertFalse( $result );
	}
}
