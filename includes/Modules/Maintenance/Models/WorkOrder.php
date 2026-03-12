<?php

namespace Nozule\Modules\Maintenance\Models;

use Nozule\Core\BaseModel;

/**
 * Maintenance Work Order model.
 *
 * @property int         $id
 * @property int|null    $property_id
 * @property int         $room_id
 * @property string      $title
 * @property string|null $description
 * @property string      $category         general|plumbing|electrical|hvac|furniture|appliance
 * @property string      $status           open|in_progress|resolved
 * @property string      $priority         low|normal|high|urgent
 * @property int|null    $assigned_to      User ID of assigned maintenance staff.
 * @property int|null    $reported_by      User ID who reported the issue.
 * @property string|null $started_at
 * @property string|null $resolved_at
 * @property string|null $resolution_notes
 * @property string      $created_at
 * @property string      $updated_at
 */
class WorkOrder extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_OPEN        = 'open';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_RESOLVED    = 'resolved';

	// ── Priority Constants ──────────────────────────────────────────

	public const PRIORITY_LOW    = 'low';
	public const PRIORITY_NORMAL = 'normal';
	public const PRIORITY_HIGH   = 'high';
	public const PRIORITY_URGENT = 'urgent';

	// ── Category Constants ──────────────────────────────────────────

	public const CATEGORY_GENERAL    = 'general';
	public const CATEGORY_PLUMBING   = 'plumbing';
	public const CATEGORY_ELECTRICAL = 'electrical';
	public const CATEGORY_HVAC       = 'hvac';
	public const CATEGORY_FURNITURE  = 'furniture';
	public const CATEGORY_APPLIANCE  = 'appliance';

	// ── Type Casting ────────────────────────────────────────────────

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'property_id',
		'room_id',
		'assigned_to',
		'reported_by',
	];

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_OPEN,
			self::STATUS_IN_PROGRESS,
			self::STATUS_RESOLVED,
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
	 * All valid category values.
	 *
	 * @return string[]
	 */
	public static function validCategories(): array {
		return [
			self::CATEGORY_GENERAL,
			self::CATEGORY_PLUMBING,
			self::CATEGORY_ELECTRICAL,
			self::CATEGORY_HVAC,
			self::CATEGORY_FURNITURE,
			self::CATEGORY_APPLIANCE,
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

	public function isOpen(): bool {
		return $this->status === self::STATUS_OPEN;
	}

	public function isInProgress(): bool {
		return $this->status === self::STATUS_IN_PROGRESS;
	}

	public function isResolved(): bool {
		return $this->status === self::STATUS_RESOLVED;
	}
}
