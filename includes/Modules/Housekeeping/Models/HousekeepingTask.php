<?php

namespace Nozule\Modules\Housekeeping\Models;

use Nozule\Core\BaseModel;

/**
 * Housekeeping Task model.
 *
 * Represents a single housekeeping task assigned to a room.
 *
 * @property int         $id
 * @property int         $room_id
 * @property int|null    $assigned_to       User ID of the assigned housekeeper.
 * @property string      $status            dirty|clean|inspected|out_of_order
 * @property string      $priority          low|normal|high|urgent
 * @property string      $task_type         checkout_clean|stay_over|deep_clean|inspection|turndown
 * @property string|null $notes
 * @property string|null $started_at
 * @property string|null $completed_at
 * @property int|null    $created_by        User ID who created the task.
 * @property string      $created_at
 * @property string      $updated_at
 */
class HousekeepingTask extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_DIRTY        = 'dirty';
	public const STATUS_CLEAN        = 'clean';
	public const STATUS_INSPECTED    = 'inspected';
	public const STATUS_OUT_OF_ORDER = 'out_of_order';

	// ── Priority Constants ──────────────────────────────────────────

	public const PRIORITY_LOW    = 'low';
	public const PRIORITY_NORMAL = 'normal';
	public const PRIORITY_HIGH   = 'high';
	public const PRIORITY_URGENT = 'urgent';

	// ── Task Type Constants ─────────────────────────────────────────

	public const TYPE_CHECKOUT_CLEAN = 'checkout_clean';
	public const TYPE_STAY_OVER      = 'stay_over';
	public const TYPE_DEEP_CLEAN     = 'deep_clean';
	public const TYPE_INSPECTION     = 'inspection';
	public const TYPE_TURNDOWN       = 'turndown';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'room_id',
		'assigned_to',
		'created_by',
	];

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_DIRTY,
			self::STATUS_CLEAN,
			self::STATUS_INSPECTED,
			self::STATUS_OUT_OF_ORDER,
		];
	}

	/**
	 * All valid priority values.
	 *
	 * @return string[]
	 */
	public static function validPriorities(): array {
		return [
			self::PRIORITY_LOW,
			self::PRIORITY_NORMAL,
			self::PRIORITY_HIGH,
			self::PRIORITY_URGENT,
		];
	}

	/**
	 * All valid task type values.
	 *
	 * @return string[]
	 */
	public static function validTaskTypes(): array {
		return [
			self::TYPE_CHECKOUT_CLEAN,
			self::TYPE_STAY_OVER,
			self::TYPE_DEEP_CLEAN,
			self::TYPE_INSPECTION,
			self::TYPE_TURNDOWN,
		];
	}

	/**
	 * Create from a database row with type casting.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		return new static( $data );
	}

	// ── Status Helpers ──────────────────────────────────────────────

	/**
	 * Check whether this task is in dirty status.
	 */
	public function isDirty(): bool {
		return $this->status === self::STATUS_DIRTY;
	}

	/**
	 * Check whether this task is in clean status.
	 */
	public function isClean(): bool {
		return $this->status === self::STATUS_CLEAN;
	}

	/**
	 * Check whether this task has been completed (inspected).
	 */
	public function isCompleted(): bool {
		return $this->status === self::STATUS_INSPECTED;
	}

	/**
	 * Check whether this task is still open (not yet inspected).
	 */
	public function isOpen(): bool {
		return in_array( $this->status, [ self::STATUS_DIRTY, self::STATUS_CLEAN ], true );
	}
}
