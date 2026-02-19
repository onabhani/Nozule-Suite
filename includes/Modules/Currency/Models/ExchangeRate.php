<?php

namespace Nozule\Modules\Currency\Models;

use Nozule\Core\BaseModel;

/**
 * ExchangeRate model representing a historical exchange rate record.
 */
class ExchangeRate extends BaseModel {

	/**
	 * Attributes that should be cast to specific types.
	 *
	 * @var array<string, string>
	 */
	protected static array $casts = [
		'id'   => 'int',
		'rate' => 'float',
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
