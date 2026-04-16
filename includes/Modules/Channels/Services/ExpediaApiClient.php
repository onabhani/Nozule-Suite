<?php

namespace Nozule\Modules\Channels\Services;

use Nozule\Core\Logger;

/**
 * Expedia EQC (Expedia QuickConnect) API client.
 *
 * EQC uses the OTA XML dialect (OTA_HotelAvailNotifRQ, OTA_HotelRateAmountNotifRQ,
 * OTA_HotelResNotifRQ) over HTTPS with HTTP Basic auth. Structure mirrors
 * BookingComApiClient; only endpoint paths and a few envelope attributes differ.
 */
class ExpediaApiClient {

	const DEFAULT_BASE_URL = 'https://services.expediapartnercentral.com/eqc/';
	const SANDBOX_BASE_URL = 'https://int.services.expediapartnercentral.com/eqc/';

	private string $propertyId = '';
	private string $username   = '';
	private string $password   = '';
	private string $baseUrl    = '';
	private Logger $logger;
	private int $timeout = 30;

	public function __construct( Logger $logger ) {
		$this->logger  = $logger;
		$this->baseUrl = self::DEFAULT_BASE_URL;
	}

	public function setCredentials( string $propertyId, string $username, string $password ): void {
		$this->propertyId = $propertyId;
		$this->username   = $username;
		$this->password   = $password;
	}

	public function setBaseUrl( string $url ): void {
		$this->baseUrl = trailingslashit( $url );
	}

	public function getBaseUrl(): string {
		return $this->baseUrl;
	}

	/**
	 * @return array{ success: bool, message: string }
	 */
	public function testConnection(): array {
		if ( $this->propertyId === '' || $this->username === '' || $this->password === '' ) {
			return [
				'success' => false,
				'message' => __( 'Missing credentials. Please provide property ID, username, and password.', 'nozule' ),
			];
		}

		// Empty ARI update = auth test. EQC returns 200 with no-op ack.
		$xml      = $this->buildAvailabilityXml( [] );
		$response = $this->sendRequest( 'ar', $xml );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 401 || $code === 403 ) {
			return [ 'success' => false, 'message' => __( 'Invalid credentials. Authentication failed.', 'nozule' ) ];
		}

