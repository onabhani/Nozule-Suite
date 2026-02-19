<?php

namespace Nozule\Modules\Audit\Models;

use Nozule\Core\BaseModel;

/**
 * Night Audit model.
 *
 * Represents a single nightly audit snapshot capturing room occupancy,
 * revenue figures, guest movement, and payment collection totals.
 *
 * @property int         $id
 * @property string      $audit_date          Y-m-d (unique per day).
 * @property int         $total_rooms
 * @property int         $occupied_rooms
 * @property int         $available_rooms
 * @property int         $out_of_order_rooms
 * @property float       $occupancy_rate      Percentage (0.00 – 100.00).
 * @property float       $room_revenue
 * @property float       $other_revenue
 * @property float       $total_revenue
 * @property float       $adr                 Average Daily Rate.
 * @property float       $revpar              Revenue Per Available Room.
 * @property int         $arrivals
 * @property int         $departures
 * @property int         $no_shows
 * @property int         $walk_ins
 * @property int         $cancellations
 * @property int         $total_guests
 * @property float       $cash_collected
 * @property float       $card_collected
 * @property float       $other_collected
 * @property string|null $notes
 * @property int         $run_by              WordPress user ID who ran the audit.
 * @property string      $run_at              Y-m-d H:i:s
 * @property string      $status              completed|failed
 * @property string      $created_at
 */
class NightAudit extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED    = 'failed';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var array<string, string> */
	protected static array $casts = [
		'id'                => 'int',
		'total_rooms'       => 'int',
		'occupied_rooms'    => 'int',
		'available_rooms'   => 'int',
		'out_of_order_rooms'=> 'int',
		'arrivals'          => 'int',
		'departures'        => 'int',
		'no_shows'          => 'int',
		'walk_ins'          => 'int',
		'cancellations'     => 'int',
		'total_guests'      => 'int',
		'run_by'            => 'int',
		'occupancy_rate'    => 'float',
		'room_revenue'      => 'float',
		'other_revenue'     => 'float',
		'total_revenue'     => 'float',
		'adr'               => 'float',
		'revpar'            => 'float',
		'cash_collected'    => 'float',
		'card_collected'    => 'float',
		'other_collected'   => 'float',
	];

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_COMPLETED,
			self::STATUS_FAILED,
		];
	}

	// ── Overrides ───────────────────────────────────────────────────

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
	 * Whether this audit completed successfully.
	 */
	public function isCompleted(): bool {
		return $this->status === self::STATUS_COMPLETED;
	}

	/**
	 * Whether this audit failed.
	 */
	public function isFailed(): bool {
		return $this->status === self::STATUS_FAILED;
	}

	// ── Computed Helpers ────────────────────────────────────────────

	/**
	 * Get the total amount collected across all payment methods.
	 */
	public function getTotalCollected(): float {
		return round(
			(float) ( $this->attributes['cash_collected'] ?? 0.0 )
			+ (float) ( $this->attributes['card_collected'] ?? 0.0 )
			+ (float) ( $this->attributes['other_collected'] ?? 0.0 ),
			2
		);
	}
}
