<?php

namespace Nozule\Modules\Channels\Services;

use Nozule\Core\Logger;

/**
 * Booking.com API client.
 *
 * Handles all HTTP communication with the Booking.com Connectivity API.
 * Uses wp_remote_post() / wp_remote_get() for HTTP calls and provides
 * a clean abstraction that can be swapped for other OTA clients.
 *
 * Base URL is configurable to support sandbox environments.
 */
class BookingComApiClient {

	/** @var string Default production base URL. */
	const DEFAULT_BASE_URL = 'https://supply-xml.booking.com/hotels/xml/';

	/** @var string Sandbox base URL for testing. */
	const SANDBOX_BASE_URL = 'https://supply-xml.booking.com/hotels/xml/test/';

	private string $hotelId  = '';
	private string $username = '';
	private string $password = '';
	private string $baseUrl  = '';
	private Logger $logger;

	/** @var int Request timeout in seconds. */
	private int $timeout = 30;

	public function __construct( Logger $logger ) {
		$this->logger  = $logger;
		$this->baseUrl = self::DEFAULT_BASE_URL;
	}

	/**
	 * Set API credentials.
	 */
	public function setCredentials( string $hotelId, string $username, string $password ): void {
		$this->hotelId  = $hotelId;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Set a custom base URL (e.g. for sandbox).
	 */
	public function setBaseUrl( string $url ): void {
		$this->baseUrl = trailingslashit( $url );
	}

	/**
	 * Get the configured base URL.
	 */
	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * Test the API connection by validating credentials.
	 *
	 * @return array{ success: bool, message: string }
	 */
	public function testConnection(): array {
		if ( empty( $this->hotelId ) || empty( $this->username ) || empty( $this->password ) ) {
			return [
				'success' => false,
				'message' => __( 'Missing credentials. Please provide hotel ID, username, and password.', 'nozule' ),
			];
		}

		// Build a minimal OTA_HotelAvailNotifRQ to test auth.
		$xml = $this->buildTestRequestXml();

		$response = $this->sendRequest( 'availability', $xml );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => $response->get_error_message(),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// 200 or 202 = valid credentials (even if the XML itself is rejected logically).
		// 401/403 = invalid credentials.
		if ( $code === 401 || $code === 403 ) {
			return [
				'success' => false,
				'message' => __( 'Invalid credentials. Authentication failed.', 'nozule' ),
			];
		}

		if ( $code >= 200 && $code < 300 ) {
			// Check for error markers in XML response.
			if ( strpos( $body, 'Error' ) !== false && strpos( $body, 'Authentication' ) !== false ) {
				return [
					'success' => false,
					'message' => __( 'Authentication failed. Please check your credentials.', 'nozule' ),
				];
			}

			return [
				'success' => true,
				'message' => __( 'Connection successful. Credentials are valid.', 'nozule' ),
			];
		}

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Unexpected response (HTTP %d). Please try again.', 'nozule' ),
				$code
			),
		];
	}

	/**
	 * Push availability data to Booking.com.
	 *
	 * @param array $data Array of availability records, each containing:
	 *     - channel_room_id: string   Room ID on Booking.com
	 *     - date:            string   Date (Y-m-d)
	 *     - available_rooms: int      Number of available rooms
	 *     - stop_sell:       bool     Whether to close sales
	 *     - min_stay:        int      Minimum stay nights
	 * @return array{ success: bool, message: string, records_processed: int, errors: string[] }
	 */
	public function pushAvailability( array $data ): array {
		if ( empty( $data ) ) {
			return [
				'success'           => true,
				'message'           => __( 'No availability data to push.', 'nozule' ),
				'records_processed' => 0,
				'errors'            => [],
			];
		}

		$xml = $this->buildAvailabilityXml( $data );

		$this->logger->debug( 'Booking.com: Pushing availability.', [
			'hotel_id'    => $this->hotelId,
			'record_count' => count( $data ),
		] );

		$response = $this->sendRequest( 'availability', $xml );

		return $this->parseResponse( $response, count( $data ), 'availability' );
	}

	/**
	 * Push rate data to Booking.com.
	 *
	 * @param array $data Array of rate records, each containing:
	 *     - channel_room_id: string   Room ID on Booking.com
	 *     - channel_rate_id: string   Rate plan ID on Booking.com
	 *     - date:            string   Date (Y-m-d)
	 *     - price:           float    Rate amount
	 *     - currency:        string   Currency code (e.g. 'USD')
	 * @return array{ success: bool, message: string, records_processed: int, errors: string[] }
	 */
	public function pushRates( array $data ): array {
		if ( empty( $data ) ) {
			return [
				'success'           => true,
				'message'           => __( 'No rate data to push.', 'nozule' ),
				'records_processed' => 0,
				'errors'            => [],
			];
		}

		$xml = $this->buildRatesXml( $data );

		$this->logger->debug( 'Booking.com: Pushing rates.', [
			'hotel_id'    => $this->hotelId,
			'record_count' => count( $data ),
		] );

		$response = $this->sendRequest( 'rates', $xml );

		return $this->parseResponse( $response, count( $data ), 'rates' );
	}

	/**
	 * Pull new reservations from Booking.com.
	 *
	 * @param string|null $lastSyncDate Only pull reservations created/modified after this date.
	 * @return array{ success: bool, message: string, reservations: array[], errors: string[] }
	 */
	public function pullReservations( ?string $lastSyncDate = null ): array {
		$xml = $this->buildReservationPullXml( $lastSyncDate );

		$this->logger->debug( 'Booking.com: Pulling reservations.', [
			'hotel_id'       => $this->hotelId,
			'last_sync_date' => $lastSyncDate,
		] );

		$response = $this->sendRequest( 'reservations', $xml );

		if ( is_wp_error( $response ) ) {
			return [
				'success'      => false,
				'message'      => $response->get_error_message(),
				'reservations' => [],
				'errors'       => [ $response->get_error_message() ],
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$error = sprintf(
				/* translators: %1$s: sync type, %2$d: HTTP status code */
				__( 'Booking.com API error for %1$s: HTTP %2$d', 'nozule' ),
				'reservations',
				$code
			);

			$this->logger->error( $error, [ 'body' => substr( $body, 0, 500 ) ] );

			return [
				'success'      => false,
				'message'      => $error,
				'reservations' => [],
				'errors'       => [ $error ],
			];
		}

		$reservations = $this->parseReservationsXml( $body );

		return [
			'success'      => true,
			'message'      => sprintf(
				/* translators: %d: number of reservations */
				__( 'Pulled %d reservations from Booking.com.', 'nozule' ),
				count( $reservations )
			),
			'reservations' => $reservations,
			'errors'       => [],
		];
	}

	// ------------------------------------------------------------------
	// XML Builders
	// ------------------------------------------------------------------

	/**
	 * Build the XML for a test/ping request.
	 */
	private function buildTestRequestXml(): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s' );

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<OTA_PingRQ xmlns="http://www.opentravel.org/OTA/2003/05"'
			. ' EchoToken="test-' . wp_generate_uuid4() . '"'
			. ' TimeStamp="' . $timestamp . '"'
			. ' Version="1.0">'
			. '<EchoData>Connection test from Nozule</EchoData>'
			. '</OTA_PingRQ>';
	}

	/**
	 * Build OTA_HotelAvailNotifRQ XML for availability push.
	 */
	private function buildAvailabilityXml( array $data ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="1.0">';
		$xml .= '<AvailStatusMessages HotelCode="' . esc_attr( $this->hotelId ) . '">';

		foreach ( $data as $record ) {
			$roomId = esc_attr( $record['channel_room_id'] ?? '' );
			$date   = esc_attr( $record['date'] ?? '' );
			$avail  = (int) ( $record['available_rooms'] ?? 0 );

			$xml .= '<AvailStatusMessage>';
			$xml .= '<StatusApplicationControl'
				. ' Start="' . $date . '"'
				. ' End="' . $date . '"'
				. ' InvTypeCode="' . $roomId . '"'
				. '/>';
			$xml .= '<LengthsOfStay>';
			$xml .= '<LengthOfStay MinMaxMessageType="SetMinLOS"'
				. ' Time="' . (int) ( $record['min_stay'] ?? 1 ) . '"'
				. '/>';
			$xml .= '</LengthsOfStay>';
			$xml .= '<StatusApplicationControl BookingLimit="' . $avail . '"/>';

			if ( ! empty( $record['stop_sell'] ) ) {
				$xml .= '<RestrictionStatus Status="Close" Restriction="Master"/>';
			} else {
				$xml .= '<RestrictionStatus Status="Open" Restriction="Master"/>';
			}

			$xml .= '</AvailStatusMessage>';
		}

		$xml .= '</AvailStatusMessages>';
		$xml .= '</OTA_HotelAvailNotifRQ>';

		return $xml;
	}

	/**
	 * Build OTA_HotelRateAmountNotifRQ XML for rate push.
	 */
	private function buildRatesXml( array $data ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="1.0">';
		$xml .= '<RateAmountMessages HotelCode="' . esc_attr( $this->hotelId ) . '">';

		foreach ( $data as $record ) {
			$roomId   = esc_attr( $record['channel_room_id'] ?? '' );
			$rateId   = esc_attr( $record['channel_rate_id'] ?? '' );
			$date     = esc_attr( $record['date'] ?? '' );
			$price    = number_format( (float) ( $record['price'] ?? 0 ), 2, '.', '' );
			$currency = esc_attr( $record['currency'] ?? 'USD' );

			$xml .= '<RateAmountMessage>';
			$xml .= '<StatusApplicationControl'
				. ' Start="' . $date . '"'
				. ' End="' . $date . '"'
				. ' InvTypeCode="' . $roomId . '"'
				. ' RatePlanCode="' . $rateId . '"'
				. '/>';
			$xml .= '<Rates>';
			$xml .= '<Rate>';
			$xml .= '<BaseByGuestAmts>';
			$xml .= '<BaseByGuestAmt AmountAfterTax="' . $price . '"'
				. ' CurrencyCode="' . $currency . '"'
				. '/>';
			$xml .= '</BaseByGuestAmts>';
			$xml .= '</Rate>';
			$xml .= '</Rates>';
			$xml .= '</RateAmountMessage>';
		}

		$xml .= '</RateAmountMessages>';
		$xml .= '</OTA_HotelRateAmountNotifRQ>';

		return $xml;
	}

	/**
	 * Build OTA_ReadRQ XML for pulling reservations.
	 */
	private function buildReservationPullXml( ?string $lastSyncDate ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_ReadRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="1.0">';
		$xml .= '<ReadRequests>';
		$xml .= '<HotelReadRequest HotelCode="' . esc_attr( $this->hotelId ) . '">';

		if ( $lastSyncDate ) {
			$xml .= '<SelectionCriteria'
				. ' Start="' . esc_attr( $lastSyncDate ) . '"'
				. ' End="' . gmdate( 'Y-m-d' ) . '"'
				. ' DateType="CreateDate"'
				. '/>';
		}

		$xml .= '</HotelReadRequest>';
		$xml .= '</ReadRequests>';
		$xml .= '</OTA_ReadRQ>';

		return $xml;
	}

	// ------------------------------------------------------------------
	// HTTP & Response Parsing
	// ------------------------------------------------------------------

	/**
	 * Send an XML request to the Booking.com API.
	 *
	 * @param string $endpoint Endpoint path (e.g. 'availability', 'rates', 'reservations').
	 * @param string $xml      XML request body.
	 * @return array|\WP_Error WP HTTP response or error.
	 */
	private function sendRequest( string $endpoint, string $xml ) {
		$url = $this->baseUrl . $endpoint;

		$args = [
			'method'  => 'POST',
			'timeout' => $this->timeout,
			'headers' => [
				'Content-Type'  => 'application/xml; charset=utf-8',
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			],
			'body'    => $xml,
		];

		/**
		 * Filter the request arguments before sending to Booking.com.
		 *
		 * @param array  $args     WP HTTP request args.
		 * @param string $endpoint The API endpoint.
		 * @param string $xml      The XML body.
		 */
		$args = apply_filters( 'nozule/channel_sync/booking_com_request_args', $args, $endpoint, $xml );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Booking.com: HTTP request failed.', [
				'endpoint' => $endpoint,
				'error'    => $response->get_error_message(),
			] );
		}

		return $response;
	}

	/**
	 * Parse the API response into a structured result.
	 *
	 * @param array|\WP_Error $response     WP HTTP response.
	 * @param int             $recordCount  Number of records sent.
	 * @param string          $syncType     Type of sync (availability/rates).
	 * @return array{ success: bool, message: string, records_processed: int, errors: string[] }
	 */
	private function parseResponse( $response, int $recordCount, string $syncType ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'success'           => false,
				'message'           => $response->get_error_message(),
				'records_processed' => 0,
				'errors'            => [ $response->get_error_message() ],
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 ) {
			// Check for error markers in XML response body.
			$errors = $this->extractXmlErrors( $body );

			if ( ! empty( $errors ) ) {
				$this->logger->warning( 'Booking.com: Partial errors in response.', [
					'sync_type' => $syncType,
					'errors'    => $errors,
				] );

				return [
					'success'           => true,
					'message'           => sprintf(
						/* translators: %1$s: sync type, %2$d: error count */
						__( 'Booking.com %1$s sync completed with %2$d warnings.', 'nozule' ),
						$syncType,
						count( $errors )
					),
					'records_processed' => $recordCount,
					'errors'            => $errors,
				];
			}

			$this->logger->info( 'Booking.com: Sync completed successfully.', [
				'sync_type'    => $syncType,
				'record_count' => $recordCount,
			] );

			return [
				'success'           => true,
				'message'           => sprintf(
					/* translators: %1$d: number of records, %2$s: sync type */
					__( 'Successfully pushed %1$d %2$s records to Booking.com.', 'nozule' ),
					$recordCount,
					$syncType
				),
				'records_processed' => $recordCount,
				'errors'            => [],
			];
		}

		$error = sprintf(
			/* translators: %1$s: sync type, %2$d: HTTP status code */
			__( 'Booking.com API error for %1$s: HTTP %2$d', 'nozule' ),
			$syncType,
			$code
		);

		$this->logger->error( $error, [ 'body' => substr( $body, 0, 500 ) ] );

		return [
			'success'           => false,
			'message'           => $error,
			'records_processed' => 0,
			'errors'            => [ $error ],
		];
	}

	/**
	 * Parse reservations from an XML response body.
	 *
	 * @return array[] Array of reservation arrays.
	 */
	private function parseReservationsXml( string $body ): array {
		$reservations = [];

		if ( empty( $body ) ) {
			return $reservations;
		}

		// Suppress XML parsing errors.
		$prev = libxml_use_internal_errors( true );

		$doc = simplexml_load_string( $body );

		if ( $doc === false ) {
			$this->logger->warning( 'Booking.com: Failed to parse reservation XML.', [
				'body' => substr( $body, 0, 500 ),
			] );
			libxml_use_internal_errors( $prev );
			return $reservations;
		}

		// Register the OTA namespace.
		$doc->registerXPathNamespace( 'ota', 'http://www.opentravel.org/OTA/2003/05' );

		// Look for HotelReservation elements.
		$resNodes = $doc->xpath( '//ota:HotelReservation' );

		if ( empty( $resNodes ) ) {
			// Try without namespace.
			$resNodes = $doc->xpath( '//HotelReservation' );
		}

		if ( ! is_array( $resNodes ) ) {
			libxml_use_internal_errors( $prev );
			return $reservations;
		}

		foreach ( $resNodes as $resNode ) {
			$reservation = $this->parseReservationNode( $resNode );
			if ( ! empty( $reservation ) ) {
				$reservations[] = $reservation;
			}
		}

		libxml_use_internal_errors( $prev );

		return $reservations;
	}

	/**
	 * Parse a single reservation XML node.
	 *
	 * @param \SimpleXMLElement $node
	 * @return array Reservation data.
	 */
	private function parseReservationNode( $node ): array {
		$attrs = $node->attributes();

		$reservation = [
			'external_id'     => (string) ( $attrs['ResID_Value'] ?? $attrs['UniqueID'] ?? '' ),
			'status'          => strtolower( (string) ( $attrs['ResStatus'] ?? 'confirmed' ) ),
			'guest_first_name' => '',
			'guest_last_name'  => '',
			'guest_email'      => '',
			'guest_phone'      => '',
			'check_in'         => '',
			'check_out'        => '',
			'room_type_code'   => '',
			'total_amount'     => 0.00,
			'currency'         => 'USD',
			'num_guests'       => 1,
			'special_requests' => '',
		];

		// Try to extract guest info.
		$guests = $node->xpath( './/ResGuest//PersonName' );
		if ( ! empty( $guests ) ) {
			$guest = $guests[0];
			$reservation['guest_first_name'] = (string) ( $guest->GivenName ?? '' );
			$reservation['guest_last_name']  = (string) ( $guest->Surname ?? '' );
		}

		// Try to extract email.
		$emails = $node->xpath( './/ResGuest//Email' );
		if ( ! empty( $emails ) ) {
			$reservation['guest_email'] = (string) $emails[0];
		}

		// Try to extract phone.
		$phones = $node->xpath( './/ResGuest//Telephone' );
		if ( ! empty( $phones ) ) {
			$reservation['guest_phone'] = (string) ( $phones[0]->attributes()['PhoneNumber'] ?? $phones[0] );
		}

		// Try to extract room stay dates.
		$roomStays = $node->xpath( './/RoomStay//TimeSpan' );
		if ( ! empty( $roomStays ) ) {
			$ts = $roomStays[0]->attributes();
			$reservation['check_in']  = (string) ( $ts['Start'] ?? '' );
			$reservation['check_out'] = (string) ( $ts['End'] ?? '' );
		}

		// Try to extract room type code.
		$roomTypes = $node->xpath( './/RoomType' );
		if ( ! empty( $roomTypes ) ) {
			$reservation['room_type_code'] = (string) ( $roomTypes[0]->attributes()['RoomTypeCode'] ?? '' );
		}

		// Try to extract total amount.
		$totals = $node->xpath( './/Total' );
		if ( ! empty( $totals ) ) {
			$totalAttrs = $totals[0]->attributes();
			$reservation['total_amount'] = (float) ( $totalAttrs['AmountAfterTax'] ?? 0 );
			$reservation['currency']     = (string) ( $totalAttrs['CurrencyCode'] ?? 'USD' );
		}

		// Try to extract special requests.
		$requests = $node->xpath( './/SpecialRequest//Text' );
		if ( ! empty( $requests ) ) {
			$reservation['special_requests'] = (string) $requests[0];
		}

		// Number of guests.
		$guestCounts = $node->xpath( './/GuestCount' );
		if ( ! empty( $guestCounts ) ) {
			$total = 0;
			foreach ( $guestCounts as $gc ) {
				$total += (int) ( $gc->attributes()['Count'] ?? 0 );
			}
			$reservation['num_guests'] = max( 1, $total );
		}

		return $reservation;
	}

	/**
	 * Extract error messages from an XML response body.
	 *
	 * @return string[]
	 */
	private function extractXmlErrors( string $body ): array {
		$errors = [];

		if ( empty( $body ) ) {
			return $errors;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = simplexml_load_string( $body );

		if ( $doc === false ) {
			libxml_use_internal_errors( $prev );
			return $errors;
		}

		$doc->registerXPathNamespace( 'ota', 'http://www.opentravel.org/OTA/2003/05' );

		// Look for Error/Warning elements.
		$errorNodes = $doc->xpath( '//ota:Error' );
		if ( empty( $errorNodes ) ) {
			$errorNodes = $doc->xpath( '//Error' );
		}

		if ( is_array( $errorNodes ) ) {
			foreach ( $errorNodes as $errNode ) {
				$attrs = $errNode->attributes();
				$code  = (string) ( $attrs['Code'] ?? '' );
				$msg   = (string) ( $attrs['ShortText'] ?? (string) $errNode );
				if ( ! empty( $msg ) ) {
					$errors[] = $code ? "[{$code}] {$msg}" : $msg;
				}
			}
		}

		$warningNodes = $doc->xpath( '//ota:Warning' );
		if ( empty( $warningNodes ) ) {
			$warningNodes = $doc->xpath( '//Warning' );
		}

		if ( is_array( $warningNodes ) ) {
			foreach ( $warningNodes as $warnNode ) {
				$attrs = $warnNode->attributes();
				$msg   = (string) ( $attrs['ShortText'] ?? (string) $warnNode );
				if ( ! empty( $msg ) ) {
					$errors[] = 'Warning: ' . $msg;
				}
			}
		}

		libxml_use_internal_errors( $prev );

		return $errors;
	}
}
