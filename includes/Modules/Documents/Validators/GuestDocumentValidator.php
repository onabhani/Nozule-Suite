<?php

namespace Nozule\Modules\Documents\Validators;

use Nozule\Core\BaseValidator;
use Nozule\Modules\Documents\Models\GuestDocument;

/**
 * Validator for guest document data.
 */
class GuestDocumentValidator extends BaseValidator {

	/**
	 * Maximum upload file size in bytes (5 MB).
	 */
	private const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Allowed file MIME types.
	 *
	 * @var string[]
	 */
	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/jpg',
		'image/png',
		'application/pdf',
	];

	/**
	 * Allowed file extensions.
	 *
	 * @var string[]
	 */
	private const ALLOWED_EXTENSIONS = [
		'jpeg',
		'jpg',
		'png',
		'pdf',
	];

	/**
	 * Validate data for creating a new document.
	 */
	public function validateCreate( array $data ): bool {
		$rules = [
			'guest_id'       => [ 'required', 'integer' ],
			'document_type'  => [ 'required', 'in' => GuestDocument::ALLOWED_TYPES ],
		];

		$valid = $this->validate( $data, $rules );

		// Additional validation for optional fields when provided.
		$optionalRules = $this->getOptionalFieldRules( $data );
		if ( ! empty( $optionalRules ) ) {
			$optionalValid = $this->validate( $data, $optionalRules );
			$valid         = $valid && $optionalValid;
		}

		return $valid;
	}

	/**
	 * Validate data for updating an existing document.
	 *
	 * @param int   $id   The ID of the document being updated.
	 * @param array $data The update data.
	 */
	public function validateUpdate( int $id, array $data ): bool {
		$this->errors = [];
		$rules        = [];

		// Only validate document_type if it is provided.
		if ( isset( $data['document_type'] ) ) {
			$rules['document_type'] = [ 'in' => GuestDocument::ALLOWED_TYPES ];
		}

		// Validate OCR status if provided.
		if ( isset( $data['ocr_status'] ) ) {
			$rules['ocr_status'] = [ 'in' => GuestDocument::ALLOWED_OCR_STATUSES ];
		}

		// Merge optional field rules.
		$optionalRules = $this->getOptionalFieldRules( $data );
		$rules         = array_merge( $rules, $optionalRules );

		if ( empty( $rules ) ) {
			return true;
		}

		return $this->validate( $data, $rules );
	}

	/**
	 * Validate an uploaded file.
	 *
	 * @param array $file The $_FILES entry for the uploaded file.
	 */
	public function validateFile( array $file ): bool {
		$this->errors = [];

		// Check for upload errors.
		if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
			$this->errors['file'][] = $this->getUploadErrorMessage( $file['error'] ?? UPLOAD_ERR_NO_FILE );
			return false;
		}

		// Check file size.
		if ( ( $file['size'] ?? 0 ) > self::MAX_FILE_SIZE ) {
			$this->errors['file'][] = sprintf(
				/* translators: %s: Maximum file size in MB. */
				__( 'File size must not exceed %s MB.', 'nozule' ),
				'5'
			);
			return false;
		}

		// Check file extension.
		$extension = strtolower( pathinfo( $file['name'] ?? '', PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			$this->errors['file'][] = sprintf(
				/* translators: %s: Comma-separated list of allowed extensions. */
				__( 'File type not allowed. Allowed types: %s.', 'nozule' ),
				implode( ', ', self::ALLOWED_EXTENSIONS )
			);
			return false;
		}

		// Check MIME type.
		$mime_type = $file['type'] ?? '';
		if ( ! empty( $mime_type ) && ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			$this->errors['file'][] = sprintf(
				/* translators: %s: Comma-separated list of allowed MIME types. */
				__( 'File MIME type not allowed. Allowed types: %s.', 'nozule' ),
				implode( ', ', self::ALLOWED_MIME_TYPES )
			);
			return false;
		}

		return true;
	}

	/**
	 * Build validation rules for optional fields that are present in the data.
	 *
	 * @param array $data Input data.
	 * @return array<string, array> Validation rules for present optional fields.
	 */
	private function getOptionalFieldRules( array $data ): array {
		$rules     = [];
		$all_rules = [
			'document_number' => [ 'maxLength' => 50 ],
			'first_name'      => [ 'maxLength' => 100 ],
			'first_name_ar'   => [ 'maxLength' => 100 ],
			'last_name'       => [ 'maxLength' => 100 ],
			'last_name_ar'    => [ 'maxLength' => 100 ],
			'nationality'     => [ 'maxLength' => 100 ],
			'issuing_country' => [ 'maxLength' => 100 ],
			'issue_date'      => [ 'date' ],
			'expiry_date'     => [ 'date' ],
			'date_of_birth'   => [ 'date' ],
			'gender'          => [ 'in' => [ 'male', 'female', 'other' ] ],
			'mrz_line1'       => [ 'maxLength' => 50 ],
			'mrz_line2'       => [ 'maxLength' => 50 ],
		];

		foreach ( $all_rules as $field => $field_rules ) {
			if ( array_key_exists( $field, $data ) && $data[ $field ] !== null && $data[ $field ] !== '' ) {
				$rules[ $field ] = $field_rules;
			}
		}

		return $rules;
	}

	/**
	 * Get a human-readable error message for a PHP file upload error code.
	 *
	 * @param int $error_code The PHP upload error code.
	 */
	private function getUploadErrorMessage( int $error_code ): string {
		return match ( $error_code ) {
			UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the server maximum upload size.', 'nozule' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the form maximum upload size.', 'nozule' ),
			UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'nozule' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'nozule' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary upload directory.', 'nozule' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'nozule' ),
			UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'nozule' ),
			default               => __( 'Unknown upload error.', 'nozule' ),
		};
	}
}
