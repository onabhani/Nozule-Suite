<?php

namespace Nozule\Modules\RateShopping\Models;

use Nozule\Core\BaseModel;

/**
 * Competitor model representing an OTA competitor being tracked.
 *
 * @property int         $id
 * @property string      $name            Competitor name (English).
 * @property string      $name_ar         Competitor name (Arabic).
 * @property string      $source          OTA source (booking_com, expedia, agoda, google_hotels, other).
 * @property int|null    $room_type_match FK to room_types — which of our room types this maps to.
 * @property string      $notes           Free-text notes.
 * @property bool        $is_active
 * @property string      $created_at
 * @property string      $updated_at
 */
class Competitor extends BaseModel {

	// ── Source Constants ────────────────────────────────────────────

	public const SOURCE_BOOKING_COM   = 'booking_com';
	public const SOURCE_EXPEDIA       = 'expedia';
	public const SOURCE_AGODA         = 'agoda';
	public const SOURCE_GOOGLE_HOTELS = 'google_hotels';
	public const SOURCE_OTHER         = 'other';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'              => 'int',
		'room_type_match' => 'int',
		'is_active'       => 'bool',
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
					'bool'  => (bool) (int) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * All valid source values.
	 *
	 * @return string[]
	 */
	public static function validSources(): array {
		return [
			self::SOURCE_BOOKING_COM,
			self::SOURCE_EXPEDIA,
			self::SOURCE_AGODA,
			self::SOURCE_GOOGLE_HOTELS,
			self::SOURCE_OTHER,
		];
	}

	/**
	 * Human-readable source label.
	 */
	public function sourceLabel(): string {
		return match ( $this->source ) {
			self::SOURCE_BOOKING_COM   => 'Booking.com',
			self::SOURCE_EXPEDIA       => 'Expedia',
			self::SOURCE_AGODA         => 'Agoda',
			self::SOURCE_GOOGLE_HOTELS => 'Google Hotels',
			self::SOURCE_OTHER         => 'Other',
			default                    => $this->source ?? '',
		};
	}

	/**
	 * Whether this competitor is active.
	 */
	public function isActive(): bool {
		return (bool) $this->is_active;
	}
}