		if ( $code >= 200 && $code < 300 ) {
			return [ 'success' => true, 'message' => __( 'Connection successful. Credentials are valid.', 'nozule' ) ];
		}

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Unexpected response (HTTP %d).', 'nozule' ),
				$code
			),
		];
	}

	/**
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

		$xml      = $this->buildAvailabilityXml( $data );
		$this->logger->debug( 'Expedia: Pushing availability.', [
			'property_id'  => $this->propertyId,
			'record_count' => count( $data ),
		] );
		$response = $this->sendRequest( 'ar', $xml );

		return $this->parseResponse( $response, count( $data ), 'availability' );
	}

	/**
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

		$xml      = $this->buildRatesXml( $data );
		$this->logger->debug( 'Expedia: Pushing rates.', [
			'property_id'  => $this->propertyId,
			'record_count' => count( $data ),
		] );
		$response = $this->sendRequest( 'ar', $xml );

		return $this->parseResponse( $response, count( $data ), 'rates' );
	}

	/**
	 * @return array{ success: bool, message: string, reservations: array[], errors: string[] }
	 */
	public function pullReservations( ?string $lastSyncDate = null ): array {
		$xml = $this->buildReservationPullXml( $lastSyncDate );
		$this->logger->debug( 'Expedia: Pulling reservations.', [
			'property_id'    => $this->propertyId,
			'last_sync_date' => $lastSyncDate,
		] );
		$response = $this->sendRequest( 'br', $xml );

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
				/* translators: %d: HTTP status code */
				__( 'Expedia API error for reservations: HTTP %d', 'nozule' ),
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
				__( 'Pulled %d reservations from Expedia.', 'nozule' ),
				count( $reservations )
			),
			'reservations' => $reservations,
			'errors'       => [],
		];
	}

	/**
	 * Confirm receipt of a booking back to Expedia (OTA_HotelResNotifRQ with confirmation number).
	 */
	public function confirmReservation( string $externalId, string $confirmationNumber = '' ): bool {
		$xml      = $this->buildBookingConfirmXml( $externalId, $confirmationNumber );
		$response = $this->sendRequest( 'bc', $xml );

		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	// ------------------------------------------------------------------
	// XML Builders
	// ------------------------------------------------------------------

	private function buildAvailabilityXml( array $data ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="2.0">';
		$xml .= '<AvailStatusMessages HotelCode="' . esc_attr( $this->propertyId ) . '">';

		foreach ( $data as $record ) {
			$roomId = esc_attr( $record['channel_room_id'] ?? '' );
			$rateId = esc_attr( $record['channel_rate_id'] ?? '' );
			$date   = esc_attr( $record['date'] ?? '' );
			$avail  = (int) ( $record['available_rooms'] ?? 0 );

			$xml .= '<AvailStatusMessage BookingLimit="' . $avail . '">';
			$xml .= '<StatusApplicationControl'
				. ' Start="' . $date . '"'
				. ' End="' . $date . '"'
				. ' InvTypeCode="' . $roomId . '"';
			if ( $rateId !== '' ) {
				$xml .= ' RatePlanCode="' . $rateId . '"';
			}
			$xml .= '/>';
			$xml .= '<RestrictionStatus Status="' . ( ! empty( $record['stop_sell'] ) ? 'Close' : 'Open' ) . '"/>';
			$xml .= '</AvailStatusMessage>';
		}

		$xml .= '</AvailStatusMessages>';
		$xml .= '</OTA_HotelAvailNotifRQ>';
		return $xml;
	}

	private function buildRatesXml( array $data ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="2.0">';
		$xml .= '<RateAmountMessages HotelCode="' . esc_attr( $this->propertyId ) . '">';

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
				. ' RatePlanCode="' . $rateId . '"/>';
			$xml .= '<Rates><Rate>';
			$xml .= '<BaseByGuestAmts>';
			$xml .= '<BaseByGuestAmt AmountAfterTax="' . $price . '" CurrencyCode="' . $currency . '"/>';
			$xml .= '</BaseByGuestAmts>';
			$xml .= '</Rate></Rates>';
			$xml .= '</RateAmountMessage>';
		}

		$xml .= '</RateAmountMessages>';
		$xml .= '</OTA_HotelRateAmountNotifRQ>';
		return $xml;
	}

	private function buildReservationPullXml( ?string $lastSyncDate ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_ReadRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="2.0">';
		$xml .= '<ReadRequests>';
		$xml .= '<HotelReadRequest HotelCode="' . esc_attr( $this->propertyId ) . '">';
		if ( $lastSyncDate ) {
			$xml .= '<SelectionCriteria'
				. ' Start="' . esc_attr( $lastSyncDate ) . '"'
				. ' End="' . gmdate( 'Y-m-d' ) . '"'
				. ' SelectionType="Undelivered"'
				. '/>';
		} else {
			$xml .= '<SelectionCriteria SelectionType="Undelivered"/>';
		}
		$xml .= '</HotelReadRequest>';
		$xml .= '</ReadRequests>';
		$xml .= '</OTA_ReadRQ>';
		return $xml;
	}

	private function buildBookingConfirmXml( string $externalId, string $confirmationNumber ): string {
		$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<OTA_HotelResNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"';
		$xml .= ' EchoToken="' . wp_generate_uuid4() . '"';
		$xml .= ' TimeStamp="' . $timestamp . '"';
		$xml .= ' Version="2.0" ResStatus="Commit">';
		$xml .= '<HotelReservations>';
		$xml .= '<HotelReservation ResStatus="Commit" CreateDateTime="' . $timestamp . '">';
		$xml .= '<UniqueID Type="14" ID="' . esc_attr( $externalId ) . '"/>';
		if ( $confirmationNumber !== '' ) {
			$xml .= '<ResGlobalInfo><HotelReservationIDs>';
			$xml .= '<HotelReservationID ResID_Type="10" ResID_Value="' . esc_attr( $confirmationNumber ) . '"/>';
			$xml .= '</HotelReservationIDs></ResGlobalInfo>';
		}
		$xml .= '</HotelReservation>';
		$xml .= '</HotelReservations>';
		$xml .= '</OTA_HotelResNotifRQ>';
		return $xml;
	}

	// ------------------------------------------------------------------
	// HTTP & Response Parsing
	// ------------------------------------------------------------------

	/**
	 * @return array|\WP_Error
	 */
	private function sendRequest( string $endpoint, string $xml ) {
		$url  = $this->baseUrl . $endpoint;
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
		 * Filter request args before sending to Expedia.
		 */
		$args = apply_filters( 'nozule/channel_sync/expedia_request_args', $args, $endpoint, $xml );

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Expedia: HTTP request failed.', [
				'endpoint' => $endpoint,
				'error'    => $response->get_error_message(),
			] );
		}

		return $response;
	}

	/**
	 * @param array|\WP_Error $response
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
			$errors = $this->extractXmlErrors( $body );

			if ( ! empty( $errors ) ) {
				$this->logger->warning( 'Expedia: Partial errors in response.', [
					'sync_type' => $syncType,
					'errors'    => $errors,
				] );
				return [
					'success'           => true,
					'message'           => sprintf(
						/* translators: %1$s: sync type, %2$d: error count */
						__( 'Expedia %1$s sync completed with %2$d warnings.', 'nozule' ),
						$syncType,
						count( $errors )
					),
					'records_processed' => $recordCount,
					'errors'            => $errors,
				];
			}

			$this->logger->info( 'Expedia: Sync completed successfully.', [
				'sync_type'    => $syncType,
				'record_count' => $recordCount,
			] );

			return [
				'success'           => true,
				'message'           => sprintf(
					/* translators: %1$d: count, %2$s: sync type */
					__( 'Successfully pushed %1$d %2$s records to Expedia.', 'nozule' ),
					$recordCount,
					$syncType
				),
				'records_processed' => $recordCount,
				'errors'            => [],
			];
		}

		$error = sprintf(
			/* translators: %1$s: sync type, %2$d: HTTP status code */
			__( 'Expedia API error for %1$s: HTTP %2$d', 'nozule' ),
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
	 * @return array[]
	 */
	private function parseReservationsXml( string $body ): array {
		$reservations = [];
		if ( $body === '' ) {
			return $reservations;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = simplexml_load_string( $body );

		if ( $doc === false ) {
			$this->logger->warning( 'Expedia: Failed to parse reservation XML.', [
				'body' => substr( $body, 0, 500 ),
			] );
			libxml_use_internal_errors( $prev );
			return $reservations;
		}

		$doc->registerXPathNamespace( 'ota', 'http://www.opentravel.org/OTA/2003/05' );

		$resNodes = $doc->xpath( '//ota:HotelReservation' );
		if ( empty( $resNodes ) ) {
			$resNodes = $doc->xpath( '//HotelReservation' );
		}

		if ( is_array( $resNodes ) ) {
			foreach ( $resNodes as $resNode ) {
				$reservation = $this->parseReservationNode( $resNode );
				if ( ! empty( $reservation ) ) {
					$reservations[] = $reservation;
				}
			}
		}

		libxml_use_internal_errors( $prev );
		return $reservations;
	}

	private function parseReservationNode( $node ): array {
		$attrs = $node->attributes();

		$reservation = [
			'external_id'      => (string) ( $attrs['ResID_Value'] ?? $attrs['UniqueID'] ?? '' ),
			'status'           => strtolower( (string) ( $attrs['ResStatus'] ?? 'confirmed' ) ),
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

		$uniqueIds = $node->xpath( './/UniqueID' );
		if ( ! empty( $uniqueIds ) && $reservation['external_id'] === '' ) {
			$reservation['external_id'] = (string) ( $uniqueIds[0]->attributes()['ID'] ?? '' );
		}

		$guests = $node->xpath( './/ResGuest//PersonName' );
		if ( ! empty( $guests ) ) {
			$reservation['guest_first_name'] = (string) ( $guests[0]->GivenName ?? '' );
			$reservation['guest_last_name']  = (string) ( $guests[0]->Surname ?? '' );
		}

		$emails = $node->xpath( './/ResGuest//Email' );
		if ( ! empty( $emails ) ) {
			$reservation['guest_email'] = (string) $emails[0];
		}

		$phones = $node->xpath( './/ResGuest//Telephone' );
		if ( ! empty( $phones ) ) {
			$reservation['guest_phone'] = (string) ( $phones[0]->attributes()['PhoneNumber'] ?? $phones[0] );
		}

		$timeSpan = $node->xpath( './/RoomStay//TimeSpan' );
		if ( ! empty( $timeSpan ) ) {
			$ts = $timeSpan[0]->attributes();
			$reservation['check_in']  = (string) ( $ts['Start'] ?? '' );
			$reservation['check_out'] = (string) ( $ts['End'] ?? '' );
		}

		$roomTypes = $node->xpath( './/RoomType' );
		if ( ! empty( $roomTypes ) ) {
			$reservation['room_type_code'] = (string) ( $roomTypes[0]->attributes()['RoomTypeCode'] ?? '' );
		}

		$totals = $node->xpath( './/Total' );
		if ( ! empty( $totals ) ) {
			$totalAttrs = $totals[0]->attributes();
			$reservation['total_amount'] = (float) ( $totalAttrs['AmountAfterTax'] ?? 0 );
			$reservation['currency']     = (string) ( $totalAttrs['CurrencyCode'] ?? 'USD' );
		}

		$requests = $node->xpath( './/SpecialRequest//Text' );
		if ( ! empty( $requests ) ) {
			$reservation['special_requests'] = (string) $requests[0];
		}

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
	 * @return string[]
	 */
	private function extractXmlErrors( string $body ): array {
		$errors = [];
		if ( $body === '' ) {
			return $errors;
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = simplexml_load_string( $body );

		if ( $doc === false ) {
			libxml_use_internal_errors( $prev );
			return $errors;
		}

		$doc->registerXPathNamespace( 'ota', 'http://www.opentravel.org/OTA/2003/05' );

		$errorNodes = $doc->xpath( '//ota:Error' );
		if ( empty( $errorNodes ) ) {
			$errorNodes = $doc->xpath( '//Error' );
		}

		if ( is_array( $errorNodes ) ) {
			foreach ( $errorNodes as $errNode ) {
				$attrs = $errNode->attributes();
				$code  = (string) ( $attrs['Code'] ?? '' );
				$msg   = (string) ( $attrs['ShortText'] ?? (string) $errNode );
				if ( $msg !== '' ) {
					$errors[] = $code !== '' ? "[{$code}] {$msg}" : $msg;
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
				if ( $msg !== '' ) {
					$errors[] = 'Warning: ' . $msg;
				}
			}
		}

		libxml_use_internal_errors( $prev );
		return $errors;
	}
}
