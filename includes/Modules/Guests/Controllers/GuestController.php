<?php

namespace Nozule\Modules\Guests\Controllers;

use Nozule\Core\PropertyScope;
use Nozule\Core\ResponseHelper;
use Nozule\Modules\Guests\Services\GuestService;
use Nozule\Modules\Guests\Repositories\GuestRepository;

/**
 * REST API controller for admin guest management.
 */
class GuestController {

    private const NAMESPACE = 'nozule/v1';

    private GuestService $service;
    private GuestRepository $repository;
    private PropertyScope $propertyScope;

    public function __construct( GuestService $service, GuestRepository $repository, PropertyScope $propertyScope ) {
        $this->service       = $service;
        $this->repository    = $repository;
        $this->propertyScope = $propertyScope;
    }

    /**
     * Register REST API routes.
     */
    public function registerRoutes(): void {
        register_rest_route( self::NAMESPACE, '/guests', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'listGuests' ],
                'permission_callback' => [ $this, 'checkPermission' ],
                'args'                => $this->getListArgs(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'createGuest' ],
                'permission_callback' => [ $this, 'checkPermission' ],
                'args'                => $this->getCreateArgs(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/guests/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getGuest' ],
                'permission_callback' => [ $this, 'checkPermission' ],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'updateGuest' ],
                'permission_callback' => [ $this, 'checkPermission' ],
                'args'                => $this->getUpdateArgs(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/guests/(?P<id>\d+)/history', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'getGuestHistory' ],
                'permission_callback' => [ $this, 'checkPermission' ],
                'args'                => [
                    'id' => [
                        'required'          => true,
                        'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ] );

        // Cross-property guest lookup (super admin only).
        register_rest_route( self::NAMESPACE, '/guests/cross-property/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'getCrossPropertyHistory' ],
            'permission_callback' => fn() => current_user_can( 'nzl_super_admin' ),
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/guests/cross-property/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'crossPropertySearch' ],
            'permission_callback' => fn() => current_user_can( 'nzl_super_admin' ),
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => fn( $v ) => is_email( $v ),
                ],
            ],
        ] );
    }

    /**
     * Permission callback: user must have the nzl_staff capability.
     */
    public function checkPermission( \WP_REST_Request $request ): bool {
        return current_user_can( 'manage_options' ) || current_user_can( 'nzl_staff' );
    }

    // Standard CRUD aliases (used by RestController admin routes)

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        $result = $this->repository->scopeToProperty( $this->propertyScope->getActivePropertyId() )->list( [
            'search'   => $request->get_param( 'search' ) ?? '',
            'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
            'order'    => $request->get_param( 'order' ) ?? 'DESC',
            'per_page' => $request->get_param( 'per_page' ) ?? 20,
            'page'     => $request->get_param( 'page' ) ?? 1,
        ] );

        $guests = array_map(
            fn( $guest ) => $guest->toArray(),
            $result['guests']
        );

        $page    = (int) ( $request->get_param( 'page' ) ?? 1 );
        $perPage = (int) ( $request->get_param( 'per_page' ) ?? 20 );

        return ResponseHelper::paginated( $guests, $result['total'], $page, $perPage );
    }

    public function show( \WP_REST_Request $request ): \WP_REST_Response {
        return $this->getGuest( $request );
    }

    /**
     * List guests with search and pagination.
     */
    public function listGuests( \WP_REST_Request $request ): \WP_REST_Response {
        $result = $this->repository->scopeToProperty( $this->propertyScope->getActivePropertyId() )->list( [
            'search'   => $request->get_param( 'search' ) ?? '',
            'orderby'  => $request->get_param( 'orderby' ) ?? 'created_at',
            'order'    => $request->get_param( 'order' ) ?? 'DESC',
            'per_page' => $request->get_param( 'per_page' ) ?? 20,
            'page'     => $request->get_param( 'page' ) ?? 1,
        ] );

        $guests = array_map(
            fn( $guest ) => $guest->toArray(),
            $result['guests']
        );

        $page    = (int) ( $request->get_param( 'page' ) ?? 1 );
        $perPage = (int) ( $request->get_param( 'per_page' ) ?? 20 );

        $response = ResponseHelper::paginated( $guests, $result['total'], $page, $perPage );
        $response->header( 'X-WP-Total', (string) $result['total'] );
        $response->header( 'X-WP-TotalPages', (string) $result['pages'] );

        return $response;
    }

    /**
     * Get a single guest by ID.
     */
    public function getGuest( \WP_REST_Request $request ): \WP_REST_Response {
        $id    = (int) $request->get_param( 'id' );
        $guest = $this->repository->find( $id );

        if ( ! $guest ) {
            return ResponseHelper::notFound( __( 'Guest not found.', 'nozule' ) );
        }

        if ( ! $this->propertyScope->canAccessAllProperties() && ( $guest->property_id ?? null ) !== $this->propertyScope->getActivePropertyId() ) {
            return ResponseHelper::forbidden( __( 'Forbidden', 'nozule' ) );
        }

        return ResponseHelper::success( $guest->toArray() );
    }

    /**
     * Create a new guest.
     */
    public function createGuest( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $this->extractGuestData( $request );

        try {
            $guest = $this->service->createGuest( $data );
            return ResponseHelper::created( $guest->toArray() );
        } catch ( \RuntimeException $e ) {
            return ResponseHelper::error( $e->getMessage(), 400 );
        }
    }

    /**
     * Update an existing guest.
     */
    public function updateGuest( \WP_REST_Request $request ): \WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );

        $guest = $this->repository->find( $id );
        if ( ! $guest ) {
            return ResponseHelper::notFound( __( 'Guest not found.', 'nozule' ) );
        }

        if ( ! $this->propertyScope->canAccessAllProperties() && ( $guest->property_id ?? null ) !== $this->propertyScope->getActivePropertyId() ) {
            return ResponseHelper::forbidden( __( 'Forbidden', 'nozule' ) );
        }

        $data = $this->extractGuestData( $request );

        if ( empty( $data ) ) {
            return ResponseHelper::error( __( 'No data provided for update.', 'nozule' ), 400 );
        }

        try {
            $guest = $this->service->updateGuestProfile( $id, $data );
            return ResponseHelper::success( $guest->toArray() );
        } catch ( \RuntimeException $e ) {
            $code = str_contains( $e->getMessage(), 'not found' ) ? 404 : 400;
            return ResponseHelper::error( $e->getMessage(), $code );
        }
    }

    /**
     * Get guest history and statistics.
     */
    public function getGuestHistory( \WP_REST_Request $request ): \WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        try {
            $history = $this->service->getGuestHistory( $id );

            // Convert the guest model to array within the history response.
            $response_data          = $history;
            $response_data['guest'] = $history['guest']->toArray();

            return ResponseHelper::success( $response_data );
        } catch ( \RuntimeException $e ) {
            return ResponseHelper::notFound( $e->getMessage() );
        }
    }

    /**
     * GET /guests/cross-property/{id}
     *
     * Returns a guest's booking history across all properties.
     */
    public function getCrossPropertyHistory( \WP_REST_Request $request ): \WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $data = $this->service->getCrossPropertyHistory( $id );

        if ( $data['guest'] === null ) {
            return ResponseHelper::notFound( __( 'Guest not found.', 'nozule' ) );
        }

        return ResponseHelper::success( $data );
    }

    /**
     * GET /guests/cross-property/search?email=...
     *
     * Find guest records across all properties by email.
     */
    public function crossPropertySearch( \WP_REST_Request $request ): \WP_REST_Response {
        $email  = sanitize_email( $request->get_param( 'email' ) );
        $guests = $this->service->findAllByEmail( $email );

        $data = array_map( fn( $g ) => $g->toArray(), $guests );

        return ResponseHelper::success( $data );
    }

    /**
     * Extract guest-related fields from the request body.
     */
    private function extractGuestData( \WP_REST_Request $request ): array {
        $fields = [
            'first_name', 'last_name', 'email', 'phone', 'phone_alt',
            'nationality', 'id_type', 'id_number', 'date_of_birth',
            'gender', 'address', 'city', 'country', 'company',
            'language', 'notes', 'tags', 'wp_user_id',
        ];

        $data = [];
        foreach ( $fields as $field ) {
            $value = $request->get_param( $field );
            if ( $value !== null ) {
                $data[ $field ] = $value;
            }
        }

        return $data;
    }

    /**
     * Argument definitions for the list endpoint.
     */
    private function getListArgs(): array {
        return [
            'search'   => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby'  => [
                'type'              => 'string',
                'default'           => 'created_at',
                'enum'              => [
                    'id', 'first_name', 'last_name', 'email',
                    'nationality', 'total_bookings', 'total_spent',
                    'last_stay', 'created_at',
                ],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'order'    => [
                'type'    => 'string',
                'default' => 'DESC',
                'enum'    => [ 'ASC', 'DESC' ],
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => 20,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'page'     => [
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Argument definitions for the create endpoint.
     */
    private function getCreateArgs(): array {
        return [
            'first_name' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name'  => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email'      => [
                'type'              => 'string',
                'required'          => true,
                'format'            => 'email',
                'sanitize_callback' => 'sanitize_email',
            ],
            'phone'      => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone_alt'  => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'nationality' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'id_type'     => [
                'type' => 'string',
                'enum' => [ 'passport', 'national_id', 'driving_license', 'other' ],
            ],
            'id_number'   => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'date_of_birth' => [
                'type'   => 'string',
                'format' => 'date',
            ],
            'gender'       => [
                'type' => 'string',
                'enum' => [ 'male', 'female', 'other' ],
            ],
            'address'      => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'city'         => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'country'      => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'company'      => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'language'     => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'notes'        => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'tags'         => [
                'type'  => 'array',
                'items' => [ 'type' => 'string' ],
            ],
            'wp_user_id'   => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Argument definitions for the update endpoint.
     */
    private function getUpdateArgs(): array {
        $args = $this->getCreateArgs();

        // On update, no fields are strictly required at the REST level.
        foreach ( $args as &$arg ) {
            unset( $arg['required'] );
        }

        $args['id'] = [
            'required'          => true,
            'validate_callback' => fn( $value ) => is_numeric( $value ) && (int) $value > 0,
            'sanitize_callback' => 'absint',
        ];

        return $args;
    }
}
