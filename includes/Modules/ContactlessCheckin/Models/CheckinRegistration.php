<?php

namespace Nozule\Modules\ContactlessCheckin\Models;

use Nozule\Core\BaseModel;

/**
 * Contactless check-in registration model.
 *
 * @property int         $id
 * @property int         $booking_id
 * @property int         $guest_id
 * @property int|null    $property_id
 * @property string      $token
 * @property string      $status            pending|submitted|approved|rejected
 * @property array|null  $guest_details     JSON: confirmed name, phone, nationality, etc.
 * @property string|null $room_preference
 * @property string|null $special_requests
 * @property array|null  $document_ids      JSON: array of nzl_guest_documents IDs uploaded.
 * @property string|null $signature_path
 * @property string      $expires_at
 * @property string|null $submitted_at
 * @property int|null    $reviewed_by
 * @property string|null $reviewed_at
 * @property string      $created_at
 * @property string      $updated_at
 */
class CheckinRegistration extends BaseModel {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_SUBMITTED = 'submitted';
	public const STATUS_APPROVED  = 'approved';
	public const STATUS_REJECTED  = 'rejected';

	/** @var string[] */
	protected static array $intFields = [
		'id',
		'booking_id',
		'guest_id',
		'property_id',
		'reviewed_by',
	];

	/** @var string[] */
	protected static array $jsonFields = [
		'guest_details',
		'document_ids',
	];

	/**
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_PENDING,
			self::STATUS_SUBMITTED,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
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

		foreach ( static::$jsonFields as $field ) {
			if ( isset( $data[ $field ] ) && is_string( $data[ $field ] ) ) {
				$data[ $field ] = json_decode( $data[ $field ], true ) ?: [];
			}
		}

		return new static( $data );
	}

	public function isPending(): bool {
		return $this->status === self::STATUS_PENDING;
	}

	public function isSubmitted(): bool {
		return $this->status === self::STATUS_SUBMITTED;
	}

	public function isApproved(): bool {
		return $this->status === self::STATUS_APPROVED;
	}

	public function isExpired(): bool {
		return strtotime( $this->expires_at ) < time();
	}
}
