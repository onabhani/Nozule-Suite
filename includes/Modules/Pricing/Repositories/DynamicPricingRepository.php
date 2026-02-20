<?php

namespace Nozule\Modules\Pricing\Repositories;

use Nozule\Core\Database;
use Nozule\Modules\Pricing\Models\DowRule;
use Nozule\Modules\Pricing\Models\EventOverride;
use Nozule\Modules\Pricing\Models\OccupancyRule;

/**
 * Repository for dynamic pricing CRUD and querying.
 *
 * Handles all three dynamic pricing tables: occupancy rules,
 * day-of-week rules, and event overrides.
 */
class DynamicPricingRepository {

	private Database $db;

	public function __construct( Database $db ) {
		$this->db = $db;
	}

	// =====================================================================
	// Occupancy Rules
	// =====================================================================

	/**
	 * Get the full occupancy_rules table name.
	 */
	private function occupancyTable(): string {
		return $this->db->table( 'occupancy_rules' );
	}

	/**
	 * Find an occupancy rule by ID.
	 */
	public function findOccupancyRule( int $id ): ?OccupancyRule {
		$table = $this->occupancyTable();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? OccupancyRule::fromRow( $row ) : null;
	}

	/**
	 * Get all occupancy rules ordered by priority descending.
	 *
	 * @return OccupancyRule[]
	 */
	public function getAllOccupancyRules(): array {
		$table = $this->occupancyTable();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY priority DESC, threshold_percent ASC"
		);

