<?php
/**
 * Stub for NotificationService that widens the queue() parameter types.
 *
 * The production BookingService calls queue( string $type, array $data )
 * but the real NotificationService::queue() signature expects
 * ( object $booking, string $type ). This stub widens the first parameter
 * to `mixed` (valid contravariance) so Mockery mocks work without TypeError.
 */

namespace Nozule\Tests\Stubs;

use Nozule\Modules\Notifications\Models\Notification;
use Nozule\Modules\Notifications\Services\NotificationService;

class NotificationServiceStub extends NotificationService {

	public function __construct() {
		// Skip parent constructor — not needed for mocking.
	}

	public function queue( mixed $booking, mixed $type = '', mixed $channel = 'email' ): ?Notification {
		return null;
	}
}
