<?php

namespace Nozule\Core;

/**
 * Single source of truth for hotel staff role slugs.
 *
 * Used by role registration (Activator, EmployeesModule), employee
 * management, housekeeping staff queries, and staff-isolation logic.
 */
class HotelRoles {

	/**
	 * Get all hotel staff role slugs.
	 *
	 * @return string[]
	 */
	public static function getSlugs(): array {
		return [
			'nzl_manager',
			'nzl_reception',
			'nzl_housekeeper',
			'nzl_finance',
			'nzl_concierge',
		];
	}
}
