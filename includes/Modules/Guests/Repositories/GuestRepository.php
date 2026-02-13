<?php

namespace Nozule\Modules\Guests\Repositories;

use Nozule\Core\BaseRepository;
use Nozule\Modules\Guests\Models\Guest;

/**
 * Repository for guest database operations.
 */
class GuestRepository extends BaseRepository {

    protected string $table = 'guests';
    protected string $model = Guest::class;

    /**
     * Find a guest by email address.
     */
    public function findByEmail( string $email ): ?Guest {
        $table = $this->tableName();
        $row   = $this->db->getRow(
            "SELECT * FROM {$table} WHERE email = %s LIMIT 1",
            $email
        );

        return $row ? Guest::fromRow( $row ) : null;
    }

    /**
     * Find a guest by phone number.
     */
    public function findByPhone( string $phone ): ?Guest {
        $table = $this->tableName();
        $row   = $this->db->getRow(
            "SELECT * FROM {$table} WHERE phone = %s OR phone_alt = %s LIMIT 1",
            $phone,
            $phone
        );

        return $row ? Guest::fromRow( $row ) : null;
    }

    /**
     * Search guests by full-text matching across name, email, and phone fields.
     *
     * @param string $query    Search query string.
     * @param int    $per_page Number of results per page.
     * @param int    $page     Page number (1-based).
     * @return array{ guests: Guest[], total: int, pages: int }
     */
    public function search( string $query, int $per_page = 20, int $page = 1 ): array {
        $table  = $this->tableName();
        $like   = '%' . $this->db->wpdb()->esc_like( $query ) . '%';
        $offset = ( $page - 1 ) * $per_page;

        $where = "WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s";

        $total = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} {$where}",
            $like,
            $like,
            $like,
            $like
        );

        $rows = $this->db->getResults(
            "SELECT * FROM {$table} {$where} ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d",
            $like,
            $like,
            $like,
            $like,
            $per_page,
            $offset
        );

        return [
            'guests' => Guest::fromRows( $rows ),
            'total'  => $total,
            'pages'  => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Get guests with the highest booking count.
     *
     * @param int $limit Number of top guests to retrieve.
     * @return Guest[]
     */
    public function getTopGuests( int $limit = 10 ): array {
        $table = $this->tableName();
        $rows  = $this->db->getResults(
            "SELECT * FROM {$table} WHERE total_bookings > 0 ORDER BY total_bookings DESC, total_spent DESC LIMIT %d",
            $limit
        );

        return Guest::fromRows( $rows );
    }

    /**
     * Get the most recently created guest profiles.
     *
     * @param int $limit Number of recent guests to retrieve.
     * @return Guest[]
     */
    public function getRecentGuests( int $limit = 10 ): array {
        $table = $this->tableName();
        $rows  = $this->db->getResults(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        );

        return Guest::fromRows( $rows );
    }

    /**
     * Get guests filtered by nationality.
     *
     * @param string $nationality Nationality code or name.
     * @param int    $per_page    Number of results per page.
     * @param int    $page        Page number (1-based).
     * @return array{ guests: Guest[], total: int, pages: int }
     */
    public function getByNationality( string $nationality, int $per_page = 20, int $page = 1 ): array {
        $table  = $this->tableName();
        $offset = ( $page - 1 ) * $per_page;

        $total = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE nationality = %s",
            $nationality
        );

        $rows = $this->db->getResults(
            "SELECT * FROM {$table} WHERE nationality = %s ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d",
            $nationality,
            $per_page,
            $offset
        );

        return [
            'guests' => Guest::fromRows( $rows ),
            'total'  => $total,
            'pages'  => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Create a new guest record.
     *
     * @return Guest|false
     */
    public function create( array $data ): Guest|false {
        $data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
        $data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

        // Encode tags as JSON if provided as an array.
        if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
            $data['tags'] = wp_json_encode( $data['tags'] );
        }

        // Set default counters.
        $data['total_bookings'] = $data['total_bookings'] ?? 0;
        $data['total_spent']    = $data['total_spent'] ?? 0;
        $data['total_nights']   = $data['total_nights'] ?? 0;

        $id = $this->db->insert( $this->table, $data );

        if ( $id === false ) {
            return false;
        }

        return $this->find( $id );
    }

    /**
     * Update an existing guest record.
     */
    public function update( int $id, array $data ): bool {
        $data['updated_at'] = current_time( 'mysql' );

        // Encode tags as JSON if provided as an array.
        if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
            $data['tags'] = wp_json_encode( $data['tags'] );
        }

        return parent::update( $id, $data );
    }

    /**
     * Increment the guest's booking count and update total spent.
     *
     * @param int   $guest_id     Guest ID.
     * @param float $amount_spent Amount spent on the booking.
     */
    public function incrementBookingCount( int $guest_id, float $amount_spent = 0 ): bool {
        $table = $this->tableName();

        $result = $this->db->query(
            "UPDATE {$table}
             SET total_bookings = total_bookings + 1,
                 total_spent    = total_spent + %f,
                 updated_at     = %s
             WHERE id = %d",
            $amount_spent,
            current_time( 'mysql' ),
            $guest_id
        );

        return $result !== false;
    }

    /**
     * Update guest stats after checkout (total nights, last stay date).
     *
     * @param int    $guest_id   Guest ID.
     * @param int    $nights     Number of nights stayed.
     * @param string $checkout   Checkout date (Y-m-d format).
     */
    public function updateAfterCheckout( int $guest_id, int $nights, string $checkout ): bool {
        $table = $this->tableName();

        $result = $this->db->query(
            "UPDATE {$table}
             SET total_nights = total_nights + %d,
                 last_stay    = %s,
                 updated_at   = %s
             WHERE id = %d",
            $nights,
            $checkout,
            current_time( 'mysql' ),
            $guest_id
        );

        return $result !== false;
    }

    /**
     * List guests with pagination, optional search, and sorting.
     *
     * @param array $args {
     *     Optional. Arguments for listing guests.
     *
     *     @type string $search   Search query for filtering.
     *     @type string $orderby  Column to order by. Default 'created_at'.
     *     @type string $order    Sort direction (ASC|DESC). Default 'DESC'.
     *     @type int    $per_page Results per page. Default 20.
     *     @type int    $page     Page number (1-based). Default 1.
     * }
     * @return array{ guests: Guest[], total: int, pages: int }
     */
    public function list( array $args = [] ): array {
        $defaults = [
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
            'per_page' => 20,
            'page'     => 1,
        ];

        $args     = wp_parse_args( $args, $defaults );
        $table    = $this->tableName();
        $offset   = ( $args['page'] - 1 ) * $args['per_page'];
        $where    = '';
        $params   = [];

        // Build search clause.
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $this->db->wpdb()->esc_like( $args['search'] ) . '%';
            $where    = 'WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Sanitize ordering.
        $allowed_columns = [
            'id', 'first_name', 'last_name', 'email', 'nationality',
            'total_bookings', 'total_spent', 'last_stay', 'created_at',
        ];
        $orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Count total.
        $count_params = $params;
        $total        = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} {$where}",
            ...$count_params
        );

        // Fetch results.
        $params[] = $args['per_page'];
        $params[] = $offset;

        $rows = $this->db->getResults(
            "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            ...$params
        );

        return [
            'guests' => Guest::fromRows( $rows ),
            'total'  => $total,
            'pages'  => (int) ceil( $total / max( 1, $args['per_page'] ) ),
        ];
    }
}
