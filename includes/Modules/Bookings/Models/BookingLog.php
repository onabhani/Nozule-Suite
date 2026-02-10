<?php

namespace Venezia\Modules\Bookings\Models;

use Venezia\Core\BaseModel;

/**
 * Booking audit log entry.
 *
 * Records every significant action performed on a booking for auditing
 * purposes (status changes, payment recording, notes, etc.).
 *
 * @property int         $id
 * @property int         $booking_id
 * @property string      $action        e.g. created, confirmed, cancelled, checked_in, checked_out, payment_added, updated, no_show, room_assigned
 * @property string|null $details       Human-readable description or JSON-encoded metadata.
 * @property int|null    $user_id       WordPress user who performed the action (null for system/guest actions).
 * @property string|null $ip_address
 * @property string      $created_at
 */
class BookingLog extends BaseModel {

	// ── Action Constants ────────────────────────────────────────────

	public const ACTION_CREATED       = 'created';
	public const ACTION_CONFIRMED     = 'confirmed';
	public const ACTION_CANCELLED     = 'cancelled';
	public const ACTION_CHECKED_IN    = 'checked_in';
	public const ACTION_CHECKED_OUT   = 'checked_out';
	public const ACTION_PAYMENT_ADDED = 'payment_added';
	public const ACTION_UPDATED       = 'updated';
	public const ACTION_NO_SHOW       = 'no_show';
	public const ACTION_ROOM_ASSIGNED = 'room_assigned';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'         => 'int',
		'booking_id' => 'int',
		'user_id'    => 'int',
	];

	/**
	 * Fill attributes, applying type casts.
	 */
	public function fill( array $attributes ): static {
		foreach ( $attributes as $key => $value ) {
			if ( $value !== null && isset( static::$casts[ $key ] ) ) {
				$value = match ( static::$casts[ $key ] ) {
					'int'   => (int) $value,
					'float' => (float) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}
}
