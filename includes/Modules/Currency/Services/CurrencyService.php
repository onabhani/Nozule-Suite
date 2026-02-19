<?php

namespace Nozule\Modules\Currency\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Currency\Models\Currency;
use Nozule\Modules\Currency\Models\ExchangeRate;
use Nozule\Modules\Currency\Repositories\CurrencyRepository;
use Nozule\Modules\Currency\Repositories\ExchangeRateRepository;
use Nozule\Modules\Currency\Validators\CurrencyValidator;

/**
 * Service layer for currency and exchange rate business logic.
 */
class CurrencyService {

	private CurrencyRepository $currencyRepo;
	private ExchangeRateRepository $rateRepo;
	private CurrencyValidator $validator;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		CurrencyRepository $currencyRepo,
		ExchangeRateRepository $rateRepo,
		CurrencyValidator $validator,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->currencyRepo = $currencyRepo;
		$this->rateRepo     = $rateRepo;
		$this->validator    = $validator;
		$this->events       = $events;
		$this->logger       = $logger;
	}

	/**
	 * Get all active currencies.
	 *
	 * @return Currency[]
	 */
	public function getCurrencies(): array {
		return $this->currencyRepo->getActive();
	}

	/**
	 * Get a currency by ID.
	 */
	public function getCurrency( int $id ): ?Currency {
		return $this->currencyRepo->find( $id );
	}

	/**
	 * Get a currency by its ISO code.
	 */
	public function getCurrencyByCode( string $code ): ?Currency {
		return $this->currencyRepo->findByCode( $code );
	}

	/**
	 * Create a new currency.
	 *
	 * @return Currency|array Currency on success, array of errors on failure.
	 */
	public function createCurrency( array $data ): Currency|array {
		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizeCurrencyData( $data );

		$currency = $this->currencyRepo->create( $sanitized );

		if ( ! $currency ) {
			$this->logger->error( 'Failed to create currency', [ 'data' => $sanitized ] );
			return [ 'general' => [ __( 'Failed to create currency.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Currency created', [ 'id' => $currency->id, 'code' => $currency->code ] );
		$this->events->dispatch( 'currency/created', $currency );

		return $currency;
	}

	/**
	 * Update an existing currency.
	 *
	 * @return Currency|array Currency on success, array of errors on failure.
	 */
	public function updateCurrency( int $id, array $data ): Currency|array {
		$currency = $this->currencyRepo->find( $id );

		if ( ! $currency ) {
			return [ 'id' => [ sprintf( __( 'Currency with ID %d not found.', 'nozule' ), $id ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizeCurrencyData( $data );

		$updated = $this->currencyRepo->update( $id, $sanitized );

		if ( ! $updated ) {
			$this->logger->error( 'Failed to update currency', [ 'id' => $id, 'data' => $sanitized ] );
			return [ 'general' => [ __( 'Failed to update currency.', 'nozule' ) ] ];
		}

		$currency = $this->currencyRepo->find( $id );

		$this->logger->info( 'Currency updated', [ 'id' => $id, 'code' => $currency->code ] );
		$this->events->dispatch( 'currency/updated', $currency );

		return $currency;
	}

	/**
	 * Delete a currency.
	 *
	 * The default currency cannot be deleted.
	 *
	 * @return bool|array True on success, array of errors on failure.
	 */
	public function deleteCurrency( int $id ): bool|array {
		$currency = $this->currencyRepo->find( $id );

		if ( ! $currency ) {
			return [ 'id' => [ sprintf( __( 'Currency with ID %d not found.', 'nozule' ), $id ) ] ];
		}

		if ( $currency->isDefault() ) {
			return [ 'code' => [ __( 'Cannot delete the default currency.', 'nozule' ) ] ];
		}

		$deleted = $this->currencyRepo->delete( $id );

		if ( ! $deleted ) {
			$this->logger->error( 'Failed to delete currency', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to delete currency.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Currency deleted', [ 'id' => $id, 'code' => $currency->code ] );
		$this->events->dispatch( 'currency/deleted', $currency );

		return true;
	}

	/**
	 * Set a currency as the default.
	 */
	public function setDefaultCurrency( int $id ): bool {
		$currency = $this->currencyRepo->find( $id );

		if ( ! $currency ) {
			return false;
		}

		$result = $this->currencyRepo->setDefault( $id );

		if ( $result ) {
			$this->logger->info( 'Default currency changed', [ 'id' => $id, 'code' => $currency->code ] );
			$this->events->dispatch( 'currency/default_changed', $currency );
		}

		return $result;
	}

	/**
	 * Convert an amount from one currency to another.
	 *
	 * @param float       $amount The amount to convert.
	 * @param string      $from   Source currency code.
	 * @param string      $to     Target currency code.
	 * @param string|null $date   Optional date for historical rate (Y-m-d format).
	 * @return float The converted amount.
	 */
	public function convert( float $amount, string $from, string $to, ?string $date = null ): float {
		$from = strtoupper( $from );
		$to   = strtoupper( $to );

		if ( $from === $to ) {
			return $amount;
		}

		$rate = $this->getExchangeRate( $from, $to, $date );

		return $amount * $rate;
	}

	/**
	 * Get the exchange rate between two currencies.
	 *
	 * Lookup order:
	 * 1. Direct rate in exchange_rates table (from -> to).
	 * 2. Inverse rate in exchange_rates table (to -> from).
	 * 3. Cross-rate via currency exchange_rate fields (both relative to base).
	 *
	 * @param string      $from Source currency code.
	 * @param string      $to   Target currency code.
	 * @param string|null $date Optional date for historical rate.
	 * @return float The exchange rate (multiply source amount by this to get target amount).
	 */
	public function getExchangeRate( string $from, string $to, ?string $date = null ): float {
		$from = strtoupper( $from );
		$to   = strtoupper( $to );

		if ( $from === $to ) {
			return 1.0;
		}

		// Try direct rate from the exchange_rates table.
		$rate_record = $date
			? $this->rateRepo->getForDate( $from, $to, $date )
			: $this->rateRepo->getLatest( $from, $to );

		if ( $rate_record ) {
			return $rate_record->rate;
		}

		// Try inverse rate.
		$inverse_record = $date
			? $this->rateRepo->getForDate( $to, $from, $date )
			: $this->rateRepo->getLatest( $to, $from );

		if ( $inverse_record && $inverse_record->rate > 0 ) {
			return 1.0 / $inverse_record->rate;
		}

		// Fall back to cross-rate via stored exchange_rate fields on the currency records.
		$from_currency = $this->currencyRepo->findByCode( $from );
		$to_currency   = $this->currencyRepo->findByCode( $to );

		if ( $from_currency && $to_currency && $from_currency->exchange_rate > 0 ) {
			return $to_currency->exchange_rate / $from_currency->exchange_rate;
		}

		$this->logger->warning( 'Exchange rate not found, returning 1.0', [
			'from' => $from,
			'to'   => $to,
			'date' => $date,
		] );

		return 1.0;
	}

	/**
	 * Update or create an exchange rate record.
	 *
	 * @param string $from   Source currency code.
	 * @param string $to     Target currency code.
	 * @param float  $rate   The exchange rate.
	 * @param string $source The source of the rate (e.g., 'manual', 'api').
	 * @return ExchangeRate|array ExchangeRate on success, array of errors on failure.
	 */
	public function updateExchangeRate(
		string $from,
		string $to,
		float $rate,
		string $source = 'manual'
	): ExchangeRate|array {
		$from = strtoupper( $from );
		$to   = strtoupper( $to );

		if ( $rate <= 0 ) {
			return [ 'rate' => [ __( 'Exchange rate must be greater than zero.', 'nozule' ) ] ];
		}

		if ( $from === $to ) {
			return [ 'currency' => [ __( 'Source and target currencies must be different.', 'nozule' ) ] ];
		}

		// Verify both currencies exist.
		$from_currency = $this->currencyRepo->findByCode( $from );
		$to_currency   = $this->currencyRepo->findByCode( $to );

		if ( ! $from_currency ) {
			return [ 'from_currency' => [ sprintf( __( 'Currency %s not found.', 'nozule' ), $from ) ] ];
		}
		if ( ! $to_currency ) {
			return [ 'to_currency' => [ sprintf( __( 'Currency %s not found.', 'nozule' ), $to ) ] ];
		}

		$record = $this->rateRepo->create( [
			'from_currency'  => $from,
			'to_currency'    => $to,
			'rate'           => $rate,
			'source'         => sanitize_text_field( $source ),
			'effective_date' => current_time( 'Y-m-d' ),
		] );

		if ( ! $record ) {
			$this->logger->error( 'Failed to create exchange rate', [
				'from' => $from,
				'to'   => $to,
				'rate' => $rate,
			] );
			return [ 'general' => [ __( 'Failed to save exchange rate.', 'nozule' ) ] ];
		}

		$this->logger->info( 'Exchange rate updated', [
			'from'   => $from,
			'to'     => $to,
			'rate'   => $rate,
			'source' => $source,
		] );
		$this->events->dispatch( 'currency/rate_updated', $record );

		return $record;
	}

	/**
	 * Get exchange rate history for a currency pair.
	 *
	 * @param string $from  Source currency code.
	 * @param string $to    Target currency code.
	 * @param int    $limit Maximum number of records.
	 * @return ExchangeRate[]
	 */
	public function getExchangeHistory( string $from, string $to, int $limit = 30 ): array {
		return $this->rateRepo->getHistory( $from, $to, $limit );
	}

	/**
	 * Resolve a guest's nationality into a guest type for pricing.
	 *
	 * Returns 'syrian' if the nationality matches Syria (SY, Syrian, or the
	 * Arabic equivalent), otherwise returns 'non_syrian'.
	 *
	 * @param string $nationality The guest's nationality.
	 * @return string 'syrian' or 'non_syrian'.
	 */
	public function resolveGuestType( string $nationality ): string {
		$normalized = mb_strtolower( trim( $nationality ) );

		$syrian_values = [
			'sy',
			'syrian',
			'سوري',
			'سورية',
			'سوريا',
		];

		if ( in_array( $normalized, $syrian_values, true ) ) {
			return 'syrian';
		}

		return 'non_syrian';
	}

	/**
	 * Sanitize currency data before storage.
	 */
	private function sanitizeCurrencyData( array $data ): array {
		$sanitized = [];

		if ( array_key_exists( 'code', $data ) ) {
			$sanitized['code'] = strtoupper( sanitize_text_field( $data['code'] ) );
		}

		$text_fields = [ 'name', 'name_ar', 'symbol', 'symbol_ar' ];
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( array_key_exists( 'decimal_places', $data ) ) {
			$sanitized['decimal_places'] = absint( $data['decimal_places'] );
		}

		if ( array_key_exists( 'exchange_rate', $data ) ) {
			$sanitized['exchange_rate'] = (float) $data['exchange_rate'];
		}

		if ( array_key_exists( 'is_default', $data ) ) {
			$sanitized['is_default'] = (int) (bool) $data['is_default'];
		}

		if ( array_key_exists( 'is_active', $data ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( array_key_exists( 'sort_order', $data ) ) {
			$sanitized['sort_order'] = absint( $data['sort_order'] );
		}

		return $sanitized;
	}
}
