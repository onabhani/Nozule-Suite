<?php

namespace Nozule\Core;

use Nozule\Modules\Employees\Repositories\EmployeeRepository;

/**
 * Resolves and holds the active property context for the current user.
 *
 * Super admins may switch between properties or view all at once;
 * regular staff are locked to their assigned property_id.
 *
 * Property selection persists across requests via wp_usermeta.
 */
class PropertyScope {

	private const META_KEY = 'nzl_active_property_id';

	private int|null $activePropertyId = null;
	private bool $isSuperAdmin = false;
	private int $wpUserId = 0;

	public function __construct( private EmployeeRepository $employees ) {}

	/**
	 * Resolve the active property for the given WordPress user.
	 */
	public function resolve( int $wpUserId ): void {
		$this->wpUserId     = $wpUserId;
		$this->isSuperAdmin = user_can( $wpUserId, 'nzl_super_admin' );

		if ( $this->isSuperAdmin ) {
			// Read persisted property filter from user meta.
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
	// Session persistence via wp_usermeta (Phase 2 implementation).
	// ------------------------------------------------------------------

	private function getSessionProperty(): int|null {
		if ( ! $this->wpUserId ) {
			return null;
		}

		$value = get_user_meta( $this->wpUserId, self::META_KEY, true );

		if ( $value === '' || $value === false ) {
			return null;
		}

		return (int) $value ?: null;
	}

	private function setSessionProperty( int $id ): void {
		if ( ! $this->wpUserId ) {
			return;
		}

		update_user_meta( $this->wpUserId, self::META_KEY, $id );
	}

	private function clearSessionProperty(): void {
		if ( ! $this->wpUserId ) {
			return;
		}

		delete_user_meta( $this->wpUserId, self::META_KEY );
	}
}
