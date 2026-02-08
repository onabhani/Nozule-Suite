<?php

namespace Venezia\Modules\Reports\Controllers;

use Venezia\Modules\Reports\Services\ReportService;
use Venezia\Modules\Reports\Services\ExportService;

/**
 * REST API controller for hotel reports.
 *
 * All endpoints require the 'vhm_admin' capability and are
 * registered under the venezia/v1/admin/reports namespace.
 */
class ReportController {

    private const NAMESPACE = 'venezia/v1';

    private ReportService $reportService;
    private ExportService $exportService;

    public function __construct( ReportService $reportService, ExportService $exportService ) {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    /**
     * Register all report REST routes.
     */
    public function registerRoutes(): void {
        register_rest_route( self::NAMESPACE, '/admin/reports/occupancy', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getOccupancyReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs( [
                'room_type_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => __( 'Filter by room type ID.', 'venezia-hotel' ),
                ],
            ] ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/revenue', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getRevenueReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs( [
                'group_by' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'daily',
                    'enum'              => [ 'daily', 'weekly', 'monthly' ],
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => __( 'Group results by period.', 'venezia-hotel' ),
                ],
            ] ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/sources', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getSourceReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/guests', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getGuestReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/forecast', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getForecastReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/cancellations', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getCancellationReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getDateRangeArgs(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/dashboard', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getDashboardStats' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/reports/export', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'exportReport' ],
            'permission_callback' => [ $this, 'checkAdminPermission' ],
            'args'                => $this->getExportArgs(),
        ] );
    }

    /**
     * Permission check: current user must have 'vhm_admin' capability.
     */
    public function checkAdminPermission(): bool {
        return current_user_can( 'vhm_admin' );
    }

    /**
     * GET /admin/reports/occupancy
     */
    public function getOccupancyReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate  = $request->get_param( 'start_date' );
        $endDate    = $request->get_param( 'end_date' );
        $roomTypeId = $request->get_param( 'room_type_id' );

        $data = $this->reportService->getOccupancyReport(
            $startDate,
            $endDate,
            $roomTypeId ? (int) $roomTypeId : null
        );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/revenue
     */
    public function getRevenueReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate = $request->get_param( 'start_date' );
        $endDate   = $request->get_param( 'end_date' );
        $groupBy   = $request->get_param( 'group_by' ) ?: 'daily';

        $data = $this->reportService->getRevenueReport( $startDate, $endDate, $groupBy );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/sources
     */
    public function getSourceReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate = $request->get_param( 'start_date' );
        $endDate   = $request->get_param( 'end_date' );

        $data = $this->reportService->getSourceReport( $startDate, $endDate );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/guests
     */
    public function getGuestReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate = $request->get_param( 'start_date' );
        $endDate   = $request->get_param( 'end_date' );

        $data = $this->reportService->getGuestReport( $startDate, $endDate );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/forecast
     */
    public function getForecastReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate = $request->get_param( 'start_date' );
        $endDate   = $request->get_param( 'end_date' );

        $data = $this->reportService->getForecastReport( $startDate, $endDate );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/cancellations
     */
    public function getCancellationReport( \WP_REST_Request $request ): \WP_REST_Response {
        $startDate = $request->get_param( 'start_date' );
        $endDate   = $request->get_param( 'end_date' );

        $data = $this->reportService->getCancellationReport( $startDate, $endDate );

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * GET /admin/reports/dashboard
     */
    public function getDashboardStats( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $this->reportService->getDashboardStats();

        return new \WP_REST_Response( $data, 200 );
    }

    /**
     * POST /admin/reports/export
     *
     * Generates and streams a file download (CSV or JSON).
     * Because this sends headers directly, it returns null and
     * exits after streaming to avoid WordPress appending output.
     */
    public function exportReport( \WP_REST_Request $request ): \WP_REST_Response|null {
        $reportType = $request->get_param( 'report_type' );
        $format     = $request->get_param( 'format' ) ?: 'csv';
        $startDate  = $request->get_param( 'start_date' );
        $endDate    = $request->get_param( 'end_date' );

        // Generate report data.
        $reportData = $this->generateReportData( $reportType, $startDate, $endDate, $request );

        if ( $reportData === null ) {
            return new \WP_REST_Response(
                [ 'message' => __( 'Invalid report type.', 'venezia-hotel' ) ],
                400
            );
        }

        $filename = 'venezia-' . $reportType . '-' . $startDate . '-to-' . $endDate;

        if ( $format === 'json' ) {
            $this->exportService->exportJSON( $reportData, $filename );
        } else {
            $exportKey  = $this->resolveExportKey( $reportType );
            $exportData = $this->extractExportableData( $reportData, $reportType );
            $this->exportService->exportCSV( $exportData, $filename, $exportKey );
        }

        exit; // Prevent WordPress from appending additional output.
    }

    /**
     * Generate report data based on report type.
     *
     * @return array|null Report data or null if invalid type.
     */
    private function generateReportData(
        string $reportType,
        string $startDate,
        string $endDate,
        \WP_REST_Request $request
    ): ?array {
        return match ( $reportType ) {
            'occupancy'     => $this->reportService->getOccupancyReport(
                $startDate,
                $endDate,
                $request->get_param( 'room_type_id' ) ? (int) $request->get_param( 'room_type_id' ) : null
            ),
            'revenue'       => $this->reportService->getRevenueReport(
                $startDate,
                $endDate,
                $request->get_param( 'group_by' ) ?: 'daily'
            ),
            'sources'       => $this->reportService->getSourceReport( $startDate, $endDate ),
            'guests'        => $this->reportService->getGuestReport( $startDate, $endDate ),
            'forecast'      => $this->reportService->getForecastReport( $startDate, $endDate ),
            'cancellations' => $this->reportService->getCancellationReport( $startDate, $endDate ),
            default         => null,
        };
    }

    /**
     * Resolve the export header key for a report type.
     */
    private function resolveExportKey( string $reportType ): string {
        return match ( $reportType ) {
            'cancellations' => 'cancellations',
            default         => $reportType,
        };
    }

    /**
     * Extract the data array suitable for CSV export from report data.
     *
     * Most reports nest the exportable rows under a 'data' key.
     * Guest reports have multiple sub-sections.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractExportableData( array $reportData, string $reportType ): array {
        return match ( $reportType ) {
            'guests'        => $reportData['nationalities'] ?? [],
            'cancellations' => $reportData['timeline'] ?? [],
            default         => $reportData['data'] ?? [],
        };
    }

    /**
     * Build the common date range argument definitions.
     *
     * @param array $extra Additional args to merge.
     * @return array
     */
    private function getDateRangeArgs( array $extra = [] ): array {
        return array_merge( [
            'start_date' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'date',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [ $this, 'validateDate' ],
                'description'       => __( 'Start date in Y-m-d format.', 'venezia-hotel' ),
            ],
            'end_date' => [
                'required'          => true,
                'type'              => 'string',
                'format'            => 'date',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [ $this, 'validateDate' ],
                'description'       => __( 'End date in Y-m-d format.', 'venezia-hotel' ),
            ],
        ], $extra );
    }

    /**
     * Build the export endpoint argument definitions.
     */
    private function getExportArgs(): array {
        return array_merge(
            $this->getDateRangeArgs(),
            [
                'report_type' => [
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => [ 'occupancy', 'revenue', 'sources', 'guests', 'forecast', 'cancellations' ],
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => __( 'The type of report to export.', 'venezia-hotel' ),
                ],
                'format' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'csv',
                    'enum'              => [ 'csv', 'json' ],
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => __( 'Export format.', 'venezia-hotel' ),
                ],
                'room_type_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'description'       => __( 'Room type filter (for occupancy report).', 'venezia-hotel' ),
                ],
                'group_by' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'daily',
                    'enum'              => [ 'daily', 'weekly', 'monthly' ],
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => __( 'Group by period (for revenue report).', 'venezia-hotel' ),
                ],
            ]
        );
    }

    /**
     * Validate a date string is in Y-m-d format.
     *
     * @param string          $value   The parameter value.
     * @param \WP_REST_Request $request The request object.
     * @param string          $param   The parameter name.
     * @return true|\WP_Error
     */
    public function validateDate( string $value, \WP_REST_Request $request, string $param ): true|\WP_Error {
        $date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

        if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
            return new \WP_Error(
                'rest_invalid_param',
                sprintf(
                    /* translators: %s: parameter name */
                    __( '%s must be a valid date in Y-m-d format.', 'venezia-hotel' ),
                    $param
                ),
                [ 'status' => 400 ]
            );
        }

        return true;
    }
}
