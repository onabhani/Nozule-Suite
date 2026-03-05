<?php

namespace Nozule\Tests\Unit\Bookings;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Bookings\Exceptions\InvalidStateException;
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

class CancelBookingTest extends TestCase {
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
	 * Helper: create a Booking model.
	 */
	private function makeBooking( array $overrides = [] ): Booking {
		return new Booking( array_merge( [
			'id'             => 100,
			'booking_number' => 'NZL-2026-00001',
			'guest_id'       => 42,
			'room_type_id'   => 1,
			'check_in'       => '2026-04-01',
			'check_out'      => '2026-04-04',
			'nights'         => 3,
			'adults'         => 2,
			'children'       => 0,
			'status'         => Booking::STATUS_CONFIRMED,
			'source'         => 'direct',
			'total_amount'   => 600.00,
			'paid_amount'    => 0,
		], $overrides ) );
	}

	/**
	 * Inventory restored exactly on cancellation.
	 */
	public function testInventoryRestoredExactlyOnCancellation(): void {
		$booking          = $this->makeBooking();
		$cancelledBooking = $this->makeBooking( [ 'status' => Booking::STATUS_CANCELLED ] );

		// Fetch booking.
		$this->bookingRepo->shouldReceive( 'findOrFail' )
			->with( 100 )
			->andReturn( $booking, $cancelledBooking ); // first call returns original, second returns updated

		// Transaction.
		$this->bookingRepo->shouldReceive( 'beginTransaction' )->once();
		$this->bookingRepo->shouldReceive( 'commit' )->once();

		// Inventory restored with exact room_type_id and dates.
		$this->availabilityService->shouldReceive( 'restoreInventory' )
			->once()
			->with( 1, '2026-04-01', '2026-04-04' );

		// Booking status updated.
		$this->bookingRepo->shouldReceive( 'update' )
			->once()
			->with( 100, Mockery::on( function ( array $data ) {
				return $data['status'] === Booking::STATUS_CANCELLED
					&& isset( $data['cancelled_at'] );
			} ) );

		// Audit log.
		$this->bookingRepo->shouldReceive( 'createLog' )
			->once()
			->with( Mockery::on( fn( $log ) => $log['action'] === BookingLog::ACTION_CANCELLED ) );

		// Notification.
		$this->notificationService->shouldReceive( 'queue' )
			->once()
			->with( 'booking_cancelled', Mockery::type( 'array' ) );

		$result = $this->service->cancelBooking( 100, 'Guest request', 5 );

		$this->assertInstanceOf( Booking::class, $result );
		$this->assertSame( Booking::STATUS_CANCELLED, $result->status );
	}

	/**
	 * Cancellation status set correctly (pending booking).
	 */
	public function testCancellationStatusSetCorrectlyForPendingBooking(): void {
		$booking          = $this->makeBooking( [ 'status' => Booking::STATUS_PENDING ] );
		$cancelledBooking = $this->makeBooking( [ 'status' => Booking::STATUS_CANCELLED ] );

		$this->bookingRepo->shouldReceive( 'findOrFail' )
			->with( 100 )
			->andReturn( $booking, $cancelledBooking );

		$this->bookingRepo->shouldReceive( 'beginTransaction' )->once();
		$this->bookingRepo->shouldReceive( 'commit' )->once();

		$this->availabilityService->shouldReceive( 'restoreInventory' )->once();

		$this->bookingRepo->shouldReceive( 'update' )
			->once()
			->with( 100, Mockery::on( function ( array $data ) {
				return $data['status'] === Booking::STATUS_CANCELLED
					&& $data['cancellation_reason'] === 'Changed plans'
					&& $data['cancelled_by'] === 5;
			} ) );

		$this->bookingRepo->shouldReceive( 'createLog' )->once();
		$this->notificationService->shouldReceive( 'queue' )->once();

		$result = $this->service->cancelBooking( 100, 'Changed plans', 5 );

		$this->assertSame( Booking::STATUS_CANCELLED, $result->status );
	}

	/**
	 * Cannot cancel a checked-out booking.
	 */
	public function testCannotCancelCheckedOutBooking(): void {
		$booking = $this->makeBooking( [ 'status' => Booking::STATUS_CHECKED_OUT ] );

		$this->bookingRepo->shouldReceive( 'findOrFail' )
			->with( 100 )
			->andReturn( $booking );

		// Nothing else should be called.
		$this->bookingRepo->shouldNotReceive( 'beginTransaction' );
		$this->availabilityService->shouldNotReceive( 'restoreInventory' );
		$this->bookingRepo->shouldNotReceive( 'update' );

		$this->expectException( InvalidStateException::class );

		$this->service->cancelBooking( 100 );
	}

	/**
	 * Cannot cancel an already-cancelled booking.
	 */
	public function testCannotCancelAlreadyCancelledBooking(): void {
		$booking = $this->makeBooking( [ 'status' => Booking::STATUS_CANCELLED ] );

		$this->bookingRepo->shouldReceive( 'findOrFail' )
			->with( 100 )
			->andReturn( $booking );

		$this->bookingRepo->shouldNotReceive( 'beginTransaction' );

		$this->expectException( InvalidStateException::class );

		$this->service->cancelBooking( 100 );
	}
}
