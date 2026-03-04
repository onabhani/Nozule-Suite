<?php

namespace Nozule\Modules\Employees\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Employees\Models\Employee;

/**
 * Repository for employee database operations.
 */
class EmployeeRepository extends BaseRepository {

	protected string $table = 'employees';
	protected string $model = Employee::class;

	/**
	 * Find an employee by their WordPress user ID.
	 */
	public function findByWpUserId( int $wpUserId ): ?Employee {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE wp_user_id = %d LIMIT 1",
			$wpUserId
		);

		return $row ? Employee::fromRow( $row ) : null;
	}

	/**
	 * Find an employee by email address.
	 */
	public function findByEmail( string $email ): ?Employee {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE email = %s LIMIT 1",
			$email
		);

		return $row ? Employee::fromRow( $row ) : null;
	}

	/**
	 * Get all active employees.
	 *
	 * @return Employee[]
	 */
	public function findActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY display_name ASC"
		);

		return Employee::fromRows( $rows );
	}

	/**
	 * Get all employees with a given role.
	 *
	 * @return Employee[]
	 */
	public function findByRole( string $role ): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE role = %s ORDER BY display_name ASC",
			$role
		);

		return Employee::fromRows( $rows );
	}
}
