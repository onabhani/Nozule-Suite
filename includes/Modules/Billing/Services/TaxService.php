<?php

namespace Nozule\Modules\Billing\Services;

use Nozule\Core\CacheManager;
use Nozule\Core\Logger;
use Nozule\Modules\Billing\Models\Tax;
use Nozule\Modules\Billing\Repositories\TaxRepository;
use Nozule\Modules\Billing\Validators\TaxValidator;

/**
 * Service layer orchestrating tax operations.
 */
class TaxService {

	private TaxRepository $taxRepository;
	private TaxValidator $taxValidator;
	private CacheManager $cache;
	private Logger $logger;

	public function __construct(
		TaxRepository $taxRepository,
		TaxValidator $taxValidator,
		CacheManager $cache,
		Logger $logger
	) {
		$this->taxRepository = $taxRepository;
		$this->taxValidator  = $taxValidator;
		$this->cache         = $cache;
		$this->logger        = $logger;
	}

	/**
	 * Get all active taxes (cached).
	 *
	 * @return Tax[]
	 */
	public function getActiveTaxes(): array {
		$cached = $this->cache->get( 'billing_taxes_active' );
		if ( $cached !== false ) {
			return $cached;
		}

		$taxes = $this->taxRepository->getActive();
		$this->cache->set( 'billing_taxes_active', $taxes, 300 );

		return $taxes;
	}

	/**
	 * Get all taxes (active and inactive).
	 *
	 * @return Tax[]
	 */
	public function getAllTaxes(): array {
		return $this->taxRepository->getAll();
	}

	/**
	 * Find a tax by ID.
	 */
	public function findTax( int $id ): ?Tax {
		return $this->taxRepository->find( $id );
	}

	/**
	 * Create a new tax.
	 *
	 * @return Tax|array Tax on success, array of errors on failure.
	 */
	public function createTax( array $data ): Tax|array {
		if ( ! $this->taxValidator->validateCreate( $data ) ) {
			return $this->taxValidator->getErrors();
		}

		$tax = $this->taxRepository->create( $data );
		if ( ! $tax ) {
			$this->logger->error( 'Failed to create tax', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create tax.', 'nozule' ) ] ];
		}

		$this->invalidateTaxCache();
		$this->logger->info( 'Tax created', [ 'id' => $tax->id, 'name' => $tax->name ] );

		return $tax;
	}

	/**
	 * Update an existing tax.
	 *
	 * @return Tax|array Updated Tax on success, errors on failure.
	 */
	public function updateTax( int $id, array $data ): Tax|array {
		$existing = $this->taxRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Tax not found.', 'nozule' ) ] ];
		}

		if ( ! $this->taxValidator->validateUpdate( $data ) ) {
			return $this->taxValidator->getErrors();
		}

		$success = $this->taxRepository->update( $id, $data );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update tax', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update tax.', 'nozule' ) ] ];
		}

		$updated = $this->taxRepository->find( $id );

		$this->invalidateTaxCache();
		$this->logger->info( 'Tax updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a tax.
	 */
	public function deleteTax( int $id ): true|array {
		$existing = $this->taxRepository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Tax not found.', 'nozule' ) ] ];
		}

		$success = $this->taxRepository->delete( $id );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to delete tax.', 'nozule' ) ] ];
		}

		$this->invalidateTaxCache();
		$this->logger->info( 'Tax deleted', [ 'id' => $id, 'name' => $existing->name ] );

		return true;
	}

	/**
	 * Calculate taxes for a given amount and category.
	 *
	 * Returns an array with the individual tax breakdowns and total tax amount.
	 *
	 * @param float  $amount   The base amount to calculate taxes on.
	 * @param string $category The charge category (room_charge, extra, service).
	 * @return array{taxes: array, total_tax: float}
	 */
	public function calculateTaxes( float $amount, string $category ): array {
		$activeTaxes = $this->getActiveTaxes();
		$taxBreakdown = [];
		$totalTax = 0.0;

		foreach ( $activeTaxes as $tax ) {
			// Check if this tax applies to the given category.
			if ( $tax->applies_to !== Tax::APPLIES_ALL && $tax->applies_to !== $category ) {
				continue;
			}

			$taxAmount = $tax->calculateAmount( $amount );
			$totalTax += $taxAmount;

			$taxBreakdown[] = [
				'tax_id'      => $tax->id,
				'tax_name'    => $tax->name,
				'tax_name_ar' => $tax->name_ar,
				'tax_rate'    => $tax->rate,
				'tax_type'    => $tax->type,
				'tax_amount'  => $taxAmount,
			];
		}

		return [
			'taxes'     => $taxBreakdown,
			'total_tax' => round( $totalTax, 2 ),
		];
	}

	/**
	 * Invalidate all tax-related caches.
	 */
	private function invalidateTaxCache(): void {
		$this->cache->delete( 'billing_taxes_active' );
		$this->cache->invalidateTag( 'billing_taxes' );
	}
}
