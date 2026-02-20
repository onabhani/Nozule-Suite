<?php

namespace Nozule\Modules\Metasearch\Services;

use Nozule\Core\SettingsManager;
use Nozule\Modules\Pricing\Repositories\RatePlanRepository;
use Nozule\Modules\Pricing\Services\PricingService;
use Nozule\Modules\Rooms\Repositories\RoomTypeRepository;

/**
 * Google Hotel Ads Service.
 *
 * Generates the Google Hotel Price Feed XML (OTA_HotelRateAmountNotifRQ style),
 * manages GHA/metasearch settings, and produces JSON-LD structured data for
 * Free Booking Links on Google Maps.
 */
class GoogleHotelAdsService {

	private SettingsManager $settings;
	private PricingService $pricingService;
	private RoomTypeRepository $roomTypeRepository;
	private RatePlanRepository $ratePlanRepository;

	/**
	 * Setting keys used by this service, all prefixed with 'metasearch.'.
	 */
	private const SETTING_KEYS = [
		'gha_enabled',
		'hotel_id',
		'partner_key',
		'landing_page_url',
		'currency',
		'hotel_name',
		'hotel_name_ar',
		'hotel_address',
		'hotel_city',
		'hotel_country',
		'free_booking_links',
		'cpc_enabled',
		'cpc_budget',
		'cpc_bid_type',
	];

	/**
	 * Number of days into the future to generate rates for.
	 */
	private const FEED_DAYS_AHEAD = 90;

	public function __construct(
		SettingsManager $settings,
		PricingService $pricingService,
		RoomTypeRepository $roomTypeRepository,
		RatePlanRepository $ratePlanRepository
	) {
		$this->settings           = $settings;
		$this->pricingService     = $pricingService;
		$this->roomTypeRepository = $roomTypeRepository;
		$this->ratePlanRepository = $ratePlanRepository;
	}

	/**
	 * Check whether the Google Hotel Ads integration is enabled.
	 */
	public function isEnabled(): bool {
		return (bool) $this->settings->get( 'metasearch.gha_enabled', false );
	}

	/**
	 * Retrieve all GHA / metasearch settings as a flat associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function getSettings(): array {
		return [
			'enabled'                    => (bool) $this->settings->get( 'metasearch.gha_enabled', false ),
			'hotel_id'                   => $this->settings->get( 'metasearch.hotel_id', '' ),
			'partner_key'                => $this->settings->get( 'metasearch.partner_key', '' ),
			'landing_page_url'           => $this->settings->get( 'metasearch.landing_page_url', '' ),
			'currency'                   => $this->settings->get( 'metasearch.currency', 'SYP' ),
			'hotel_name'                 => $this->settings->get( 'metasearch.hotel_name', '' ),
			'hotel_name_ar'              => $this->settings->get( 'metasearch.hotel_name_ar', '' ),
			'hotel_address'              => $this->settings->get( 'metasearch.hotel_address', '' ),
			'hotel_city'                 => $this->settings->get( 'metasearch.hotel_city', '' ),
			'hotel_country'              => $this->settings->get( 'metasearch.hotel_country', 'SY' ),
			'free_booking_links_enabled' => (bool) $this->settings->get( 'metasearch.free_booking_links', false ),
			'cpc_enabled'                => (bool) $this->settings->get( 'metasearch.cpc_enabled', false ),
			'cpc_budget'                 => (float) $this->settings->get( 'metasearch.cpc_budget', 0.0 ),
			'cpc_bid_type'               => $this->settings->get( 'metasearch.cpc_bid_type', 'manual' ),
		];
	}

	/**
	 * Persist GHA / metasearch settings.
	 *
	 * Only keys that exist in the incoming data are updated; others are left
	 * untouched so callers can perform partial updates.
	 *
	 * @param array<string, mixed> $data Associative array of setting values.
	 */
	public function updateSettings( array $data ): void {
		$map = [
			'enabled'                    => 'metasearch.gha_enabled',
			'hotel_id'                   => 'metasearch.hotel_id',
			'partner_key'                => 'metasearch.partner_key',
			'landing_page_url'           => 'metasearch.landing_page_url',
			'currency'                   => 'metasearch.currency',
			'hotel_name'                 => 'metasearch.hotel_name',
			'hotel_name_ar'              => 'metasearch.hotel_name_ar',
			'hotel_address'              => 'metasearch.hotel_address',
			'hotel_city'                 => 'metasearch.hotel_city',
			'hotel_country'              => 'metasearch.hotel_country',
			'free_booking_links_enabled' => 'metasearch.free_booking_links',
			'cpc_enabled'                => 'metasearch.cpc_enabled',
			'cpc_budget'                 => 'metasearch.cpc_budget',
			'cpc_bid_type'               => 'metasearch.cpc_bid_type',
		];

		foreach ( $map as $inputKey => $settingKey ) {
			if ( array_key_exists( $inputKey, $data ) ) {
				$this->settings->set( $settingKey, $data[ $inputKey ] );
			}
		}
	}

