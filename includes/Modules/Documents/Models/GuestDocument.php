<?php

namespace Nozule\Modules\Documents\Models;

use Nozule\Core\BaseModel;

/**
 * Guest Document model representing an identity document (passport, national ID, etc.).
 *
 * @property int         $id
 * @property int         $guest_id
 * @property string      $document_type
 * @property string|null $document_number
 * @property string|null $first_name
 * @property string|null $first_name_ar
 * @property string|null $last_name
 * @property string|null $last_name_ar
 * @property string|null $nationality
 * @property string|null $issuing_country
 * @property string|null $issue_date
 * @property string|null $expiry_date
 * @property string|null $date_of_birth
 * @property string|null $gender
 * @property string|null $mrz_line1
 * @property string|null $mrz_line2
 * @property string|null $file_path
 * @property string|null $file_type
 * @property string|null $thumbnail_path
 * @property string|null $ocr_data
 * @property string      $ocr_status
 * @property bool        $verified
 * @property int|null    $verified_by
 * @property string|null $verified_at
 * @property string|null $notes
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class GuestDocument extends BaseModel {

	// Document type constants.
	public const TYPE_PASSPORT         = 'passport';
	public const TYPE_NATIONAL_ID      = 'national_id';
	public const TYPE_DRIVING_LICENSE   = 'driving_license';
	public const TYPE_RESIDENCE_PERMIT  = 'residence_permit';
	public const TYPE_OTHER             = 'other';

	// OCR status constants.
	public const OCR_NONE      = 'none';
	public const OCR_PENDING   = 'pending';
	public const OCR_COMPLETED = 'completed';
	public const OCR_FAILED    = 'failed';

	/**
	 * Allowed document types.
	 *
	 * @var string[]
	 */
	public const ALLOWED_TYPES = [
		self::TYPE_PASSPORT,
		self::TYPE_NATIONAL_ID,
		self::TYPE_DRIVING_LICENSE,
		self::TYPE_RESIDENCE_PERMIT,
		self::TYPE_OTHER,
	];

	/**
	 * Allowed OCR statuses.
	 *
	 * @var string[]
	 */
	public const ALLOWED_OCR_STATUSES = [
		self::OCR_NONE,
		self::OCR_PENDING,
		self::OCR_COMPLETED,
		self::OCR_FAILED,
	];

	/**
	 * Attributes that should be cast to specific types.
	 *
	 * @var array<string, string>
	 */
	protected static array $casts = [
		'id'          => 'int',
		'guest_id'    => 'int',
		'verified'    => 'bool',
		'verified_by' => 'int',
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
	 * Create from a database row with proper type casting.
	 */
	public static function fromRow( object $row ): static {
		return new static( (array) $row );
	}

	/**
	 * Check if the document has been verified.
	 */
	public function isVerified(): bool {
		return (bool) ( $this->attributes['verified'] ?? false );
	}

	/**
	 * Check if the document has expired.
	 */
	public function isExpired(): bool {
		$expiry = $this->attributes['expiry_date'] ?? null;

		if ( empty( $expiry ) ) {
			return false;
		}

		return strtotime( $expiry ) < strtotime( 'today' );
	}

	/**
	 * Get the document holder's full name.
	 */
	public function getFullName(): string {
		return trim(
			( $this->attributes['first_name'] ?? '' ) . ' ' . ( $this->attributes['last_name'] ?? '' )
		);
	}

	/**
	 * Get the document holder's full name in Arabic.
	 */
	public function getFullNameAr(): string {
		return trim(
			( $this->attributes['first_name_ar'] ?? '' ) . ' ' . ( $this->attributes['last_name_ar'] ?? '' )
		);
	}

	/**
	 * Get the OCR data decoded from JSON.
	 *
	 * @return array|null Decoded OCR data or null if not set.
	 */
	public function getOcrData(): ?array {
		$raw = $this->attributes['ocr_data'] ?? null;

		if ( $raw === null || $raw === '' ) {
			return null;
		}

		if ( is_array( $raw ) ) {
			return $raw;
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Get an attribute with special handling for computed fields.
	 *
	 * @return mixed
	 */
	public function __get( string $name ) {
		if ( $name === 'full_name' ) {
			return $this->getFullName();
		}

		if ( $name === 'full_name_ar' ) {
			return $this->getFullNameAr();
		}

		return parent::__get( $name );
	}

	/**
	 * Check if attribute exists, including computed attributes.
	 */
	public function __isset( string $name ): bool {
		if ( $name === 'full_name' ) {
			return isset( $this->attributes['first_name'] ) || isset( $this->attributes['last_name'] );
		}

		if ( $name === 'full_name_ar' ) {
			return isset( $this->attributes['first_name_ar'] ) || isset( $this->attributes['last_name_ar'] );
		}

		return parent::__isset( $name );
	}

	/**
	 * Convert to array, including computed fields and decoded OCR data.
	 */
	public function toArray(): array {
		$data = parent::toArray();

		$data['full_name']    = $this->getFullName();
		$data['full_name_ar'] = $this->getFullNameAr();
		$data['is_verified']  = $this->isVerified();
		$data['is_expired']   = $this->isExpired();
		$data['ocr_data']     = $this->getOcrData();

		return $data;
	}

	/**
	 * Get fields suitable for database insertion/update.
	 */
	public function toDatabaseArray(): array {
		$data = parent::toArray();

		// Encode ocr_data as JSON for storage.
		if ( isset( $data['ocr_data'] ) && is_array( $data['ocr_data'] ) ) {
			$data['ocr_data'] = wp_json_encode( $data['ocr_data'] );
		}

		// Remove computed fields.
		unset( $data['full_name'], $data['full_name_ar'], $data['is_verified'], $data['is_expired'] );

		return $data;
	}
}
