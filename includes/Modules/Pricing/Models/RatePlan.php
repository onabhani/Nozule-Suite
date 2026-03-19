<?php

namespace Nozule\Modules\Pricing\Models;

use Nozule\Core\BaseModel;

/**
 * Rate Plan model.
 *
 * Represents a pricing strategy that can be applied to room types.
 * Rate plans define modifiers (percentage or fixed) on top of the
 * room type's base price, along with policies and restrictions.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $code             Unique short code (e.g. "BAR", "PROMO_SUMMER").
 * @property string|null $description
 * @property int|null    $room_type_id     Null means applicable to all room types.
 * @property string      $modifier_type    'percentage' | 'fixed' | 'absolute'
 * @property float       $modifier_value   The modifier amount.
 * @property int         $min_stay         Minimum nights required for this plan.
 * @property int         $max_stay         Maximum nights allowed (0 = unlimited).
 * @property bool        $is_refundable
 * @property string      $cancellation_policy  Free-text or structured cancellation rules.
 * @property bool        $includes_breakfast
 * @property int         $priority         Lower number = higher priority for default selection.
 * @property bool        $is_default       Whether this is the default rate plan for its room type.
 * @property string      $status           'active' | 'inactive'
 * @property string      $valid_from       Y-m-d or null for always valid.
 * @property string      $valid_until      Y-m-d or null for no expiry.
 * @property string      $created_at
 * @property string      $updated_at
 */
class RatePlan extends BaseModel {

	/**
	 * Fields that should be cast to booleans.
	 *
	 * @var string[]
	 */
	protected static array $boolFields = [
		'is_refundable',
		'is_default',
	];

	/**
	 * Check whether this rate plan is currently active.
	 */

	protected static array $casts = [
		'id' => 'int',
		'room_type_id' => 'int',
		'min_stay' => 'int',
		'max_stay' => 'int',
		'modifier_value' => 'float',
	];

	/**
	 * Create from a database row, remapping DB column names to model attributes.
	 *
	 * DB uses: price_modifier, cancellation_hours, valid_to
	 * Model uses: modifier_value, cancellation_policy, valid_until
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		if ( array_key_exists( 'price_modifier', $data ) && ! array_key_exists( 'modifier_value', $data ) ) {
			$data['modifier_value'] = $data['price_modifier'];
			unset( $data['price_modifier'] );
		}
		if ( array_key_exists( 'cancellation_hours', $data ) && ! array_key_exists( 'cancellation_policy', $data ) ) {
			$data['cancellation_policy'] = $data['cancellation_hours'];
			unset( $data['cancellation_hours'] );
		}
		if ( array_key_exists( 'valid_to', $data ) && ! array_key_exists( 'valid_until', $data ) ) {
			$data['valid_until'] = $data['valid_to'];
			unset( $data['valid_to'] );
		}

		return new static( $data );
	}

	public function isActive(): bool {
		return $this->status === 'active';
	}

	/**
	 * Convert to a public-facing array, mapping DB column names to API field names.
	 */
	public function toPublicArray(): array {
		$data = parent::toArray();

		if ( array_key_exists( 'price_modifier', $data ) ) {
			$data['modifier_value'] = $data['price_modifier'];
			unset( $data['price_modifier'] );
		}
		if ( array_key_exists( 'cancellation_hours', $data ) ) {
			$data['cancellation_policy'] = $data['cancellation_hours'];
			unset( $data['cancellation_hours'] );
		}
		if ( array_key_exists( 'valid_to', $data ) ) {
			$data['valid_until'] = $data['valid_to'];
			unset( $data['valid_to'] );
		}

		return $data;
	}

	/**
	 * Check whether this rate plan is valid for a given date.
	 */
	public function isValidForDate( string $date ): bool {
		if ( ! $this->isActive() ) {
			return false;
		}

		if ( $this->valid_from && $date < $this->valid_from ) {
			return false;
		}

		if ( $this->valid_until && $date > $this->valid_until ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether a given number of nights satisfies the stay constraints.
	 */
	public function isValidForStayLength( int $nights ): bool {
		if ( $nights < $this->min_stay ) {
			return false;
		}

		if ( $this->max_stay > 0 && $nights > $this->max_stay ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether this plan applies to a specific room type.
	 *
	 * A null room_type_id means the plan applies to all room types.
	 */
	public function appliesToRoomType( int $roomTypeId ): bool {
		return $this->room_type_id === null || $this->room_type_id === $roomTypeId;
	}
}