	/**
	 * Generate the full Google Hotel Price Feed XML.
	 *
	 * Produces an OTA_HotelRateAmountNotifRQ-style document listing every
	 * active room type + rate plan combination with rates for the next 90 days.
	 *
	 * @param int $maxResults Maximum number of Result elements (0 = unlimited).
	 * @return string Complete XML document as a string.
	 */
	public function generatePriceFeedXml( int $maxResults = 0 ): string {
		$hotelId  = $this->settings->get( 'metasearch.hotel_id', '' );
		$currency = $this->settings->get( 'metasearch.currency', 'SYP' );

		$roomTypes = $this->roomTypeRepository->getAllOrdered();
		$today     = new \DateTimeImmutable( 'today', wp_timezone() );

		// Collect room+rate combinations and their pricing results.
		$roomDataElements    = [];
		$packageDataElements = [];
		$resultElements      = [];
		$seenPackages        = [];
		$resultCount         = 0;

		foreach ( $roomTypes as $roomType ) {
			if ( ! $roomType->isActive() ) {
				continue;
			}

			$ratePlans = $this->ratePlanRepository->getForRoomType( $roomType->id );

			if ( empty( $ratePlans ) ) {
				continue;
			}

			foreach ( $ratePlans as $ratePlan ) {
				$ratePlanCode = $ratePlan->code ?? ( 'rp_' . $ratePlan->id );

				// Build RoomData element for this room type + rate plan pair.
				$roomDataElements[] = $this->buildRoomDataXml(
					$roomType,
					$ratePlanCode
				);

				// Build PackageData element (once per unique rate plan).
				if ( ! isset( $seenPackages[ $ratePlanCode ] ) ) {
					$packageDataElements[]           = $this->buildPackageDataXml( $ratePlan, $ratePlanCode );
					$seenPackages[ $ratePlanCode ] = true;
				}

				// Generate Result elements for each day in the look-ahead window.
				for ( $day = 0; $day < self::FEED_DAYS_AHEAD; $day++ ) {
					if ( $maxResults > 0 && $resultCount >= $maxResults ) {
						break 3; // Exit all three loops.
					}

					$checkIn  = $today->modify( "+{$day} days" );
					$checkOut = $checkIn->modify( '+1 day' );

					$checkInStr  = $checkIn->format( 'Y-m-d' );
					$checkOutStr = $checkOut->format( 'Y-m-d' );

					// Skip dates for which the rate plan is not valid.
					if ( ! $ratePlan->isValidForDate( $checkInStr ) ) {
						continue;
					}

					try {
						$pricing = $this->pricingService->calculateStayPrice(
							$roomType->id,
							$checkInStr,
							$checkOutStr,
							2,       // adults
							0,       // children
							$ratePlan->id
						);
					} catch ( \Throwable $e ) {
						// Skip dates where pricing cannot be calculated.
						continue;
					}

					$resultElements[] = $this->buildResultXml(
						$hotelId,
						$roomType->id,
						$ratePlanCode,
						$checkInStr,
						$pricing->subtotal,
						$pricing->taxes,
						$pricing->fees,
						$currency
					);

					$resultCount++;
				}
			}
		}

		return $this->assembleXml(
			$hotelId,
			$roomDataElements,
			$packageDataElements,
			$resultElements
		);
	}

