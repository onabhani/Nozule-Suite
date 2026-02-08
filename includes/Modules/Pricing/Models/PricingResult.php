<?php

namespace Venezia\Modules\Pricing\Models;

/**
 * Value object representing the result of a pricing calculation.
 *
 * This is a read-only data transfer object that does NOT extend BaseModel.
 * Once constructed, properties should not be modified.
 */
final class PricingResult {

	/**
	 * @param float  $subtotal     Sum of nightly rates before taxes, fees, and discounts.
	 * @param float  $taxes        Total tax amount.
	 * @param float  $fees         Total fees (e.g. extra person charges, service fees).
	 * @param float  $discount     Total discount amount (always >= 0).
	 * @param float  $total        Final amount after taxes, fees, and discounts.
	 * @param string $currency     ISO 4217 currency code (e.g. "USD", "SAR").
	 * @param float  $exchangeRate Exchange rate relative to the base currency.
	 * @param array  $nightlyRates Associative array of date (Y-m-d) => rate for each night.
	 */
	public function __construct(
		public readonly float  $subtotal,
		public readonly float  $taxes,
		public readonly float  $fees,
		public readonly float  $discount,
		public readonly float  $total,
		public readonly string $currency,
		public readonly float  $exchangeRate,
		public readonly array  $nightlyRates,
	) {
	}

	/**
	 * Get the number of nights in this pricing calculation.
	 */
	public function getNights(): int {
		return count( $this->nightlyRates );
	}

	/**
	 * Get the average nightly rate.
	 */
	public function getAverageNightlyRate(): float {
		$nights = $this->getNights();

		if ( $nights === 0 ) {
			return 0.0;
		}

		return $this->subtotal / $nights;
	}

	/**
	 * Convert to an associative array suitable for JSON serialization.
	 */
	public function toArray(): array {
		return [
			'subtotal'            => round( $this->subtotal, 2 ),
			'taxes'               => round( $this->taxes, 2 ),
			'fees'                => round( $this->fees, 2 ),
			'discount'            => round( $this->discount, 2 ),
			'total'               => round( $this->total, 2 ),
			'currency'            => $this->currency,
			'exchange_rate'       => round( $this->exchangeRate, 6 ),
			'nightly_rates'       => array_map(
				fn( float $rate ) => round( $rate, 2 ),
				$this->nightlyRates
			),
			'nights'              => $this->getNights(),
			'average_nightly_rate' => round( $this->getAverageNightlyRate(), 2 ),
		];
	}
}
