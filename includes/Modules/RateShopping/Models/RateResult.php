<?php

namespace Nozule\Modules\RateShopping\Models;

use Nozule\Core\BaseModel;

/**
 * RateResult model representing a captured competitor rate for a specific date.
 *
 * @property int    $id
 * @property int    $competitor_id   FK to rate_shop_competitors.
 * @property string $check_date      The stay date being compared (Y-m-d).
 * @property float  $rate            The competitor's rate.
 * @property string $currency        ISO 4217 currency code.
 * @property string $source          Where the rate was obtained (manual, api, scrape).
 * @property string $captured_at     When this rate was recorded.
 * @property string $created_at
 */
class RateResult extends BaseModel {

	// ── Source Constants ────────────────────────────────────────────

	public const SOURCE_MANUAL = 'manual';
	public const SOURCE_API    = 'api';
	public const SOURCE_SCRAPE = 'scrape';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'            => 'int',
		'competitor_id' => 'int',
		'rate'          => 'float',
	];

	/**
	 * Fill attributes, applying type casts.
	 */
	public function fill( array $attributes ): static {
		// Remap DB column name to model attribute.
		if ( isset( $attributes['rate_found'] ) && ! isset( $attributes['rate'] ) ) {
			$attributes['rate'] = $attributes['rate_found'];
			unset( $attributes['rate_found'] );
		}

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

	/**
	 * All valid capture source values.
	 *
	 * @return string[]
	 */
	public static function validSources(): array {
		return [
			self::SOURCE_MANUAL,
			self::SOURCE_API,
			self::SOURCE_SCRAPE,
		];
	}
}
