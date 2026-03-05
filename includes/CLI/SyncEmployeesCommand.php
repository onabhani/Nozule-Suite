<?php

namespace Nozule\CLI;

use Nozule\Core\Database;
use Nozule\Core\HotelRoles;
use Nozule\Modules\Employees\Repositories\EmployeeRepository;

/**
 * WP-CLI command: wp nozule sync-employees
 *
 * Dev/debug only — seeds nzl_employees from existing WordPress users
 * that hold Nozule staff roles.
 */
class SyncEmployeesCommand {

	/**
	 * Roles ordered by privilege (highest first).
	 * Used to resolve which role wins when a user holds multiple.
	 */
	private const ROLE_PRIORITY = [
		'nzl_manager',
		'nzl_reception',
		'nzl_finance',
		'nzl_concierge',
		'nzl_housekeeper',
	];

	/**
	 * Sync WordPress users with Nozule staff roles into nzl_employees.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nozule sync-employees
	 *
	 * @when after_wp_load
	 */
	public function __invoke(): void {
		$repo = new EmployeeRepository( new Database() );

		$users = get_users( [
			'role__in' => HotelRoles::getSlugs(),
		] );

		if ( empty( $users ) ) {
			\WP_CLI::warning( 'No WordPress users found with Nozule staff roles.' );
			return;
		}

		$inserted = 0;
		$skipped  = 0;

		foreach ( $users as $user ) {
			// Skip if already synced.
			if ( $repo->findByWpUserId( $user->ID ) ) {
				++$skipped;
				\WP_CLI::log( "Skipped: {$user->user_email} (already exists)" );
				continue;
			}

			// Pick the highest-privilege nzl_ role the user holds.
			$role = $this->resolveHighestRole( $user->roles );

			if ( $role === '' ) {
				++$skipped;
				\WP_CLI::log( "Skipped: {$user->user_email} (no nzl_ role found)" );
				continue;
			}

			$result = $repo->create( [
				'wp_user_id'   => $user->ID,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'role'         => $role,
				'is_active'    => 1,
			] );

			if ( $result ) {
				++$inserted;
				\WP_CLI::log( "Inserted: {$user->user_email} ({$role})" );
			} else {
				\WP_CLI::warning( "Failed to insert: {$user->user_email}" );
			}
		}

		\WP_CLI::success( "Done. Inserted: {$inserted}, Skipped: {$skipped}" );
	}

	/**
	 * Return the highest-privilege nzl_ role from a list of user roles.
	 *
	 * @param string[] $userRoles WordPress roles assigned to the user.
	 * @return string The highest-privilege role slug, or empty string.
	 */
	private function resolveHighestRole( array $userRoles ): string {
		foreach ( self::ROLE_PRIORITY as $candidate ) {
			if ( in_array( $candidate, $userRoles, true ) ) {
				return $candidate;
			}
		}
		return '';
	}
}
