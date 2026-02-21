<?php

namespace Nozule\Modules\Forecasting\Models;

use Nozule\Core\BaseModel;

/**
 * Demand Forecast model.
 *
 * Represents a single day's predicted demand for a room type.
 *
 * @property int         $id
 * @property int         $room_type_id
 * @property string      $forecast_date      Y-m-d
 * @property float       $predicted_occupancy 0.00 – 100.00
 * @property float       $predicted_adr       Average daily rate prediction.
 * @property float       $confidence          0.00 – 1.00
 * @property float       $suggested_rate      Algorithm-suggested rate.
 * @property string|null $factors             JSON-encoded contributing factors.
 * @property string      $created_at
 */
class DemandForecast extends BaseModel {

	/** @var array<string, string> */
	protected static array $casts = [
		'id'                  => 'int',
		'room_type_id'        => 'int',
		'predicted_occupancy' => 'float',
		'predicted_adr'       => 'float',
		'confidence'          => 'float',
		'suggested_rate'      => 'float',
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

	/**
	 * Get the decoded factors array.
	 *
	 * @return array<string, mixed>
	 */
	public function getFactors(): array {
		$raw = $this->attributes['factors'] ?? null;

		if ( empty( $raw ) ) {
			return [];
		}

		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : [];
		}

		return is_array( $raw ) ? $raw : [];
	}

	/**
	 * Convert to array with factors decoded.
	 */
	public function toArray(): array {
		$data            = parent::toArray();
		$data['factors'] = $this->getFactors();

		return $data;
	}
}
