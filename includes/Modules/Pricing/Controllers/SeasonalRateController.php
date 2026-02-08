<?php

namespace Venezia\Modules\Pricing\Controllers;

use Venezia\Modules\Pricing\Repositories\SeasonalRateRepository;

class SeasonalRateController {

    private SeasonalRateRepository $repo;

    public function __construct( SeasonalRateRepository $repo ) {
        $this->repo = $repo;
    }

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        $rates = $this->repo->all( 'priority', 'DESC' );
        return new \WP_REST_Response( [
            'success' => true,
            'data'    => array_map( fn( $r ) => $r->toArray(), $rates ),
        ] );
    }

    public function store( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $request->get_json_params();

        $rate = $this->repo->create( [
            'rate_plan_id'   => $data['rate_plan_id'] ?? null,
            'room_type_id'   => $data['room_type_id'] ?? null,
            'name'           => sanitize_text_field( $data['name'] ?? '' ),
            'name_ar'        => sanitize_text_field( $data['name_ar'] ?? '' ),
            'start_date'     => sanitize_text_field( $data['start_date'] ?? '' ),
            'end_date'       => sanitize_text_field( $data['end_date'] ?? '' ),
            'price_modifier' => (float) ( $data['price_modifier'] ?? 0 ),
            'modifier_type'  => $data['modifier_type'] ?? 'percentage',
            'days_of_week'   => isset( $data['days_of_week'] ) ? wp_json_encode( $data['days_of_week'] ) : null,
            'priority'       => (int) ( $data['priority'] ?? 0 ),
            'status'         => $data['status'] ?? 'active',
        ] );

        if ( ! $rate ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'CREATE_FAILED', 'message' => 'Failed to create seasonal rate' ],
            ], 500 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => $rate->toArray() ], 201 );
    }

    public function update( \WP_REST_Request $request ): \WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $rate = $this->repo->find( $id );

        if ( ! $rate ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'NOT_FOUND', 'message' => 'Seasonal rate not found' ],
            ], 404 );
        }

        $data = $request->get_json_params();
        $this->repo->update( $id, array_filter( [
            'name'           => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null,
            'name_ar'        => isset( $data['name_ar'] ) ? sanitize_text_field( $data['name_ar'] ) : null,
            'start_date'     => $data['start_date'] ?? null,
            'end_date'       => $data['end_date'] ?? null,
            'price_modifier' => isset( $data['price_modifier'] ) ? (float) $data['price_modifier'] : null,
            'modifier_type'  => $data['modifier_type'] ?? null,
            'days_of_week'   => isset( $data['days_of_week'] ) ? wp_json_encode( $data['days_of_week'] ) : null,
            'priority'       => isset( $data['priority'] ) ? (int) $data['priority'] : null,
            'status'         => $data['status'] ?? null,
        ], fn( $v ) => $v !== null ) );

        $updated = $this->repo->find( $id );
        return new \WP_REST_Response( [ 'success' => true, 'data' => $updated->toArray() ] );
    }

    public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
        $this->repo->delete( (int) $request->get_param( 'id' ) );
        return new \WP_REST_Response( [ 'success' => true ] );
    }
}