		return OccupancyRule::fromRows( $rows );
	}

	/**
	 * Get active occupancy rules for a room type, ordered by threshold ascending.
	 *
	 * @return OccupancyRule[]
	 */
	public function getActiveOccupancyRules( ?int $roomTypeId = null ): array {
		$table      = $this->occupancyTable();
		$conditions = [ "status = 'active'" ];
		$params     = [];

		if ( $roomTypeId !== null ) {
			$conditions[] = '(room_type_id = %d OR room_type_id IS NULL)';
			$params[]     = $roomTypeId;
		}

		$where = implode( ' AND ', $conditions );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY threshold_percent ASC, priority DESC",
			...$params
		);

		return OccupancyRule::fromRows( $rows );
	}

	/**
	 * Create a new occupancy rule.
	 *
	 * @return OccupancyRule|false
	 */
	public function createOccupancyRule( array $data ): OccupancyRule|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'occupancy_rules', $data );

		if ( $id === false ) {
			return false;
		}

		return $this->findOccupancyRule( $id );
	}

	/**
	 * Update an occupancy rule by ID.
	 */
	public function updateOccupancyRule( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		return $this->db->update( 'occupancy_rules', $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete an occupancy rule by ID.
	 */
	public function deleteOccupancyRule( int $id ): bool {
		return $this->db->delete( 'occupancy_rules', [ 'id' => $id ] ) !== false;
	}

	// =====================================================================
	// Day-of-Week Rules
	// =====================================================================

	/**
	 * Get the full dow_rules table name.
	 */
	private function dowTable(): string {
		return $this->db->table( 'dow_rules' );
	}

	/**
	 * Find a DOW rule by ID.
	 */
	public function findDowRule( int $id ): ?DowRule {
		$table = $this->dowTable();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? DowRule::fromRow( $row ) : null;
	}

	/**
	 * Get all DOW rules ordered by day of week.
	 *
	 * @return DowRule[]
	 */
	public function getAllDowRules(): array {
		$table = $this->dowTable();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY day_of_week ASC"
		);

		return DowRule::fromRows( $rows );
	}

	/**
	 * Get active DOW rules for a specific day and optional room type.
	 *
	 * @return DowRule[]
	 */
	public function getActiveDowRules( int $dayOfWeek, ?int $roomTypeId = null ): array {
		$table      = $this->dowTable();
		$conditions = [ "status = 'active'", 'day_of_week = %d' ];
		$params     = [ $dayOfWeek ];

		if ( $roomTypeId !== null ) {
			$conditions[] = '(room_type_id = %d OR room_type_id IS NULL)';
			$params[]     = $roomTypeId;
		}

		$where = implode( ' AND ', $conditions );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE {$where}",
			...$params
		);

		return DowRule::fromRows( $rows );
	}

	/**
	 * Create a new DOW rule.
	 *
	 * @return DowRule|false
	 */
	public function createDowRule( array $data ): DowRule|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'dow_rules', $data );

		if ( $id === false ) {
			return false;
		}

		return $this->findDowRule( $id );
	}

	/**
	 * Update a DOW rule by ID.
	 */
	public function updateDowRule( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		return $this->db->update( 'dow_rules', $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete a DOW rule by ID.
	 */
	public function deleteDowRule( int $id ): bool {
		return $this->db->delete( 'dow_rules', [ 'id' => $id ] ) !== false;
	}

	// =====================================================================
	// Event Overrides
	// =====================================================================

	/**
	 * Get the full event_overrides table name.
	 */
	private function eventTable(): string {
		return $this->db->table( 'event_overrides' );
	}

	/**
	 * Find an event override by ID.
	 */
	public function findEventOverride( int $id ): ?EventOverride {
		$table = $this->eventTable();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE id = %d",
			$id
		);

		return $row ? EventOverride::fromRow( $row ) : null;
	}

	/**
	 * Get all event overrides ordered by start date.
	 *
	 * @return EventOverride[]
	 */
	public function getAllEventOverrides(): array {
		$table = $this->eventTable();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} ORDER BY start_date ASC, priority DESC"
		);

		return EventOverride::fromRows( $rows );
	}

	/**
	 * Get active event overrides for a specific date and optional room type.
	 *
	 * @return EventOverride[]
	 */
	public function getActiveEventOverrides( string $date, ?int $roomTypeId = null ): array {
		$table      = $this->eventTable();
		$conditions = [ "status = 'active'", 'start_date <= %s', 'end_date >= %s' ];
		$params     = [ $date, $date ];

		if ( $roomTypeId !== null ) {
			$conditions[] = '(room_type_id = %d OR room_type_id IS NULL)';
			$params[]     = $roomTypeId;
		}

		$where = implode( ' AND ', $conditions );
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY priority DESC",
			...$params
		);

		return EventOverride::fromRows( $rows );
	}

	/**
	 * Create a new event override.
	 *
	 * @return EventOverride|false
	 */
	public function createEventOverride( array $data ): EventOverride|false {
		$now = current_time( 'mysql', true );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		$id = $this->db->insert( 'event_overrides', $data );

		if ( $id === false ) {
			return false;
		}

		return $this->findEventOverride( $id );
	}

	/**
	 * Update an event override by ID.
	 */
	public function updateEventOverride( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql', true );
		return $this->db->update( 'event_overrides', $data, [ 'id' => $id ] ) !== false;
	}

	/**
	 * Delete an event override by ID.
	 */
	public function deleteEventOverride( int $id ): bool {
		return $this->db->delete( 'event_overrides', [ 'id' => $id ] ) !== false;
	}

	// =====================================================================
	// Occupancy Calculation
	// =====================================================================

	/**
	 * Calculate the current occupancy percentage for a room type on a given date.
	 *
	 * Occupancy = (booked rooms / total rooms) * 100.
	 * Uses the bookings and rooms tables to compute this.
	 *
	 * @param int    $roomTypeId The room type to check.
	 * @param string $date       The date to check (Y-m-d).
	 * @return float Occupancy percentage (0-100).
	 */
	public function getOccupancyPercent( int $roomTypeId, string $date ): float {
		$roomsTable    = $this->db->table( 'rooms' );
		$bookingsTable = $this->db->table( 'bookings' );

		// Count total available rooms for this type.
		$totalRooms = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$roomsTable} WHERE room_type_id = %d AND status != 'out_of_order'",
			$roomTypeId
		);

		if ( $totalRooms === 0 ) {
			return 0.0;
		}

		// Count booked rooms for this date.
		$bookedRooms = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$bookingsTable}
			WHERE room_type_id = %d
				AND check_in <= %s
				AND check_out > %s
				AND status NOT IN ('cancelled', 'no_show')",
			$roomTypeId,
			$date,
			$date
		);

		return round( ( $bookedRooms / $totalRooms ) * 100, 2 );
	}
}
