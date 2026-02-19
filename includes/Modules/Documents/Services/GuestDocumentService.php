<?php

namespace Nozule\Modules\Documents\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Documents\Models\GuestDocument;
use Nozule\Modules\Documents\Repositories\GuestDocumentRepository;
use Nozule\Modules\Documents\Validators\GuestDocumentValidator;

/**
 * Service layer for guest document business logic.
 *
 * Handles CRUD operations, file uploads, document verification,
 * and MRZ (Machine Readable Zone) parsing for TD3 passports.
 */
class GuestDocumentService {

	private GuestDocumentRepository $repository;
	private GuestDocumentValidator $validator;
	private EventDispatcher $events;
	private Logger $logger;

	/**
	 * ISO 3166-1 alpha-3 country code to full name mapping.
	 *
	 * @var array<string, string>
	 */
	private const COUNTRY_CODES = [
		'AFG' => 'Afghanistan',
		'ALB' => 'Albania',
		'DZA' => 'Algeria',
		'ARE' => 'United Arab Emirates',
		'ARG' => 'Argentina',
		'AUS' => 'Australia',
		'AUT' => 'Austria',
		'BHR' => 'Bahrain',
		'BGD' => 'Bangladesh',
		'BEL' => 'Belgium',
		'BRA' => 'Brazil',
		'CAN' => 'Canada',
		'CHN' => 'China',
		'COL' => 'Colombia',
		'CUB' => 'Cuba',
		'CYP' => 'Cyprus',
		'CZE' => 'Czech Republic',
		'D<<' => 'Germany',
		'DEU' => 'Germany',
		'DNK' => 'Denmark',
		'EGY' => 'Egypt',
		'ESP' => 'Spain',
		'ETH' => 'Ethiopia',
		'FIN' => 'Finland',
		'FRA' => 'France',
		'GBR' => 'United Kingdom',
		'GEO' => 'Georgia',
		'GHA' => 'Ghana',
		'GRC' => 'Greece',
		'HKG' => 'Hong Kong',
		'HUN' => 'Hungary',
		'IDN' => 'Indonesia',
		'IND' => 'India',
		'IRN' => 'Iran',
		'IRQ' => 'Iraq',
		'IRL' => 'Ireland',
		'ISR' => 'Israel',
		'ITA' => 'Italy',
		'JOR' => 'Jordan',
		'JPN' => 'Japan',
		'KAZ' => 'Kazakhstan',
		'KEN' => 'Kenya',
		'KOR' => 'South Korea',
		'KWT' => 'Kuwait',
		'LBN' => 'Lebanon',
		'LBY' => 'Libya',
		'MAR' => 'Morocco',
		'MEX' => 'Mexico',
		'MYS' => 'Malaysia',
		'NGA' => 'Nigeria',
		'NLD' => 'Netherlands',
		'NOR' => 'Norway',
		'NZL' => 'New Zealand',
		'OMN' => 'Oman',
		'PAK' => 'Pakistan',
		'PHL' => 'Philippines',
		'POL' => 'Poland',
		'PRT' => 'Portugal',
		'PSE' => 'Palestine',
		'QAT' => 'Qatar',
		'ROU' => 'Romania',
		'RUS' => 'Russia',
		'SAU' => 'Saudi Arabia',
		'SDN' => 'Sudan',
		'SGP' => 'Singapore',
		'SOM' => 'Somalia',
		'SWE' => 'Sweden',
		'CHE' => 'Switzerland',
		'SYR' => 'Syria',
		'THA' => 'Thailand',
		'TUN' => 'Tunisia',
		'TUR' => 'Turkey',
		'UKR' => 'Ukraine',
		'USA' => 'United States',
		'UZB' => 'Uzbekistan',
		'VNM' => 'Vietnam',
		'YEM' => 'Yemen',
		'ZAF' => 'South Africa',
	];

	public function __construct(
		GuestDocumentRepository $repository,
		GuestDocumentValidator $validator,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->events     = $events;
		$this->logger     = $logger;
	}

	/**
	 * Get all documents for a guest.
	 *
	 * @param int $guestId Guest ID.
	 * @return GuestDocument[]
	 */
	public function getDocuments( int $guestId ): array {
		return $this->repository->getByGuest( $guestId );
	}

