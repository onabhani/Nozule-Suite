<?php

namespace Nozule\Modules\RateShopping\Models;

use Nozule\Core\BaseModel;

/**
 * ParityAlert model representing a parity violation detected between
 * our rate and a competitor's rate.
 *
 * @property int         $id
 * @property int         $competitor_id   FK to rate_shop_competitors.
 * @property string      $check_date      The stay date of the comparison (Y-m-d).
 * @property float       $our_rate        Our rate for the date.
 * @property float       $their_rate      Competitor rate for the date.
 * @property float       $difference      Absolute difference (their_rate - our_rate).
 * @property float       $pct_difference  Percentage difference.
 * @property string      $alert_type      undercut|overpriced
 * @property string      $status          unresolved|resolved
 * @property string|null $resolved_at
 * @property int|null    $resolved_by     User ID who resolved.
 * @property string      $created_at
 */
class ParityAlert extends BaseModel {

	// ── Alert Type Constants ───────────────────────────────────────

	public const TYPE_UNDERCUT   = 'undercut';
	public const TYPE_OVERPRICED = 'overpriced';

	// ── Status Constants ───────────────────────────────────────────

	public const STATUS_UNRESOLVED = 'unresolved';
	public const STATUS_RESOLVED   = 'resolved';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'             => 'int',
		'competitor_id'  => 'int',
		'our_rate'       => 'float',
		'their_rate'     => 'float',
		'difference'     => 'float',
		'pct_difference' => 'float',
		'resolved_by'    => 'int',
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
	 * All valid alert types.
	 *
	 * @return string[]
	 */
	public static function validTypes(): array {
		return [
			self::TYPE_UNDERCUT,
			self::TYPE_OVERPRICED,
		];
	}

	/**
	 * All valid statuses.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_UNRESOLVED,
			self::STATUS_RESOLVED,
		];
	}

	/**
	 * Whether this alert is unresolved.
	 */
	public function isUnresolved(): bool {
		return $this->status === self::STATUS_UNRESOLVED;
	}

	/**
	 * Whether this alert has been resolved.
	 */
	public function isResolved(): bool {
		return $this->status === self::STATUS_RESOLVED;
	}
}
