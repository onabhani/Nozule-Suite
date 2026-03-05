<?php

namespace Nozule\Core;

use Nozule\Modules\Employees\Repositories\EmployeeRepository;

/**
 * Resolves and holds the active property context for the current user.
 *
 * Super admins may switch between properties or view all at once;
 * regular staff are locked to their assigned property_id.
 */
class PropertyScope {

	private int|null $activePropertyId = null;
	private bool $isSuperAdmin = false;

	public function __construct( private EmployeeRepository $employees ) {}

	/**
	 * Resolve the active property for the given WordPress user.
	 */
	public function resolve( int $wpUserId ): void {
		$this->isSuperAdmin = user_can( $wpUserId, 'nzl_super_admin' );

		if ( $this->isSuperAdmin ) {
			// Read from session if set, otherwise null (all properties).
			$this->activePropertyId = $this->getSessionProperty();
		} else {
			$employee               = $this->employees->findByWpUserId( $wpUserId );
			$this->activePropertyId = $employee?->property_id ?? 1;
		}
	}

	public function getActivePropertyId(): int|null {
		return $this->activePropertyId;
	}

	public function isSuperAdmin(): bool {
		return $this->isSuperAdmin;
	}

	public function canAccessAllProperties(): bool {
		return $this->isSuperAdmin && $this->activePropertyId === null;
	}

	/**
	 * Switch the active property (super admins only).
	 */
	public function switchProperty( int $propertyId ): void {
		if ( ! $this->isSuperAdmin ) {
			throw new \RuntimeException( 'Only super admins can switch properties.' );
		}
		$this->setSessionProperty( $propertyId );
		$this->activePropertyId = $propertyId;
	}

	/**
	 * Clear the property filter so all properties are visible (super admins only).
	 */
	public function clearPropertyFilter(): void {
		if ( ! $this->isSuperAdmin ) {
			return;
		}
		$this->clearSessionProperty();
		$this->activePropertyId = null;
	}

	// ------------------------------------------------------------------
	// Session stubs — Phase 2 will implement real session storage.
	// ------------------------------------------------------------------

	private function getSessionProperty(): int|null {
		return null;
	}

	private function setSessionProperty( int $id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}

	private function clearSessionProperty(): void {
	}
}
