<?php

namespace Nozule\Modules\Channels\Models;

use Nozule\Core\BaseModel;

/**
 * Channel Sync Log model.
 *
 * Represents a single sync operation record for audit and debugging.
 *
 * @property int    $id
 * @property string $channel_name       OTA identifier.
 * @property string $direction          'push' or 'pull'.
 * @property string $sync_type          'rates', 'availability', or 'reservations'.
 * @property string $status             'pending', 'success', 'partial', 'failed'.
 * @property int    $records_processed  Number of records processed.
 * @property string $error_message      Error details on failure.
 * @property string $started_at
 * @property string $completed_at
 * @property string $created_at
 */
class ChannelSyncLog extends BaseModel {

	/** @var string Status: operation is in progress. */
	const STATUS_PENDING = 'pending';

	/** @var string Status: operation completed successfully. */
	const STATUS_SUCCESS = 'success';

	/** @var string Status: operation completed with partial results. */
	const STATUS_PARTIAL = 'partial';

	/** @var string Status: operation failed. */
	const STATUS_FAILED = 'failed';

	/** @var string Direction: pushing data to OTA. */
	const DIRECTION_PUSH = 'push';

	/** @var string Direction: pulling data from OTA. */
	const DIRECTION_PULL = 'pull';

	/** @var string Sync type: availability. */
	const TYPE_AVAILABILITY = 'availability';

	/** @var string Sync type: rates. */
	const TYPE_RATES = 'rates';

	/** @var string Sync type: reservations. */
	const TYPE_RESERVATIONS = 'reservations';

	/**
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'records_processed',
	];

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

	/**
	 * Check whether this log entry represents a successful sync.
	 */
	public function isSuccess(): bool {
		return $this->status === self::STATUS_SUCCESS;
	}

	/**
	 * Check whether this log entry represents a failed sync.
	 */
	public function isFailed(): bool {
		return $this->status === self::STATUS_FAILED;
	}

	/**
	 * Get the duration of the sync operation in seconds.
	 *
	 * @return int|null Duration in seconds, or null if not completed.
	 */
	public function getDurationSeconds(): ?int {
		if ( empty( $this->started_at ) || empty( $this->completed_at ) ) {
			return null;
		}

		$start = strtotime( $this->started_at );
		$end   = strtotime( $this->completed_at );

		if ( $start === false || $end === false ) {
			return null;
		}

		return max( 0, $end - $start );
	}

	/**
	 * Get all valid status values.
	 *
	 * @return string[]
	 */
	public static function getStatuses(): array {
		return [
			self::STATUS_PENDING,
			self::STATUS_SUCCESS,
			self::STATUS_PARTIAL,
			self::STATUS_FAILED,
		];
	}

	/**
	 * Get all valid direction values.
	 *
	 * @return string[]
	 */
	public static function getDirections(): array {
		return [
			self::DIRECTION_PUSH,
			self::DIRECTION_PULL,
		];
	}

	/**
	 * Get all valid sync type values.
	 *
	 * @return string[]
	 */
	public static function getSyncTypes(): array {
		return [
			self::TYPE_AVAILABILITY,
			self::TYPE_RATES,
			self::TYPE_RESERVATIONS,
		];
	}
}
