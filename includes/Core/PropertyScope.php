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

	private const USER_META_KEY = 'nzl_active_property_id';

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
			// Read persisted preference from user meta; null = all properties.
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
	// Persisted property preference (per-user meta).
	//
	// User meta is used instead of PHP sessions so the preference survives
	// across requests, devices, and browser restarts without requiring
	// session_start() (which conflicts with object caching in many hosts).
	// ------------------------------------------------------------------

	private function getSessionProperty(): int|null {
		$userId = $this->wpUserId ?: get_current_user_id();
		if ( $userId <= 0 ) {
			return null;
		}
		$stored = get_user_meta( $userId, self::USER_META_KEY, true );
		if ( $stored === '' || $stored === null || $stored === false ) {
			return null;
		}
		$id = (int) $stored;
		return $id > 0 ? $id : null;
	}

	private function setSessionProperty( int $id ): void {
		$userId = $this->wpUserId ?: get_current_user_id();
		if ( $userId <= 0 ) {
			return;
		}
		update_user_meta( $userId, self::USER_META_KEY, $id );
	}

	private function clearSessionProperty(): void {
		$userId = $this->wpUserId ?: get_current_user_id();
		if ( $userId <= 0 ) {
			return;
		}
		delete_user_meta( $userId, self::USER_META_KEY );
	}
}