	/**
	 * Get a single document by ID.
	 *
	 * @param int $id Document ID.
	 */
	public function getDocument( int $id ): ?GuestDocument {
		return $this->repository->find( $id );
	}

	/**
	 * Create a new guest document.
	 *
	 * @param array $data Document data.
	 * @return GuestDocument|array The created document, or an array of validation errors.
	 */
	public function createDocument( array $data ): GuestDocument|array {
		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizeDocumentData( $data );
		$document  = $this->repository->create( $sanitized );

		if ( ! $document ) {
			$this->logger->error( 'Failed to create guest document.', [
				'guest_id' => $data['guest_id'] ?? null,
			] );
			return [ 'general' => [ __( 'Failed to create document.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Guest document created.', [
			'document_id' => $document->id,
			'guest_id'    => $document->guest_id,
			'type'        => $document->document_type,
		] );

		$this->events->dispatch( 'documents/created', $document );

		return $document;
	}

	/**
	 * Update an existing guest document.
	 *
	 * @param int   $id   Document ID.
	 * @param array $data Fields to update.
	 * @return GuestDocument|array The updated document, or an array of validation errors.
	 */
	public function updateDocument( int $id, array $data ): GuestDocument|array {
		$document = $this->repository->find( $id );

		if ( ! $document ) {
			return [ 'general' => [ __( 'Document not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizeDocumentData( $data );

		$updated = $this->repository->update( $id, $sanitized );

		if ( ! $updated ) {
			$this->logger->error( 'Failed to update guest document.', [
				'document_id' => $id,
			] );
			return [ 'general' => [ __( 'Failed to update document.', 'nozule' ) ] ];
		}

		$document = $this->repository->find( $id );

		$this->logger->info( 'Guest document updated.', [
			'document_id' => $id,
			'guest_id'    => $document->guest_id,
		] );

		$this->events->dispatch( 'documents/updated', $document );

		return $document;
	}

	/**
	 * Delete a guest document and its associated file.
	 *
	 * @param int $id Document ID.
	 */
	public function deleteDocument( int $id ): bool {
		$document = $this->repository->find( $id );

		if ( ! $document ) {
			return false;
		}

		// Delete the physical file from disk.
		if ( ! empty( $document->file_path ) ) {
			$this->deleteFileFromDisk( $document->file_path );
		}

		// Delete the thumbnail if it exists.
		if ( ! empty( $document->thumbnail_path ) ) {
			$this->deleteFileFromDisk( $document->thumbnail_path );
		}

		$deleted = $this->repository->delete( $id );

		if ( $deleted ) {
			$this->logger->info( 'Guest document deleted.', [
				'document_id' => $id,
				'guest_id'    => $document->guest_id,
			] );

			$this->events->dispatch( 'documents/deleted', $document );
		}

		return $deleted;
	}

	/**
	 * Upload a document file using WordPress upload handling.
	 *
	 * @param array $file    The $_FILES entry for the uploaded file.
	 * @param int   $guestId Guest ID (used for organizing uploads).
	 * @return array|string File path on success, or array of errors on failure.
	 */
	public function uploadFile( array $file, int $guestId ): array|string {
		if ( ! $this->validator->validateFile( $file ) ) {
			return $this->validator->getErrors();
		}

		// Require the WordPress file handling functions.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Custom upload directory for guest documents.
		$upload_dir_filter = function ( array $uploads ) use ( $guestId ): array {
			$subdir              = '/nozule/documents/' . $guestId;
			$uploads['subdir']   = $subdir;
			$uploads['path']     = $uploads['basedir'] . $subdir;
			$uploads['url']      = $uploads['baseurl'] . $subdir;
			return $uploads;
		};

		add_filter( 'upload_dir', $upload_dir_filter );

		$overrides = [
			'test_form' => false,
			'mimes'     => [
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'pdf'      => 'application/pdf',
			],
		];

		$result = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $result['error'] ) ) {
			$this->logger->error( 'Document file upload failed.', [
				'guest_id' => $guestId,
				'error'    => $result['error'],
			] );
			return [ 'file' => [ $result['error'] ] ];
		}

		$this->logger->info( 'Document file uploaded.', [
			'guest_id'  => $guestId,
			'file_path' => $result['file'],
		] );

		return $result['file'];
	}

	/**
	 * Mark a document as verified.
	 *
	 * @param int $id         Document ID.
	 * @param int $verifiedBy User ID of the verifier.
	 * @return GuestDocument|array The verified document, or an array of errors.
	 */
	public function verifyDocument( int $id, int $verifiedBy ): GuestDocument|array {
		$document = $this->repository->find( $id );

		if ( ! $document ) {
			return [ 'general' => [ __( 'Document not found.', 'nozule' ) ] ];
		}

		$updated = $this->repository->update( $id, [
			'verified'    => 1,
			'verified_by' => $verifiedBy,
			'verified_at' => current_time( 'mysql' ),
		] );

		if ( ! $updated ) {
			return [ 'general' => [ __( 'Failed to verify document.', 'nozule' ) ] ];
		}

		$document = $this->repository->find( $id );

		$this->logger->info( 'Guest document verified.', [
			'document_id' => $id,
			'verified_by' => $verifiedBy,
		] );

		$this->events->dispatch( 'documents/verified', $document, $verifiedBy );

		return $document;
	}

	/**
	 * Parse TD3 passport MRZ (Machine Readable Zone).
	 *
	 * TD3 format: 2 lines x 44 characters each.
	 *
	 * Line 1: P<ISSUING_COUNTRY<LAST_NAME<<FIRST_NAMES<<<<...
	 *   Pos 0:     Document type indicator (P = passport)
	 *   Pos 1:     Type sub-indicator (< for standard)
	 *   Pos 2-4:   Issuing country (3-letter code)
	 *   Pos 5-43:  Names (last<<first, padded with <)
	 *
	 * Line 2: PASSPORT_NUMBER<CHECK<NATIONALITY<DOB<CHECK<SEX<EXPIRY<CHECK<PERSONAL_NO<CHECK<OVERALL_CHECK
	 *   Pos 0-8:   Document number
	 *   Pos 9:     Check digit for document number
	 *   Pos 10-12: Nationality (3-letter code)
	 *   Pos 13-18: Date of birth (YYMMDD)
	 *   Pos 19:    Check digit for DOB
	 *   Pos 20:    Sex (M/F/<)
	 *   Pos 21-26: Expiry date (YYMMDD)
	 *   Pos 27:    Check digit for expiry
	 *   Pos 28-41: Optional/personal number
	 *   Pos 42:    Check digit for optional data
	 *   Pos 43:    Overall check digit
	 *
	 * @param string $mrz1 First MRZ line (44 chars).
	 * @param string $mrz2 Second MRZ line (44 chars).
	 * @return array Parsed MRZ data with extracted fields.
	 */
	public function parseMRZ( string $mrz1, string $mrz2 ): array {
		$mrz1 = strtoupper( trim( $mrz1 ) );
		$mrz2 = strtoupper( trim( $mrz2 ) );

		$result = [
			'valid'            => false,
			'document_type'    => null,
			'issuing_country'  => null,
			'last_name'        => null,
			'first_name'       => null,
			'document_number'  => null,
			'nationality'      => null,
			'nationality_code' => null,
			'date_of_birth'    => null,
			'gender'           => null,
			'expiry_date'      => null,
			'personal_number'  => null,
			'check_digits'     => [],
			'raw_mrz_line1'    => $mrz1,
			'raw_mrz_line2'    => $mrz2,
		];

		// Validate line lengths.
		if ( strlen( $mrz1 ) !== 44 || strlen( $mrz2 ) !== 44 ) {
			$result['error'] = __( 'Invalid MRZ format: each line must be exactly 44 characters.', 'nozule' );
			$this->logger->warning( 'MRZ parse failed: invalid line length.', [
				'line1_length' => strlen( $mrz1 ),
				'line2_length' => strlen( $mrz2 ),
			] );
			return $result;
		}

		// Validate document type (first character must be P for passport).
		$doc_type_indicator = $mrz1[0];
		if ( $doc_type_indicator !== 'P' ) {
			$result['error'] = __( 'Invalid MRZ format: first character must be P for passport (TD3).', 'nozule' );
			return $result;
		}

		// Parse Line 1.
		$result['document_type'] = 'passport';

		// Issuing country: positions 2-4.
		$issuing_code              = substr( $mrz1, 2, 3 );
		$result['issuing_country'] = $this->resolveCountryName( $issuing_code );

		// Names: positions 5-43.
		$names_raw = substr( $mrz1, 5, 39 );
		$names     = $this->parseMRZNames( $names_raw );

		$result['last_name']  = $names['last_name'];
		$result['first_name'] = $names['first_name'];

		// Parse Line 2.

		// Document number: positions 0-8.
		$doc_number_raw            = substr( $mrz2, 0, 9 );
		$result['document_number'] = $this->cleanMRZField( $doc_number_raw );

		// Document number check digit: position 9.
		$doc_check = $mrz2[9];

		// Nationality: positions 10-12.
		$nationality_code          = substr( $mrz2, 10, 3 );
		$result['nationality_code'] = $this->cleanMRZField( $nationality_code );
		$result['nationality']     = $this->resolveCountryName( $nationality_code );

		// Date of birth: positions 13-18 (YYMMDD).
		$dob_raw                 = substr( $mrz2, 13, 6 );
		$result['date_of_birth'] = $this->parseMRZDate( $dob_raw, true );

		// DOB check digit: position 19.
		$dob_check = $mrz2[19];

		// Gender: position 20.
		$gender_raw       = $mrz2[20];
		$result['gender'] = match ( $gender_raw ) {
			'M'     => 'male',
			'F'     => 'female',
			default => 'other',
		};

		// Expiry date: positions 21-26 (YYMMDD).
		$expiry_raw            = substr( $mrz2, 21, 6 );
		$result['expiry_date'] = $this->parseMRZDate( $expiry_raw, false );

		// Expiry check digit: position 27.
		$expiry_check = $mrz2[27];

		// Personal/optional number: positions 28-41.
		$personal_raw              = substr( $mrz2, 28, 14 );
		$result['personal_number'] = $this->cleanMRZField( $personal_raw );

		// Optional data check digit: position 42.
		$optional_check = $mrz2[42];

		// Overall check digit: position 43.
		$overall_check = $mrz2[43];

		// Validate check digits.
		$check_digits = [
			'document_number' => [
				'data'     => $doc_number_raw,
				'expected' => $doc_check,
				'computed' => $this->computeCheckDigit( $doc_number_raw ),
				'valid'    => $this->computeCheckDigit( $doc_number_raw ) === $doc_check,
			],
			'date_of_birth'   => [
				'data'     => $dob_raw,
				'expected' => $dob_check,
				'computed' => $this->computeCheckDigit( $dob_raw ),
				'valid'    => $this->computeCheckDigit( $dob_raw ) === $dob_check,
			],
			'expiry_date'     => [
				'data'     => $expiry_raw,
				'expected' => $expiry_check,
				'computed' => $this->computeCheckDigit( $expiry_raw ),
				'valid'    => $this->computeCheckDigit( $expiry_raw ) === $expiry_check,
			],
			'optional_data'   => [
				'data'     => $personal_raw,
				'expected' => $optional_check,
				'computed' => $this->computeCheckDigit( $personal_raw ),
				'valid'    => $this->computeCheckDigit( $personal_raw ) === $optional_check,
			],
			'overall'         => [
				'data'     => $doc_number_raw . $doc_check . $dob_raw . $dob_check . $expiry_raw . $expiry_check . $personal_raw . $optional_check,
				'expected' => $overall_check,
				'computed' => $this->computeCheckDigit(
					$doc_number_raw . $doc_check . $dob_raw . $dob_check . $expiry_raw . $expiry_check . $personal_raw . $optional_check
				),
				'valid'    => $this->computeCheckDigit(
					$doc_number_raw . $doc_check . $dob_raw . $dob_check . $expiry_raw . $expiry_check . $personal_raw . $optional_check
				) === $overall_check,
			],
		];

		$result['check_digits'] = $check_digits;

		// The MRZ is valid even if some check digits fail (we still return parsed data).
		$all_checks_pass = array_reduce(
			$check_digits,
			fn( bool $carry, array $check ) => $carry && $check['valid'],
			true
		);

		$result['valid']              = true; // Structurally valid (correct format).
		$result['check_digits_valid'] = $all_checks_pass;

		$this->logger->info( 'MRZ parsed successfully.', [
			'document_number'    => $result['document_number'],
			'check_digits_valid' => $all_checks_pass,
		] );

		return $result;
	}

	/**
	 * Parse a YYMMDD date from MRZ format to Y-m-d.
	 *
	 * For dates of birth: YY > 30 -> 19XX, YY <= 30 -> 20XX.
	 * For expiry dates: always 20XX.
	 *
	 * @param string $yymmdd Six-character date string (YYMMDD).
	 * @param bool   $isDob  Whether this is a date of birth (affects century logic).
	 * @return string|null Formatted date (Y-m-d) or null if invalid.
	 */
	public function parseMRZDate( string $yymmdd, bool $isDob = true ): ?string {
		$yymmdd = trim( $yymmdd );

		if ( strlen( $yymmdd ) !== 6 || ! ctype_digit( $yymmdd ) ) {
			return null;
		}

		$yy = (int) substr( $yymmdd, 0, 2 );
		$mm = (int) substr( $yymmdd, 2, 2 );
		$dd = (int) substr( $yymmdd, 4, 2 );

		// Validate month and day ranges.
		if ( $mm < 1 || $mm > 12 || $dd < 1 || $dd > 31 ) {
			return null;
		}

		// Determine century.
		if ( $isDob ) {
			$century = ( $yy > 30 ) ? 1900 : 2000;
		} else {
			$century = 2000;
		}

		$year = $century + $yy;

		// Validate the date.
		if ( ! checkdate( $mm, $dd, $year ) ) {
			return null;
		}

		return sprintf( '%04d-%02d-%02d', $year, $mm, $dd );
	}

	/**
	 * Parse the name field from MRZ Line 1.
	 *
	 * Names are encoded as: LAST_NAME<<FIRST_NAME<MIDDLE_NAMES<...<
	 * The double chevron << separates last name from given names.
	 * Single chevrons < separate multiple given names.
	 *
	 * @param string $raw The raw name field from MRZ (positions 5-43 of line 1).
	 * @return array{ last_name: string, first_name: string }
	 */
	private function parseMRZNames( string $raw ): array {
		// Split on double filler (<<) to separate last name from first name(s).
		$parts = explode( '<<', $raw, 2 );

		$last_name  = $this->cleanMRZName( $parts[0] ?? '' );
		$first_name = $this->cleanMRZName( $parts[1] ?? '' );

		return [
			'last_name'  => $last_name,
			'first_name' => $first_name,
		];
	}

	/**
	 * Clean an MRZ name field: replace < with spaces and trim.
	 *
	 * @param string $raw The raw MRZ name segment.
	 */
	private function cleanMRZName( string $raw ): string {
		// Replace single fillers with spaces.
		$name = str_replace( '<', ' ', $raw );

		// Trim and collapse multiple spaces.
		return trim( preg_replace( '/\s+/', ' ', $name ) );
	}

	/**
	 * Clean an MRZ field: remove trailing < fillers.
	 *
	 * @param string $raw The raw MRZ field.
	 */
	private function cleanMRZField( string $raw ): string {
		return rtrim( $raw, '<' );
	}

	/**
	 * Compute the MRZ check digit for a given string.
	 *
	 * ICAO 9303 check digit algorithm:
	 * 1. Each character is assigned a numerical value:
	 *    - Digits 0-9 = their value
	 *    - Letters A-Z = 10-35
	 *    - < (filler) = 0
	 * 2. Multiply each value by a weight from the repeating pattern 7, 3, 1.
	 * 3. Sum all products.
	 * 4. The check digit is the sum modulo 10.
	 *
	 * @param string $data The MRZ data string to compute check digit for.
	 * @return string The single-digit check character (0-9).
	 */
	private function computeCheckDigit( string $data ): string {
		$weights = [ 7, 3, 1 ];
		$sum     = 0;

		for ( $i = 0, $len = strlen( $data ); $i < $len; $i++ ) {
			$char   = $data[ $i ];
			$weight = $weights[ $i % 3 ];

			if ( ctype_digit( $char ) ) {
				$value = (int) $char;
			} elseif ( ctype_alpha( $char ) ) {
				$value = ord( strtoupper( $char ) ) - ord( 'A' ) + 10;
			} else {
				// Filler character (<) and anything else.
				$value = 0;
			}

			$sum += $value * $weight;
		}

		return (string) ( $sum % 10 );
	}

	/**
	 * Resolve a 3-letter country code to a full country name.
	 *
	 * @param string $code The 3-letter country/nationality code.
	 * @return string The country name, or the cleaned code if not found.
	 */
	private function resolveCountryName( string $code ): string {
		$code    = strtoupper( $this->cleanMRZField( $code ) );
		$cleaned = str_replace( '<', '', $code );

		return self::COUNTRY_CODES[ $code ] ?? self::COUNTRY_CODES[ $cleaned ] ?? $cleaned;
	}

	/**
	 * Sanitize document data for storage.
	 *
	 * @param array $data Raw document data.
	 * @return array Sanitized data.
	 */
	private function sanitizeDocumentData( array $data ): array {
		$sanitized = [];

		$text_fields = [
			'document_number', 'first_name', 'first_name_ar',
			'last_name', 'last_name_ar', 'nationality', 'issuing_country',
			'gender', 'mrz_line1', 'mrz_line2', 'file_type',
		];

		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Integer fields.
		if ( array_key_exists( 'guest_id', $data ) ) {
			$sanitized['guest_id'] = absint( $data['guest_id'] );
		}

		if ( array_key_exists( 'verified', $data ) ) {
			$sanitized['verified'] = absint( $data['verified'] ) ? 1 : 0;
		}

		if ( array_key_exists( 'verified_by', $data ) ) {
			$sanitized['verified_by'] = absint( $data['verified_by'] ) ?: null;
		}

		// Enum fields.
		if ( array_key_exists( 'document_type', $data ) ) {
			$sanitized['document_type'] = in_array( $data['document_type'], GuestDocument::ALLOWED_TYPES, true )
				? $data['document_type']
				: GuestDocument::TYPE_PASSPORT;
		}

		if ( array_key_exists( 'ocr_status', $data ) ) {
			$sanitized['ocr_status'] = in_array( $data['ocr_status'], GuestDocument::ALLOWED_OCR_STATUSES, true )
				? $data['ocr_status']
				: GuestDocument::OCR_NONE;
		}

		// Date fields.
		$date_fields = [ 'issue_date', 'expiry_date', 'date_of_birth', 'verified_at' ];
		foreach ( $date_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Path fields (sanitize as text but preserve slashes).
		if ( array_key_exists( 'file_path', $data ) ) {
			$sanitized['file_path'] = sanitize_text_field( $data['file_path'] );
		}

		if ( array_key_exists( 'thumbnail_path', $data ) ) {
			$sanitized['thumbnail_path'] = sanitize_text_field( $data['thumbnail_path'] );
		}

		// Textarea fields.
		if ( array_key_exists( 'notes', $data ) ) {
			$sanitized['notes'] = sanitize_textarea_field( $data['notes'] );
		}

		// JSON fields.
		if ( array_key_exists( 'ocr_data', $data ) ) {
			$sanitized['ocr_data'] = $data['ocr_data'];
		}

		return $sanitized;
	}

	/**
	 * Delete a file from disk.
	 *
	 * @param string $filePath Absolute path to the file.
	 */
	private function deleteFileFromDisk( string $filePath ): void {
		if ( ! empty( $filePath ) && file_exists( $filePath ) ) {
			$deleted = wp_delete_file( $filePath );

			if ( file_exists( $filePath ) ) {
				$this->logger->warning( 'Failed to delete document file from disk.', [
					'file_path' => $filePath,
				] );
			}
		}
	}
}
