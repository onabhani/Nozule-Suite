<?php

namespace Nozule\CLI;

use Nozule\Core\Database;
use Nozule\Modules\Employees\Repositories\EmployeeRepository;

/**
 * WP-CLI command: wp nozule sync-employees
 *
 * Dev/debug only — seeds nzl_employees from existing WordPress users
 * that hold Nozule staff roles.
 */
class SyncEmployeesCommand {

	private const SYNC_ROLES = [
		'nzl_manager',
		'nzl_reception',
		'nzl_housekeeper',
		'nzl_finance',
		'nzl_concierge',
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
			'role__in' => self::SYNC_ROLES,
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

			// Determine the first nzl_ role.
			$role = '';
			foreach ( $user->roles as $r ) {
				if ( str_starts_with( $r, 'nzl_' ) ) {
					$role = $r;
					break;
				}
			}

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
}
