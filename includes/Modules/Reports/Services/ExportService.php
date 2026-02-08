<?php

namespace Venezia\Modules\Reports\Services;

/**
 * Service for exporting report data to CSV and JSON formats.
 *
 * CSV exports include a UTF-8 BOM for proper display in Microsoft Excel
 * and other spreadsheet applications that require it for character encoding.
 */
class ExportService {

    /**
     * UTF-8 BOM (Byte Order Mark) for Excel compatibility.
     */
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Column header definitions keyed by report type.
     *
     * @var array<string, array<string, string>>
     */
    private const HEADERS = [
        'occupancy' => [
            'date'          => 'Date',
            'total_rooms'   => 'Total Rooms',
            'booked_rooms'  => 'Booked Rooms',
            'occupancy_pct' => 'Occupancy %',
        ],
        'revenue' => [
            'period'            => 'Period',
            'booking_count'     => 'Bookings',
            'room_nights'       => 'Room Nights',
            'subtotal'          => 'Subtotal',
            'taxes'             => 'Taxes',
            'fees'              => 'Fees',
            'discount'          => 'Discount',
            'total_revenue'     => 'Total Revenue',
            'amount_collected'  => 'Collected',
            'avg_booking_value' => 'Avg Booking Value',
            'adr'               => 'ADR',
        ],
        'sources' => [
            'source'            => 'Source',
            'booking_count'     => 'Bookings',
            'room_nights'       => 'Room Nights',
            'total_revenue'     => 'Total Revenue',
            'amount_collected'  => 'Collected',
            'avg_booking_value' => 'Avg Booking Value',
            'avg_stay_length'   => 'Avg Stay Length',
            'revenue_pct'       => 'Revenue %',
            'count_pct'         => 'Booking %',
        ],
        'guests_nationalities' => [
            'nationality'   => 'Nationality',
            'guest_count'   => 'Guests',
            'booking_count' => 'Bookings',
            'total_revenue' => 'Total Revenue',
        ],
        'guests_repeat' => [
            'name'               => 'Guest Name',
            'email'              => 'Email',
            'nationality'        => 'Nationality',
            'bookings_in_period' => 'Bookings (Period)',
            'revenue_in_period'  => 'Revenue (Period)',
            'nights_in_period'   => 'Nights (Period)',
            'lifetime_bookings'  => 'Lifetime Bookings',
            'lifetime_spent'     => 'Lifetime Spent',
        ],
        'guests_top_spenders' => [
            'name'          => 'Guest Name',
            'email'         => 'Email',
            'booking_count' => 'Bookings',
            'total_revenue' => 'Total Revenue',
            'total_nights'  => 'Total Nights',
        ],
        'forecast' => [
            'date'             => 'Date',
            'total_rooms'      => 'Total Rooms',
            'booked_rooms'     => 'Booked Rooms',
            'occupancy_pct'    => 'Occupancy %',
            'booking_count'    => 'Bookings',
            'expected_revenue' => 'Expected Revenue',
            'room_nights'      => 'Room Nights',
        ],
        'cancellations' => [
            'date'         => 'Date',
            'cancel_count' => 'Cancellations',
            'lost_revenue' => 'Lost Revenue',
        ],
        'cancellations_by_reason' => [
            'reason'       => 'Reason',
            'cancel_count' => 'Cancellations',
            'lost_revenue' => 'Lost Revenue',
        ],
        'cancellations_by_source' => [
            'source'       => 'Source',
            'cancel_count' => 'Cancellations',
            'lost_revenue' => 'Lost Revenue',
        ],
    ];

    /**
     * Generate a CSV file from report data and send it as a download.
     *
     * Opens php://output as a stream and writes CSV rows directly,
     * prefixed with a UTF-8 BOM for Excel compatibility.
     *
     * @param array  $data     Array of associative arrays (rows).
     * @param string $filename Download filename (without extension).
     * @param string $reportType Report type key for header lookup.
     */
    public function exportCSV( array $data, string $filename, string $reportType = '' ): void {
        $filename = sanitize_file_name( $filename ) . '.csv';

        // Set download headers.
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        if ( $output === false ) {
            return;
        }

        // Write UTF-8 BOM for Excel compatibility.
        fwrite( $output, self::UTF8_BOM );

        // Determine headers.
        $headers = $this->getExportHeaders( $reportType );

        if ( ! empty( $headers ) ) {
            // Use defined headers.
            fputcsv( $output, array_values( $headers ) );

            foreach ( $data as $row ) {
                $csvRow = [];
                foreach ( array_keys( $headers ) as $key ) {
                    $csvRow[] = $row[ $key ] ?? '';
                }
                fputcsv( $output, $csvRow );
            }
        } elseif ( ! empty( $data ) ) {
            // Fallback: use array keys from first row.
            $keys = array_keys( $data[0] );
            fputcsv( $output, $keys );

            foreach ( $data as $row ) {
                $csvRow = [];
                foreach ( $keys as $key ) {
                    $csvRow[] = $row[ $key ] ?? '';
                }
                fputcsv( $output, $csvRow );
            }
        }

        fclose( $output );
    }

    /**
     * Generate a JSON file from report data and send it as a download.
     *
     * @param mixed  $data     The data to encode as JSON.
     * @param string $filename Download filename (without extension).
     */
    public function exportJSON( mixed $data, string $filename ): void {
        $filename = sanitize_file_name( $filename ) . '.json';

        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Get the column headers for a given report type.
     *
     * @param string $reportType The report type key.
     * @return array<string, string> Associative array of column_key => display_label.
     */
    public function getExportHeaders( string $reportType ): array {
        return self::HEADERS[ $reportType ] ?? [];
    }

    /**
     * Get all available report type keys.
     *
     * @return string[]
     */
    public function getAvailableReportTypes(): array {
        return array_keys( self::HEADERS );
    }
}