	/**
	 * Generate JSON-LD structured data for a Hotel entity.
	 *
	 * Outputs a <script type="application/ld+json"> block suitable for
	 * embedding in wp_head. Returns an empty string when required data
	 * is missing.
	 *
	 * @return string HTML script element containing JSON-LD or empty string.
	 */
	public function generateJsonLd(): string {
		$hotelName   = $this->settings->get( 'metasearch.hotel_name', '' );
		$hotelNameAr = $this->settings->get( 'metasearch.hotel_name_ar', '' );
		$landingUrl  = $this->settings->get( 'metasearch.landing_page_url', '' );
		$hotelId     = $this->settings->get( 'metasearch.hotel_id', '' );
		$address     = $this->settings->get( 'metasearch.hotel_address', '' );
		$city        = $this->settings->get( 'metasearch.hotel_city', '' );
		$country     = $this->settings->get( 'metasearch.hotel_country', 'SY' );

		if ( empty( $hotelName ) ) {
			// Fall back to the general hotel name from plugin settings.
			$hotelName   = $this->settings->get( 'general.hotel_name', '' );
			$hotelNameAr = $this->settings->get( 'general.hotel_name_ar', '' );
		}

		if ( empty( $hotelName ) ) {
			return '';
		}

		$schema = [
			'@context'   => 'https://schema.org',
			'@type'      => 'Hotel',
			'name'       => $hotelName,
			'url'        => $landingUrl ?: home_url( '/' ),
			'identifier' => $hotelId,
			'address'    => [
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressLocality' => $city,
				'addressCountry'  => $country,
			],
		];

		if ( ! empty( $hotelNameAr ) ) {
			$schema['alternateName'] = $hotelNameAr;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		return '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
	}

	/**
	 * Return statistics about the feed that would be generated.
	 *
	 * Useful for the admin "test feed" endpoint to validate configuration
	 * without transmitting the entire XML body.
	 *
	 * @return array{room_count: int, rate_count: int, date_range_start: string, date_range_end: string, result_count: int}
	 */
	public function getFeedStats(): array {
		$roomTypes  = $this->roomTypeRepository->getAllOrdered();
		$today      = new \DateTimeImmutable( 'today', wp_timezone() );
		$endDate    = $today->modify( '+' . ( self::FEED_DAYS_AHEAD - 1 ) . ' days' );

		$activeRoomCount = 0;
		$ratePlanCodes   = [];
		$resultCount     = 0;

		foreach ( $roomTypes as $roomType ) {
			if ( ! $roomType->isActive() ) {
				continue;
			}

			$ratePlans = $this->ratePlanRepository->getForRoomType( $roomType->id );

			if ( empty( $ratePlans ) ) {
				continue;
			}

			$activeRoomCount++;

			foreach ( $ratePlans as $ratePlan ) {
				$code = $ratePlan->code ?? ( 'rp_' . $ratePlan->id );
				$ratePlanCodes[ $code ] = true;

				for ( $day = 0; $day < self::FEED_DAYS_AHEAD; $day++ ) {
					$checkIn = $today->modify( "+{$day} days" );

					if ( $ratePlan->isValidForDate( $checkIn->format( 'Y-m-d' ) ) ) {
						$resultCount++;
					}
				}
			}
		}

		return [
			'room_count'       => $activeRoomCount,
			'rate_count'       => count( $ratePlanCodes ),
			'date_range_start' => $today->format( 'Y-m-d' ),
			'date_range_end'   => $endDate->format( 'Y-m-d' ),
			'result_count'     => $resultCount,
		];
	}

	// ------------------------------------------------------------------
	// Private XML builder helpers
	// ------------------------------------------------------------------

	/**
	 * Build a single <RoomData> XML fragment.
	 */
	private function buildRoomDataXml( object $roomType, string $ratePlanCode ): string {
		$nameEn = esc_xml( $roomType->name ?? '' );
		$nameAr = esc_xml( $roomType->name_ar ?? $roomType->name ?? '' );

		return <<<XML
    <RoomData>
      <RoomID>{$roomType->id}</RoomID>
      <Name>
        <Text text="{$nameEn}" language="en"/>
        <Text text="{$nameAr}" language="ar"/>
      </Name>
      <RatePlanID>{$ratePlanCode}</RatePlanID>
    </RoomData>
XML;
	}

	/**
	 * Build a single <PackageData> XML fragment.
	 */
	private function buildPackageDataXml( object $ratePlan, string $ratePlanCode ): string {
		$nameEn      = esc_xml( $ratePlan->name ?? '' );
		$nameAr      = esc_xml( $ratePlan->name_ar ?? $ratePlan->name ?? '' );
		$refundable  = ! empty( $ratePlan->is_refundable ) ? 'true' : 'false';
		$cancelHours = (int) ( $ratePlan->cancellation_hours ?? 0 );
		$cancelDays  = max( 0, intdiv( $cancelHours, 24 ) );
		$breakfast   = ! empty( $ratePlan->includes_breakfast );
		$mealTag     = $breakfast ? 'breakfast' : 'none';

		return <<<XML
    <PackageData>
      <PackageID>{$ratePlanCode}</PackageID>
      <Name>
        <Text text="{$nameEn}" language="en"/>
        <Text text="{$nameAr}" language="ar"/>
      </Name>
      <Refundable available="{$refundable}" refundable_until_days="{$cancelDays}"/>
      <MealIncluded>{$mealTag}</MealIncluded>
    </PackageData>
XML;
	}

	/**
	 * Build a single <Result> XML fragment.
	 */
	private function buildResultXml(
		string $hotelId,
		int    $roomTypeId,
		string $ratePlanCode,
		string $checkIn,
		float  $baseRate,
		float  $tax,
		float  $otherFees,
		string $currency
	): string {
		$baseRateFmt  = number_format( $baseRate, 2, '.', '' );
		$taxFmt       = number_format( $tax, 2, '.', '' );
		$otherFeesFmt = number_format( $otherFees, 2, '.', '' );
		$hotelIdEsc   = esc_xml( $hotelId );

		return <<<XML
    <Result>
      <Property>{$hotelIdEsc}</Property>
      <Checkin>{$checkIn}</Checkin>
      <Nights>1</Nights>
      <RoomID>{$roomTypeId}</RoomID>
      <RatePlanID>{$ratePlanCode}</RatePlanID>
      <Baserate currency="{$currency}">{$baseRateFmt}</Baserate>
      <Tax currency="{$currency}">{$taxFmt}</Tax>
      <OtherFees currency="{$currency}">{$otherFeesFmt}</OtherFees>
    </Result>
XML;
	}

	/**
	 * Assemble the complete XML document from pre-built fragments.
	 *
	 * @param string   $hotelId             The property identifier.
	 * @param string[] $roomDataElements    Array of <RoomData> XML strings.
	 * @param string[] $packageDataElements Array of <PackageData> XML strings.
	 * @param string[] $resultElements      Array of <Result> XML strings.
	 * @return string Complete XML document.
	 */
	private function assembleXml(
		string $hotelId,
		array  $roomDataElements,
		array  $packageDataElements,
		array  $resultElements
	): string {
		$timestamp  = gmdate( 'Y-m-d\TH:i:s\Z' );
		$txnId      = 'nzl_' . gmdate( 'YmdHis' ) . '_' . wp_rand( 1000, 9999 );
		$hotelIdEsc = esc_xml( $hotelId );

		$roomData    = implode( "\n", $roomDataElements );
		$packageData = implode( "\n", $packageDataElements );
		$results     = implode( "\n", $resultElements );

		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Transaction timestamp="{$timestamp}" id="{$txnId}">
  <PropertyDataSet>
    <Property>{$hotelIdEsc}</Property>
{$roomData}
{$packageData}
{$results}
  </PropertyDataSet>
</Transaction>
XML;
	}
}
