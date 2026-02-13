<?php

namespace Nozule\Modules\Reports\Services;

use Nozule\Core\Database;
use Nozule\Core\CacheManager;

/**
 * Service for generating hotel operational reports.
 *
 * All report methods use raw SQL queries via the Database wrapper
 * for optimal performance on large datasets. Results are cached
 * with short TTLs to balance freshness and speed.
 */
class ReportService {

    private Database $db;
    private CacheManager $cache;

    /**
     * Cache TTL for report data (5 minutes).
     */
    private const CACHE_TTL = 300;

    public function __construct( Database $db, CacheManager $cache ) {
        $this->db    = $db;
        $this->cache = $cache;
    }

    /**
     * Get daily occupancy report for a date range.
     *
     * Returns one row per date with total rooms, booked rooms,
     * occupancy percentage, and optional room-type breakdown.
     *
     * @param string   $startDate   Start date (Y-m-d).
     * @param string   $endDate     End date (Y-m-d).
     * @param int|null $roomTypeId  Optional room type filter.
     * @return array<int, array{
     *     date: string,
     *     total_rooms: int,
     *     booked_rooms: int,
     *     occupancy_pct: float,
     *     room_type_id: int|null
     * }>
     */
    public function getOccupancyReport( string $startDate, string $endDate, ?int $roomTypeId = null ): array {
        $cacheKey = 'report_occupancy_' . md5( $startDate . $endDate . ( $roomTypeId ?? 'all' ) );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $inventory = $this->db->table( 'room_inventory' );
        $types     = $this->db->table( 'room_types' );

        if ( $roomTypeId ) {
            $rows = $this->db->getResults(
                "SELECT
                    ri.date,
                    ri.room_type_id,
                    rt.name AS room_type_name,
                    ri.total_rooms,
                    ri.booked_rooms,
                    CASE
                        WHEN ri.total_rooms > 0
                        THEN ROUND( ( ri.booked_rooms / ri.total_rooms ) * 100, 2 )
                        ELSE 0
                    END AS occupancy_pct
                FROM {$inventory} ri
                INNER JOIN {$types} rt ON rt.id = ri.room_type_id
                WHERE ri.date >= %s
                  AND ri.date <= %s
                  AND ri.room_type_id = %d
                ORDER BY ri.date ASC",
                $startDate,
                $endDate,
                $roomTypeId
            );
        } else {
            $rows = $this->db->getResults(
                "SELECT
                    ri.date,
                    SUM( ri.total_rooms )  AS total_rooms,
                    SUM( ri.booked_rooms ) AS booked_rooms,
                    CASE
                        WHEN SUM( ri.total_rooms ) > 0
                        THEN ROUND( ( SUM( ri.booked_rooms ) / SUM( ri.total_rooms ) ) * 100, 2 )
                        ELSE 0
                    END AS occupancy_pct
                FROM {$inventory} ri
                WHERE ri.date >= %s
                  AND ri.date <= %s
                GROUP BY ri.date
                ORDER BY ri.date ASC",
                $startDate,
                $endDate
            );
        }

        $result = array_map( function ( object $row ) use ( $roomTypeId ) {
            $item = [
                'date'          => $row->date,
                'total_rooms'   => (int) $row->total_rooms,
                'booked_rooms'  => (int) $row->booked_rooms,
                'occupancy_pct' => (float) $row->occupancy_pct,
            ];

            if ( $roomTypeId ) {
                $item['room_type_id']   = (int) $row->room_type_id;
                $item['room_type_name'] = $row->room_type_name;
            }

            return $item;
        }, $rows );

        // Append summary row.
        $totalRoomNights    = array_sum( array_column( $result, 'total_rooms' ) );
        $occupiedRoomNights = array_sum( array_column( $result, 'booked_rooms' ) );
        $avgOccupancy       = $totalRoomNights > 0
            ? round( ( $occupiedRoomNights / $totalRoomNights ) * 100, 2 )
            : 0.0;

        $data = [
            'data'    => $result,
            'summary' => [
                'total_room_nights'    => $totalRoomNights,
                'occupied_room_nights' => $occupiedRoomNights,
                'average_occupancy'    => $avgOccupancy,
                'days_count'           => count( $result ),
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get revenue report with breakdown by period.
     *
     * Supports daily, weekly, and monthly grouping. Calculates ADR
     * (Average Daily Rate) and RevPAR for each period.
     *
     * @param string $startDate Start date (Y-m-d).
     * @param string $endDate   End date (Y-m-d).
     * @param string $groupBy   Grouping: 'daily', 'weekly', or 'monthly'.
     * @return array{data: array, summary: array}
     */
    public function getRevenueReport( string $startDate, string $endDate, string $groupBy = 'daily' ): array {
        $cacheKey = 'report_revenue_' . md5( $startDate . $endDate . $groupBy );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings  = $this->db->table( 'bookings' );
        $inventory = $this->db->table( 'room_inventory' );

        $dateExpr = match ( $groupBy ) {
            'weekly'  => "DATE_FORMAT( b.check_in, '%x-W%v' )",
            'monthly' => "DATE_FORMAT( b.check_in, '%Y-%m' )",
            default   => 'b.check_in',
        };

        $rows = $this->db->getResults(
            "SELECT
                {$dateExpr} AS period,
                COUNT( b.id ) AS booking_count,
                SUM( b.nights ) AS room_nights,
                SUM( b.subtotal ) AS subtotal,
                SUM( b.taxes ) AS taxes,
                SUM( b.fees ) AS fees,
                SUM( b.discount ) AS discount,
                SUM( b.total_price ) AS total_revenue,
                SUM( b.amount_paid ) AS amount_collected,
                AVG( b.total_price ) AS avg_booking_value
            FROM {$bookings} b
            WHERE b.check_in >= %s
              AND b.check_in <= %s
              AND b.status NOT IN ('cancelled', 'no_show')
            GROUP BY period
            ORDER BY period ASC",
            $startDate,
            $endDate
        );

        // Get total available room-nights for RevPAR calculation.
        $totalAvailable = (int) $this->db->getVar(
            "SELECT SUM( total_rooms ) FROM {$inventory}
            WHERE date >= %s AND date <= %s",
            $startDate,
            $endDate
        );

        $result = array_map( function ( object $row ) {
            $roomNights = (int) $row->room_nights;
            $revenue    = (float) $row->total_revenue;
            $adr        = $roomNights > 0 ? round( $revenue / $roomNights, 2 ) : 0.0;

            return [
                'period'            => $row->period,
                'booking_count'     => (int) $row->booking_count,
                'room_nights'       => $roomNights,
                'subtotal'          => (float) $row->subtotal,
                'taxes'             => (float) $row->taxes,
                'fees'              => (float) $row->fees,
                'discount'          => (float) $row->discount,
                'total_revenue'     => $revenue,
                'amount_collected'  => (float) $row->amount_collected,
                'avg_booking_value' => round( (float) $row->avg_booking_value, 2 ),
                'adr'               => $adr,
            ];
        }, $rows );

        // Totals.
        $totalRevenue    = array_sum( array_column( $result, 'total_revenue' ) );
        $totalRoomNights = array_sum( array_column( $result, 'room_nights' ) );
        $overallAdr      = $totalRoomNights > 0 ? round( $totalRevenue / $totalRoomNights, 2 ) : 0.0;
        $revpar          = $totalAvailable > 0 ? round( $totalRevenue / $totalAvailable, 2 ) : 0.0;

        $data = [
            'data'    => $result,
            'summary' => [
                'total_revenue'           => $totalRevenue,
                'total_collected'         => array_sum( array_column( $result, 'amount_collected' ) ),
                'total_bookings'          => array_sum( array_column( $result, 'booking_count' ) ),
                'total_room_nights'       => $totalRoomNights,
                'total_available_nights'  => $totalAvailable,
                'adr'                     => $overallAdr,
                'revpar'                  => $revpar,
                'occupancy'               => $totalAvailable > 0
                    ? round( ( $totalRoomNights / $totalAvailable ) * 100, 2 )
                    : 0.0,
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get booking source breakdown report.
     *
     * Aggregates bookings by source (direct, booking.com, expedia, etc.)
     * showing count, revenue, and percentage of total.
     *
     * @param string $startDate Start date (Y-m-d).
     * @param string $endDate   End date (Y-m-d).
     * @return array{data: array, summary: array}
     */
    public function getSourceReport( string $startDate, string $endDate ): array {
        $cacheKey = 'report_source_' . md5( $startDate . $endDate );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings = $this->db->table( 'bookings' );

        $rows = $this->db->getResults(
            "SELECT
                b.source,
                COUNT( b.id ) AS booking_count,
                SUM( b.nights ) AS room_nights,
                SUM( b.total_price ) AS total_revenue,
                SUM( b.amount_paid ) AS amount_collected,
                AVG( b.total_price ) AS avg_booking_value,
                AVG( b.nights ) AS avg_stay_length
            FROM {$bookings} b
            WHERE b.created_at >= %s
              AND b.created_at <= %s
              AND b.status NOT IN ('cancelled')
            GROUP BY b.source
            ORDER BY total_revenue DESC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $grandTotal  = 0.0;
        $grandCount  = 0;

        $result = array_map( function ( object $row ) use ( &$grandTotal, &$grandCount ) {
            $revenue = (float) $row->total_revenue;
            $count   = (int) $row->booking_count;
            $grandTotal += $revenue;
            $grandCount += $count;

            return [
                'source'            => $row->source,
                'booking_count'     => $count,
                'room_nights'       => (int) $row->room_nights,
                'total_revenue'     => $revenue,
                'amount_collected'  => (float) $row->amount_collected,
                'avg_booking_value' => round( (float) $row->avg_booking_value, 2 ),
                'avg_stay_length'   => round( (float) $row->avg_stay_length, 1 ),
            ];
        }, $rows );

        // Calculate percentages.
        $result = array_map( function ( array $item ) use ( $grandTotal, $grandCount ) {
            $item['revenue_pct'] = $grandTotal > 0
                ? round( ( $item['total_revenue'] / $grandTotal ) * 100, 2 )
                : 0.0;
            $item['count_pct'] = $grandCount > 0
                ? round( ( $item['booking_count'] / $grandCount ) * 100, 2 )
                : 0.0;
            return $item;
        }, $result );

        $data = [
            'data'    => $result,
            'summary' => [
                'total_revenue'  => $grandTotal,
                'total_bookings' => $grandCount,
                'source_count'   => count( $result ),
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get guest statistics report.
     *
     * Includes nationality breakdown, repeat guest analysis, and
     * top spenders for the given period.
     *
     * @param string $startDate Start date (Y-m-d).
     * @param string $endDate   End date (Y-m-d).
     * @return array{nationalities: array, repeat_guests: array, top_spenders: array, summary: array}
     */
    public function getGuestReport( string $startDate, string $endDate ): array {
        $cacheKey = 'report_guest_' . md5( $startDate . $endDate );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings = $this->db->table( 'bookings' );
        $guests   = $this->db->table( 'guests' );

        // Nationality breakdown.
        $nationalities = $this->db->getResults(
            "SELECT
                COALESCE( g.nationality, 'Unknown' ) AS nationality,
                COUNT( DISTINCT g.id ) AS guest_count,
                COUNT( b.id ) AS booking_count,
                SUM( b.total_price ) AS total_revenue
            FROM {$bookings} b
            INNER JOIN {$guests} g ON g.id = b.guest_id
            WHERE b.check_in >= %s
              AND b.check_in <= %s
              AND b.status NOT IN ('cancelled')
            GROUP BY g.nationality
            ORDER BY guest_count DESC",
            $startDate,
            $endDate
        );

        $nationalityData = array_map( function ( object $row ) {
            return [
                'nationality'   => $row->nationality,
                'guest_count'   => (int) $row->guest_count,
                'booking_count' => (int) $row->booking_count,
                'total_revenue' => (float) $row->total_revenue,
            ];
        }, $nationalities );

        // Repeat guests (more than 1 booking in the period).
        $repeatGuests = $this->db->getResults(
            "SELECT
                g.id,
                g.first_name,
                g.last_name,
                g.email,
                g.nationality,
                COUNT( b.id ) AS bookings_in_period,
                SUM( b.total_price ) AS revenue_in_period,
                SUM( b.nights ) AS nights_in_period,
                g.total_bookings AS lifetime_bookings,
                g.total_spent AS lifetime_spent
            FROM {$bookings} b
            INNER JOIN {$guests} g ON g.id = b.guest_id
            WHERE b.check_in >= %s
              AND b.check_in <= %s
              AND b.status NOT IN ('cancelled')
            GROUP BY g.id
            HAVING bookings_in_period > 1
            ORDER BY bookings_in_period DESC
            LIMIT 50",
            $startDate,
            $endDate
        );

        $repeatData = array_map( function ( object $row ) {
            return [
                'guest_id'          => (int) $row->id,
                'name'              => trim( $row->first_name . ' ' . $row->last_name ),
                'email'             => $row->email,
                'nationality'       => $row->nationality,
                'bookings_in_period' => (int) $row->bookings_in_period,
                'revenue_in_period' => (float) $row->revenue_in_period,
                'nights_in_period'  => (int) $row->nights_in_period,
                'lifetime_bookings' => (int) $row->lifetime_bookings,
                'lifetime_spent'    => (float) $row->lifetime_spent,
            ];
        }, $repeatGuests );

        // Top spenders.
        $topSpenders = $this->db->getResults(
            "SELECT
                g.id,
                g.first_name,
                g.last_name,
                g.email,
                COUNT( b.id ) AS booking_count,
                SUM( b.total_price ) AS total_revenue,
                SUM( b.nights ) AS total_nights
            FROM {$bookings} b
            INNER JOIN {$guests} g ON g.id = b.guest_id
            WHERE b.check_in >= %s
              AND b.check_in <= %s
              AND b.status NOT IN ('cancelled')
            GROUP BY g.id
            ORDER BY total_revenue DESC
            LIMIT 20",
            $startDate,
            $endDate
        );

        $topSpenderData = array_map( function ( object $row ) {
            return [
                'guest_id'      => (int) $row->id,
                'name'          => trim( $row->first_name . ' ' . $row->last_name ),
                'email'         => $row->email,
                'booking_count' => (int) $row->booking_count,
                'total_revenue' => (float) $row->total_revenue,
                'total_nights'  => (int) $row->total_nights,
            ];
        }, $topSpenders );

        // Summary stats.
        $summaryRow = $this->db->getRow(
            "SELECT
                COUNT( DISTINCT b.guest_id ) AS unique_guests,
                COUNT( b.id ) AS total_bookings,
                SUM( b.total_price ) AS total_revenue
            FROM {$bookings} b
            WHERE b.check_in >= %s
              AND b.check_in <= %s
              AND b.status NOT IN ('cancelled')",
            $startDate,
            $endDate
        );

        $uniqueGuests  = $summaryRow ? (int) $summaryRow->unique_guests : 0;
        $totalBookings = $summaryRow ? (int) $summaryRow->total_bookings : 0;
        $repeatCount   = count( $repeatData );

        $data = [
            'nationalities' => $nationalityData,
            'repeat_guests' => $repeatData,
            'top_spenders'  => $topSpenderData,
            'summary'       => [
                'unique_guests'    => $uniqueGuests,
                'total_bookings'   => $totalBookings,
                'repeat_guests'    => $repeatCount,
                'repeat_rate'      => $uniqueGuests > 0
                    ? round( ( $repeatCount / $uniqueGuests ) * 100, 2 )
                    : 0.0,
                'total_revenue'    => $summaryRow ? (float) $summaryRow->total_revenue : 0.0,
                'nationality_count' => count( $nationalityData ),
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get forward-looking forecast report.
     *
     * Calculates projected occupancy and revenue based on existing
     * confirmed/pending bookings for future dates.
     *
     * @param string $startDate Start date (Y-m-d).
     * @param string $endDate   End date (Y-m-d).
     * @return array{data: array, summary: array}
     */
    public function getForecastReport( string $startDate, string $endDate ): array {
        $cacheKey = 'report_forecast_' . md5( $startDate . $endDate );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings  = $this->db->table( 'bookings' );
        $inventory = $this->db->table( 'room_inventory' );

        // Get inventory per date.
        $inventoryRows = $this->db->getResults(
            "SELECT
                date,
                SUM( total_rooms ) AS total_rooms,
                SUM( booked_rooms ) AS booked_rooms
            FROM {$inventory}
            WHERE date >= %s AND date <= %s
            GROUP BY date
            ORDER BY date ASC",
            $startDate,
            $endDate
        );

        $inventoryByDate = [];
        foreach ( $inventoryRows as $row ) {
            $inventoryByDate[ $row->date ] = [
                'total_rooms'  => (int) $row->total_rooms,
                'booked_rooms' => (int) $row->booked_rooms,
            ];
        }

        // Get expected revenue per check-in date from confirmed/pending bookings.
        $revenueRows = $this->db->getResults(
            "SELECT
                check_in AS date,
                COUNT( id ) AS booking_count,
                SUM( total_price ) AS expected_revenue,
                SUM( nights ) AS room_nights
            FROM {$bookings}
            WHERE check_in >= %s
              AND check_in <= %s
              AND status IN ('confirmed', 'pending')
            GROUP BY check_in
            ORDER BY check_in ASC",
            $startDate,
            $endDate
        );

        $revenueByDate = [];
        foreach ( $revenueRows as $row ) {
            $revenueByDate[ $row->date ] = [
                'booking_count'    => (int) $row->booking_count,
                'expected_revenue' => (float) $row->expected_revenue,
                'room_nights'      => (int) $row->room_nights,
            ];
        }

        // Build day-by-day forecast.
        $result = [];
        $current = new \DateTimeImmutable( $startDate );
        $end     = new \DateTimeImmutable( $endDate );

        while ( $current <= $end ) {
            $dateStr     = $current->format( 'Y-m-d' );
            $inv         = $inventoryByDate[ $dateStr ] ?? [ 'total_rooms' => 0, 'booked_rooms' => 0 ];
            $rev         = $revenueByDate[ $dateStr ] ?? [ 'booking_count' => 0, 'expected_revenue' => 0.0, 'room_nights' => 0 ];
            $totalRooms  = $inv['total_rooms'];
            $bookedRooms = $inv['booked_rooms'];

            $result[] = [
                'date'              => $dateStr,
                'total_rooms'       => $totalRooms,
                'booked_rooms'      => $bookedRooms,
                'occupancy_pct'     => $totalRooms > 0
                    ? round( ( $bookedRooms / $totalRooms ) * 100, 2 )
                    : 0.0,
                'booking_count'     => $rev['booking_count'],
                'expected_revenue'  => $rev['expected_revenue'],
                'room_nights'       => $rev['room_nights'],
            ];

            $current = $current->modify( '+1 day' );
        }

        $totalRoomNights    = array_sum( array_column( $result, 'total_rooms' ) );
        $totalBooked        = array_sum( array_column( $result, 'booked_rooms' ) );
        $totalExpectedRev   = array_sum( array_column( $result, 'expected_revenue' ) );

        $data = [
            'data'    => $result,
            'summary' => [
                'total_available_nights' => $totalRoomNights,
                'total_booked_nights'    => $totalBooked,
                'projected_occupancy'    => $totalRoomNights > 0
                    ? round( ( $totalBooked / $totalRoomNights ) * 100, 2 )
                    : 0.0,
                'projected_revenue'      => $totalExpectedRev,
                'projected_adr'          => $totalBooked > 0
                    ? round( $totalExpectedRev / $totalBooked, 2 )
                    : 0.0,
                'projected_revpar'       => $totalRoomNights > 0
                    ? round( $totalExpectedRev / $totalRoomNights, 2 )
                    : 0.0,
                'days_count'             => count( $result ),
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get cancellation analysis report.
     *
     * Breaks down cancelled bookings by reason, source, room type,
     * and calculates the financial impact.
     *
     * @param string $startDate Start date (Y-m-d).
     * @param string $endDate   End date (Y-m-d).
     * @return array{by_reason: array, by_source: array, by_room_type: array, timeline: array, summary: array}
     */
    public function getCancellationReport( string $startDate, string $endDate ): array {
        $cacheKey = 'report_cancel_' . md5( $startDate . $endDate );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings = $this->db->table( 'bookings' );
        $types    = $this->db->table( 'room_types' );

        // By cancellation reason.
        $byReason = $this->db->getResults(
            "SELECT
                COALESCE( cancel_reason, 'Not specified' ) AS reason,
                COUNT( id ) AS cancel_count,
                SUM( total_price ) AS lost_revenue
            FROM {$bookings}
            WHERE status = 'cancelled'
              AND cancelled_at >= %s
              AND cancelled_at <= %s
            GROUP BY cancel_reason
            ORDER BY cancel_count DESC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $reasonData = array_map( function ( object $row ) {
            return [
                'reason'       => $row->reason,
                'cancel_count' => (int) $row->cancel_count,
                'lost_revenue' => (float) $row->lost_revenue,
            ];
        }, $byReason );

        // By source.
        $bySource = $this->db->getResults(
            "SELECT
                source,
                COUNT( id ) AS cancel_count,
                SUM( total_price ) AS lost_revenue
            FROM {$bookings}
            WHERE status = 'cancelled'
              AND cancelled_at >= %s
              AND cancelled_at <= %s
            GROUP BY source
            ORDER BY cancel_count DESC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $sourceData = array_map( function ( object $row ) {
            return [
                'source'       => $row->source,
                'cancel_count' => (int) $row->cancel_count,
                'lost_revenue' => (float) $row->lost_revenue,
            ];
        }, $bySource );

        // By room type.
        $byRoomType = $this->db->getResults(
            "SELECT
                rt.name AS room_type_name,
                b.room_type_id,
                COUNT( b.id ) AS cancel_count,
                SUM( b.total_price ) AS lost_revenue
            FROM {$bookings} b
            INNER JOIN {$types} rt ON rt.id = b.room_type_id
            WHERE b.status = 'cancelled'
              AND b.cancelled_at >= %s
              AND b.cancelled_at <= %s
            GROUP BY b.room_type_id
            ORDER BY cancel_count DESC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $roomTypeData = array_map( function ( object $row ) {
            return [
                'room_type_id'   => (int) $row->room_type_id,
                'room_type_name' => $row->room_type_name,
                'cancel_count'   => (int) $row->cancel_count,
                'lost_revenue'   => (float) $row->lost_revenue,
            ];
        }, $byRoomType );

        // Cancellation timeline (daily count).
        $timeline = $this->db->getResults(
            "SELECT
                DATE( cancelled_at ) AS cancel_date,
                COUNT( id ) AS cancel_count,
                SUM( total_price ) AS lost_revenue
            FROM {$bookings}
            WHERE status = 'cancelled'
              AND cancelled_at >= %s
              AND cancelled_at <= %s
            GROUP BY cancel_date
            ORDER BY cancel_date ASC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $timelineData = array_map( function ( object $row ) {
            return [
                'date'         => $row->cancel_date,
                'cancel_count' => (int) $row->cancel_count,
                'lost_revenue' => (float) $row->lost_revenue,
            ];
        }, $timeline );

        // Summary.
        $summaryRow = $this->db->getRow(
            "SELECT
                COUNT( id ) AS total_cancellations,
                SUM( total_price ) AS total_lost_revenue,
                AVG( total_price ) AS avg_lost_value,
                AVG( DATEDIFF( check_in, DATE( cancelled_at ) ) ) AS avg_lead_time_days
            FROM {$bookings}
            WHERE status = 'cancelled'
              AND cancelled_at >= %s
              AND cancelled_at <= %s",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        // Total bookings in the same period to calculate cancellation rate.
        $totalBookings = (int) $this->db->getVar(
            "SELECT COUNT( id ) FROM {$bookings}
            WHERE created_at >= %s AND created_at <= %s",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        $totalCancellations = $summaryRow ? (int) $summaryRow->total_cancellations : 0;

        $data = [
            'by_reason'    => $reasonData,
            'by_source'    => $sourceData,
            'by_room_type' => $roomTypeData,
            'timeline'     => $timelineData,
            'summary'      => [
                'total_cancellations' => $totalCancellations,
                'total_lost_revenue'  => $summaryRow ? (float) $summaryRow->total_lost_revenue : 0.0,
                'avg_lost_value'      => $summaryRow ? round( (float) $summaryRow->avg_lost_value, 2 ) : 0.0,
                'avg_lead_time_days'  => $summaryRow ? round( (float) $summaryRow->avg_lead_time_days, 1 ) : 0.0,
                'cancellation_rate'   => $totalBookings > 0
                    ? round( ( $totalCancellations / $totalBookings ) * 100, 2 )
                    : 0.0,
                'total_bookings'      => $totalBookings,
            ],
        ];

        $this->cache->set( $cacheKey, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Get dashboard summary statistics for today.
     *
     * Returns at-a-glance metrics: arrivals, departures, in-house
     * guests, current occupancy, and today's revenue.
     *
     * @return array{
     *     today: string,
     *     arrivals: array,
     *     departures: array,
     *     in_house: array,
     *     occupancy: array,
     *     revenue: array
     * }
     */
    public function getDashboardStats(): array {
        $cacheKey = 'report_dashboard_' . wp_date( 'Y-m-d' );
        $cached   = $this->cache->get( $cacheKey );

        if ( $cached !== false ) {
            return $cached;
        }

        $bookings  = $this->db->table( 'bookings' );
        $inventory = $this->db->table( 'room_inventory' );
        $payments  = $this->db->table( 'payments' );
        $today     = wp_date( 'Y-m-d' );

        // Today's arrivals: bookings checking in today.
        $arrivals = $this->db->getRow(
            "SELECT
                COUNT( id ) AS total,
                SUM( CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END ) AS arrived,
                SUM( CASE WHEN status IN ('confirmed', 'pending') THEN 1 ELSE 0 END ) AS expected
            FROM {$bookings}
            WHERE check_in = %s
              AND status NOT IN ('cancelled', 'no_show')",
            $today
        );

        // Today's departures: bookings checking out today.
        $departures = $this->db->getRow(
            "SELECT
                COUNT( id ) AS total,
                SUM( CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END ) AS departed,
                SUM( CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END ) AS remaining
            FROM {$bookings}
            WHERE check_out = %s
              AND status NOT IN ('cancelled', 'no_show')",
            $today
        );

        // In-house guests: currently checked in.
        $inHouse = $this->db->getRow(
            "SELECT
                COUNT( id ) AS room_count,
                SUM( adults ) AS adults,
                SUM( children ) AS children,
                SUM( infants ) AS infants
            FROM {$bookings}
            WHERE status = 'checked_in'
              AND check_in <= %s
              AND check_out > %s",
            $today,
            $today
        );

        // Occupancy: from room inventory.
        $occupancy = $this->db->getRow(
            "SELECT
                SUM( total_rooms ) AS total_rooms,
                SUM( booked_rooms ) AS booked_rooms
            FROM {$inventory}
            WHERE date = %s",
            $today
        );

        $totalRooms  = $occupancy ? (int) $occupancy->total_rooms : 0;
        $bookedRooms = $occupancy ? (int) $occupancy->booked_rooms : 0;

        // Revenue: payments received today.
        $revenue = $this->db->getRow(
            "SELECT
                COUNT( id ) AS payment_count,
                SUM( amount ) AS total_collected
            FROM {$payments}
            WHERE status = 'completed'
              AND DATE( paid_at ) = %s",
            $today
        );

        // Revenue from bookings created today.
        $bookingRevenue = $this->db->getRow(
            "SELECT
                COUNT( id ) AS booking_count,
                SUM( total_price ) AS total_value
            FROM {$bookings}
            WHERE DATE( created_at ) = %s
              AND status NOT IN ('cancelled')",
            $today
        );

        $data = [
            'today'      => $today,
            'arrivals'   => [
                'total'    => $arrivals ? (int) $arrivals->total : 0,
                'arrived'  => $arrivals ? (int) $arrivals->arrived : 0,
                'expected' => $arrivals ? (int) $arrivals->expected : 0,
            ],
            'departures' => [
                'total'     => $departures ? (int) $departures->total : 0,
                'departed'  => $departures ? (int) $departures->departed : 0,
                'remaining' => $departures ? (int) $departures->remaining : 0,
            ],
            'in_house'   => [
                'room_count' => $inHouse ? (int) $inHouse->room_count : 0,
                'adults'     => $inHouse ? (int) $inHouse->adults : 0,
                'children'   => $inHouse ? (int) $inHouse->children : 0,
                'infants'    => $inHouse ? (int) $inHouse->infants : 0,
                'total_pax'  => $inHouse
                    ? (int) $inHouse->adults + (int) $inHouse->children + (int) $inHouse->infants
                    : 0,
            ],
            'occupancy'  => [
                'total_rooms'   => $totalRooms,
                'booked_rooms'  => $bookedRooms,
                'available'     => max( 0, $totalRooms - $bookedRooms ),
                'occupancy_pct' => $totalRooms > 0
                    ? round( ( $bookedRooms / $totalRooms ) * 100, 2 )
                    : 0.0,
            ],
            'revenue'    => [
                'payments_today'       => $revenue ? (float) $revenue->total_collected : 0.0,
                'payment_count'        => $revenue ? (int) $revenue->payment_count : 0,
                'new_bookings_count'   => $bookingRevenue ? (int) $bookingRevenue->booking_count : 0,
                'new_bookings_value'   => $bookingRevenue ? (float) $bookingRevenue->total_value : 0.0,
            ],
        ];

        // Short cache for dashboard (2 minutes).
        $this->cache->set( $cacheKey, $data, 120 );

        return $data;
    }
}
