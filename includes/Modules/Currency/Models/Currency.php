<?php

namespace Nozule\Modules\Currency\Models;

use Nozule\Core\BaseModel;

/**
 * Currency model representing a supported currency.
 */
class Currency extends BaseModel {

	/**
	 * Attributes that should be cast to specific types.
	 *
	 * @var array<string, string>
	 */
	protected static array $casts = [
		'id'             => 'int',
		'decimal_places' => 'int',
		'exchange_rate'  => 'float',
		'is_default'     => 'bool',
		'is_active'      => 'bool',
		'sort_order'     => 'int',
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
					'bool'  => (bool) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * Check if this currency is active.
	 */
	public function isActive(): bool {
		return (bool) ( $this->attributes['is_active'] ?? false );
	}

	/**
	 * Check if this currency is the default.
	 */
	public function isDefault(): bool {
		return (bool) ( $this->attributes['is_default'] ?? false );
	}

	/**
	 * Format a monetary amount using this currency's symbol and decimal places.
	 *
	 * @param float $amount The amount to format.
	 * @return string Formatted amount with symbol (e.g., "$1,234.56").
	 */
	public function formatAmount( float $amount ): string {
		$decimal_places = $this->attributes['decimal_places'] ?? 2;
		$symbol         = $this->attributes['symbol'] ?? '';

		$formatted = number_format( $amount, $decimal_places, '.', ',' );

		return $symbol . $formatted;
	}

	/**
	 * Convert to array.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		// Ensure boolean values are properly represented.
		if ( isset( $data['is_default'] ) ) {
			$data['is_default'] = (bool) $data['is_default'];
		}
		if ( isset( $data['is_active'] ) ) {
			$data['is_active'] = (bool) $data['is_active'];
		}

		return $data;
	}
}
