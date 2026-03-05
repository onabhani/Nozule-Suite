<?php

namespace Nozule\Modules\Employees\Models;

use Nozule\Core\BaseModel;

/**
 * Employee model representing a staff member.
 */
class Employee extends BaseModel {

	protected static array $casts = [
		'id'         => 'int',
		'wp_user_id' => 'int',
		'is_active'  => 'bool',
	];

	/**
	 * Fill attributes, applying type casts and JSON decoding.
	 */
	public function fill( array $attributes ): static {
		foreach ( $attributes as $key => $value ) {
			if ( $key === 'capabilities' && is_string( $value ) ) {
				$value = json_decode( $value, true ) ?: [];
			} elseif ( $value !== null && isset( static::$casts[ $key ] ) ) {
				$value = match ( static::$casts[ $key ] ) {
					'int'  => (int) $value,
					'bool' => (bool) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * Convert to array.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		if ( isset( $data['is_active'] ) ) {
			$data['is_active'] = (bool) $data['is_active'];
		}
		if ( isset( $data['capabilities'] ) && ! is_array( $data['capabilities'] ) ) {
			if ( is_string( $data['capabilities'] ) ) {
				$decoded = json_decode( $data['capabilities'], true );
				$data['capabilities'] = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : [];
			} else {
				$data['capabilities'] = [];
			}
		}

		return $data;
	}
}
