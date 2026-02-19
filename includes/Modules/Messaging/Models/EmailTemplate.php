<?php

namespace Nozule\Modules\Messaging\Models;

use Nozule\Core\BaseModel;

/**
 * Email Template model.
 *
 * Represents a reusable email template with bilingual support (EN/AR)
 * and variable placeholder substitution.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug           Unique template identifier.
 * @property string|null $trigger_event  Automatic trigger (e.g. booking_confirmed).
 * @property string      $subject        English subject line.
 * @property string|null $subject_ar     Arabic subject line.
 * @property string      $body           English body (HTML).
 * @property string|null $body_ar        Arabic body (HTML).
 * @property string|null $variables      JSON-encoded list of supported placeholder names.
 * @property int         $is_active      Whether the template is enabled (1 = active).
 * @property string      $created_at
 * @property string      $updated_at
 */
class EmailTemplate extends BaseModel {

	/** @var array<string, string> */
	protected static array $casts = [
		'id'        => 'int',
		'is_active' => 'int',
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
	 * Create from a database row, decoding JSON variables.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		// Decode variables JSON.
		if ( isset( $data['variables'] ) && is_string( $data['variables'] ) ) {
			$decoded            = json_decode( $data['variables'], true );
			$data['variables']  = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : [];
		} elseif ( ! isset( $data['variables'] ) ) {
			$data['variables'] = [];
		}

		return ( new static() )->fill( $data );
	}

	// ── Helpers ─────────────────────────────────────────────────────

	/**
	 * Whether the template is active.
	 */
	public function isActive(): bool {
		return (int) ( $this->attributes['is_active'] ?? 0 ) === 1;
	}

	/**
	 * Get the supported template variables as an array.
	 *
	 * @return string[]
	 */
	public function getVariables(): array {
		$vars = $this->attributes['variables'] ?? [];

		if ( is_string( $vars ) ) {
			$decoded = json_decode( $vars, true );
			return is_array( $decoded ) ? $decoded : [];
		}

		return is_array( $vars ) ? $vars : [];
	}

	/**
	 * Get all recognised trigger events.
	 *
	 * @return string[]
	 */
	public static function getTriggerEvents(): array {
		return [
			'booking_confirmed',
			'booking_checked_in',
			'booking_checked_out',
			'booking_cancelled',
			'booking_created',
			'payment_received',
		];
	}

	// ── Serialisation ───────────────────────────────────────────────

	/**
	 * Convert to array, encoding variables back to JSON for storage.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		// Encode variables for database persistence.
		if ( isset( $data['variables'] ) && is_array( $data['variables'] ) ) {
			$data['variables'] = wp_json_encode( $data['variables'] );
		}

		return $data;
	}
}
