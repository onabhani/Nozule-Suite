<?php

namespace Nozule\Modules\Reviews\Models;

use Nozule\Core\BaseModel;

/**
 * Review Request model.
 *
 * Represents a post-checkout review solicitation email request.
 *
 * @property int         $id
 * @property int         $booking_id      FK to bookings.
 * @property int         $guest_id        FK to guests.
 * @property string      $to_email        Recipient address.
 * @property string      $status          queued|sent|failed|clicked
 * @property string      $review_platform google|tripadvisor|direct
 * @property string|null $send_after      Earliest time to send (delay).
 * @property string|null $sent_at         Timestamp of successful delivery.
 * @property string|null $clicked_at      Timestamp of review link click.
 * @property string      $created_at
 */
class ReviewRequest extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_QUEUED  = 'queued';
	public const STATUS_SENT    = 'sent';
	public const STATUS_FAILED  = 'failed';
	public const STATUS_CLICKED = 'clicked';

	// ── Platform Constants ──────────────────────────────────────────

	public const PLATFORM_GOOGLE      = 'google';
	public const PLATFORM_TRIPADVISOR = 'tripadvisor';
	public const PLATFORM_DIRECT      = 'direct';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'         => 'int',
		'booking_id' => 'int',
		'guest_id'   => 'int',
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

	// ── Status Helpers ──────────────────────────────────────────────

	/**
	 * Whether the request is still queued.
	 */
	public function isQueued(): bool {
		return $this->status === self::STATUS_QUEUED;
	}

	/**
	 * Whether the request was successfully sent.
	 */
	public function isSent(): bool {
		return $this->status === self::STATUS_SENT;
	}

	/**
	 * Whether the request send failed.
	 */
	public function isFailed(): bool {
		return $this->status === self::STATUS_FAILED;
	}

	/**
	 * Whether the review link was clicked.
	 */
	public function isClicked(): bool {
		return $this->status === self::STATUS_CLICKED;
	}

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_QUEUED,
			self::STATUS_SENT,
			self::STATUS_FAILED,
			self::STATUS_CLICKED,
		];
	}

	/**
	 * All valid platform values.
	 *
	 * @return string[]
	 */
	public static function validPlatforms(): array {
		return [
			self::PLATFORM_GOOGLE,
			self::PLATFORM_TRIPADVISOR,
			self::PLATFORM_DIRECT,
		];
	}
}
