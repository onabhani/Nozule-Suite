<?php

namespace Nozule\Modules\WhatsApp\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Core\Database;
use Nozule\Modules\WhatsApp\Models\WhatsAppTemplate;

/**
 * Repository for WhatsApp template database operations.
 */
class WhatsAppTemplateRepository extends BaseRepository {

	protected string $table = 'whatsapp_templates';
	protected string $model = WhatsAppTemplate::class;

	public function __construct( Database $db ) {
		parent::__construct( $db );
	}

	// ── Finders ─────────────────────────────────────────────────────

	/**
	 * Find a template by its unique slug.
	 */
	public function findBySlug( string $slug ): ?WhatsAppTemplate {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE slug = %s LIMIT 1",
			$slug
		);

		return $row ? WhatsAppTemplate::fromRow( $row ) : null;
	}

	/**
	 * Get the active template assigned to a specific trigger event.
	 */
	public function getByTriggerEvent( string $event ): ?WhatsAppTemplate {
		$table = $this->tableName();
		$row   = $this->db->getRow(
			"SELECT * FROM {$table} WHERE trigger_event = %s AND is_active = 1 LIMIT 1",
			$event
		);

		return $row ? WhatsAppTemplate::fromRow( $row ) : null;
	}

	/**
	 * Get all active templates.
	 *
	 * @return WhatsAppTemplate[]
	 */
	public function getActive(): array {
		$table = $this->tableName();
		$rows  = $this->db->getResults(
			"SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
		);

		return WhatsAppTemplate::fromRows( $rows );
	}

	// ── CRUD Overrides ──────────────────────────────────────────────

	/**
	 * Create a new WhatsApp template.
	 *
	 * @return WhatsAppTemplate|false
	 */
	public function create( array $data ): WhatsAppTemplate|false {
		$now                = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;

		// Encode variables if provided as array.
		if ( isset( $data['variables'] ) && is_array( $data['variables'] ) ) {
			$data['variables'] = wp_json_encode( $data['variables'] );
		}

		$id = $this->db->insert( $this->table, $data );

		if ( $id === false ) {
			return false;
		}

		return $this->find( $id );
	}

	/**
	 * Update a template by ID.
	 */
	public function update( int $id, array $data ): bool {
		$data['updated_at'] = current_time( 'mysql' );

		// Encode variables if provided as array.
		if ( isset( $data['variables'] ) && is_array( $data['variables'] ) ) {
			$data['variables'] = wp_json_encode( $data['variables'] );
		}

		return $this->db->update( $this->table, $data, [ 'id' => $id ] ) !== false;
	}

	// ── Paginated Listing ───────────────────────────────────────────

	/**
	 * List templates with pagination and filtering.
	 *
	 * @param array $args {
	 *     Optional. Arguments for listing templates.
	 *
	 *     @type string $search        Free-text search on name, slug.
	 *     @type string $trigger_event Filter by trigger event.
	 *     @type string $orderby       Column to order by. Default 'created_at'.
	 *     @type string $order         Sort direction (ASC|DESC). Default 'DESC'.
	 *     @type int    $per_page      Results per page. Default 20.
	 *     @type int    $page          Page number (1-based). Default 1.
	 * }
	 * @return array{ items: WhatsAppTemplate[], total: int, pages: int }
	 */
	public function list( array $args = [] ): array {
		$defaults = [
			'search'        => '',
			'trigger_event' => '',
			'orderby'       => 'created_at',
			'order'         => 'DESC',
			'per_page'      => 20,
			'page'          => 1,
		];

		$args       = wp_parse_args( $args, $defaults );
		$table      = $this->tableName();
		$offset     = ( $args['page'] - 1 ) * $args['per_page'];
		$conditions = [];
		$params     = [];

		// Trigger event filter.
		if ( ! empty( $args['trigger_event'] ) ) {
			$conditions[] = 'trigger_event = %s';
			$params[]     = $args['trigger_event'];
		}

		// Free-text search.
		if ( ! empty( $args['search'] ) ) {
			$like         = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
			$conditions[] = '(name LIKE %s OR slug LIKE %s)';
			$params[]     = $like;
			$params[]     = $like;
		}

		$where = ! empty( $conditions )
			? 'WHERE ' . implode( ' AND ', $conditions )
			: '';

		// Sanitize ordering.
		$allowed_columns = [ 'id', 'name', 'slug', 'trigger_event', 'is_active', 'created_at', 'updated_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Count total matching rows.
		$count_params = $params;
		$total        = (int) $this->db->getVar(
			"SELECT COUNT(*) FROM {$table} {$where}",
			...$count_params
		);

		// Fetch paginated results.
		$params[] = $args['per_page'];
		$params[] = $offset;

		$rows = $this->db->getResults(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			...$params
		);

		return [
			'items' => WhatsAppTemplate::fromRows( $rows ),
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $args['per_page'] ) ),
		];
	}
}
