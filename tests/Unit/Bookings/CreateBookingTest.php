<?php

namespace Nozule\Tests\Unit\Bookings;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Exceptions\NoAvailabilityException;
use Nozule\Modules\Bookings\Models\Booking;
use Nozule\Modules\Bookings\Models\BookingLog;
use Nozule\Modules\Bookings\Repositories\BookingRepository;
use Nozule\Modules\Bookings\Repositories\PaymentRepository;
use Nozule\Modules\Bookings\Services\BookingService;
use Nozule\Modules\Bookings\Validators\BookingValidator;
use Nozule\Modules\Guests\Services\GuestService;
use Nozule\Modules\Notifications\Services\NotificationService;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\Rooms\Services\AvailabilityService;
use Nozule\Tests\Stubs\NotificationServiceStub;
use PHPUnit\Framework\TestCase;

class CreateBookingTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private BookingRepository $bookingRepo;
	private PaymentRepository $paymentRepo;
	private BookingValidator $validator;
	private GuestService $guestService;
	private AvailabilityService $availabilityService;
	private PricingService $pricingService;
	private $notificationService;
	private SettingsManager $settings;
	private BookingService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->bookingRepo         = Mockery::mock( BookingRepository::class );
		$this->paymentRepo         = Mockery::mock( PaymentRepository::class );
		$this->validator           = Mockery::mock( BookingValidator::class );
		$this->guestService        = Mockery::mock( GuestService::class );
		$this->availabilityService = Mockery::mock( AvailabilityService::class );
		$this->pricingService      = Mockery::mock( PricingService::class );
		$this->settings            = Mockery::mock( SettingsManager::class );

		// Use stub with widened parameter types to avoid TypeError
		// (production code passes string where method expects object).
		$this->notificationService = Mockery::mock( NotificationServiceStub::class );

		$this->service = new BookingService(
			$this->bookingRepo,
			$this->paymentRepo,
			$this->validator,
			$this->guestService,
			$this->availabilityService,
			$this->pricingService,
			$this->notificationService,
			$this->settings
		);
	}

	/**
	 * Valid booking creates DB record, deducts inventory, returns Booking.
	 */
	public function testValidBookingCreatesRecordDeductsInventoryAndReturnsBooking(): void {
		$data = [
			'room_type_id' => 1,
			'check_in'     => '2026-04-01',
			'check_out'    => '2026-04-04',
			'adults'       => 2,
			'children'     => 0,
			'guest_id'     => 42,
			'source'       => 'direct',
		];

		$booking = new Booking( [
			'id'             => 100,
			'booking_number' => 'NZL-2026-00001',
			'guest_id'       => 42,
			'room_type_id'   => 1,
			'check_in'       => '2026-04-01',
			'check_out'      => '2026-04-04',
			'nights'         => 3,
			'adults'         => 2,
			'children'       => 0,
			'status'         => Booking::STATUS_PENDING,
			'source'         => 'direct',
			'total_amount'   => 600.00,
			'paid_amount'    => 0,
			'currency'       => 'SAR',
		] );

		// 1. Validation passes.
		$this->validator->shouldReceive( 'validateCreate' )
			->once()
			->with( $data )
			->andReturn( true );

		// 2. Availability check passes.
		$this->availabilityService->shouldReceive( 'isAvailable' )
			->once()
			->with( 1, '2026-04-01', '2026-04-04' )
			->andReturn( true );

		// 3. Pricing returns total.
		$this->pricingService->shouldReceive( 'calculate' )
			->once()
			->with( 1, '2026-04-01', '2026-04-04', 2, 0 )
			->andReturn( [ 'total' => 600.00 ] );

		// 4. Currency setting.
		$this->settings->shouldReceive( 'get' )
			->with( 'currency.default', 'USD' )
			->andReturn( 'SAR' );

		// 5. Booking number generation.
		$this->settings->shouldReceive( 'get' )
			->with( 'bookings.number_prefix', 'NZL' )
			->andReturn( 'NZL' );
		$this->bookingRepo->shouldReceive( 'getNextSequence' )
			->once()
			->andReturn( 1 );

		// 6. Transaction lifecycle.
		$this->bookingRepo->shouldReceive( 'beginTransaction' )->once();
		$this->bookingRepo->shouldReceive( 'commit' )->once();

		// 7. Inventory deduction.
		$this->availabilityService->shouldReceive( 'deductInventory' )
			->once()
			->with( 1, '2026-04-01', '2026-04-04' );

		// 8. Record creation.
		$this->bookingRepo->shouldReceive( 'create' )
			->once()
			->with( Mockery::on( function ( array $record ) {
				return $record['room_type_id'] === 1
					&& $record['guest_id'] === 42
					&& $record['nights'] === 3
					&& $record['total_amount'] === 600.00
					&& $record['status'] === Booking::STATUS_PENDING;
			} ) )
			->andReturn( $booking );

		// 9. Guest booking count increment.
		$this->guestService->shouldReceive( 'incrementBookingCount' )
			->once()
			->with( 42, 600.00 );

		// 10. Audit log.
		$this->bookingRepo->shouldReceive( 'createLog' )
			->once()
			->with( Mockery::on( fn( $log ) => $log['action'] === BookingLog::ACTION_CREATED ) );

		// 11. Notification queued.
		$this->notificationService->shouldReceive( 'queue' )
			->once()
			->with( 'booking_created', Mockery::type( 'array' ) );

		$result = $this->service->createBooking( $data );

		$this->assertInstanceOf( Booking::class, $result );
		$this->assertSame( 100, $result->id );
		$this->assertSame( Booking::STATUS_PENDING, $result->status );
		$this->assertSame( 600.00, $result->total_amount );
	}

	/**
	 * Overbooking throws NoAvailabilityException.
	 */
	public function testOverbookingThrowsNoAvailabilityException(): void {
		$data = [
			'room_type_id' => 1,
			'check_in'     => '2026-04-01',
			'check_out'    => '2026-04-04',
			'adults'       => 2,
			'guest_id'     => 42,
		];

		$this->validator->shouldReceive( 'validateCreate' )
			->once()
			->andReturn( true );

		$this->availabilityService->shouldReceive( 'isAvailable' )
			->once()
			->with( 1, '2026-04-01', '2026-04-04' )
			->andReturn( false );

		// Nothing else should be called.
		$this->bookingRepo->shouldNotReceive( 'beginTransaction' );
		$this->bookingRepo->shouldNotReceive( 'create' );
		$this->availabilityService->shouldNotReceive( 'deductInventory' );

		$this->expectException( NoAvailabilityException::class );

		$this->service->createBooking( $data );
	}
}
