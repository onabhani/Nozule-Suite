<?php

namespace Venezia\Modules\Pricing\Controllers;

use Venezia\Modules\Pricing\Repositories\RatePlanRepository;
use Venezia\Modules\Pricing\Validators\RatePlanValidator;

class RatePlanController {

    private RatePlanRepository $repo;
    private RatePlanValidator $validator;

    public function __construct( RatePlanRepository $repo, RatePlanValidator $validator ) {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function index( \WP_REST_Request $request ): \WP_REST_Response {
        $plans = $this->repo->getActive();
        return new \WP_REST_Response( [
            'success' => true,
            'data'    => array_map( fn( $p ) => $p->toArray(), $plans ),
        ] );
    }

    public function show( \WP_REST_Request $request ): \WP_REST_Response {
        $plan = $this->repo->find( (int) $request->get_param( 'id' ) );
        if ( ! $plan ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'NOT_FOUND', 'message' => 'Rate plan not found' ],
            ], 404 );
        }
        return new \WP_REST_Response( [ 'success' => true, 'data' => $plan->toArray() ] );
    }

    public function store( \WP_REST_Request $request ): \WP_REST_Response {
        $data = $request->get_json_params();

        if ( ! $this->validator->validateCreate( $data ) ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'VALIDATION_ERROR', 'message' => implode( ' ', $this->validator->getAllErrors() ) ],
            ], 422 );
        }

        $plan = $this->repo->create( [
            'room_type_id'      => $data['room_type_id'] ?? null,
            'name'              => sanitize_text_field( $data['name'] ),
            'name_ar'           => sanitize_text_field( $data['name_ar'] ),
            'code'              => sanitize_title( $data['code'] ),
            'description'       => sanitize_textarea_field( $data['description'] ?? '' ),
            'meal_plan'         => $data['meal_plan'] ?? 'room_only',
            'price_modifier'    => (float) ( $data['price_modifier'] ?? 0 ),
            'modifier_type'     => $data['modifier_type'] ?? 'fixed',
            'is_default'        => (int) ( $data['is_default'] ?? 0 ),
            'is_refundable'     => (int) ( $data['is_refundable'] ?? 1 ),
            'cancellation_hours' => (int) ( $data['cancellation_hours'] ?? 24 ),
            'min_stay'          => $data['min_stay'] ?? null,
            'max_stay'          => $data['max_stay'] ?? null,
            'valid_from'        => $data['valid_from'] ?? null,
            'valid_to'          => $data['valid_to'] ?? null,
            'status'            => $data['status'] ?? 'active',
        ] );

        if ( ! $plan ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'CREATE_FAILED', 'message' => 'Failed to create rate plan' ],
            ], 500 );
        }

        return new \WP_REST_Response( [ 'success' => true, 'data' => $plan->toArray() ], 201 );
    }

    public function update( \WP_REST_Request $request ): \WP_REST_Response {
        $id   = (int) $request->get_param( 'id' );
        $plan = $this->repo->find( $id );

        if ( ! $plan ) {
            return new \WP_REST_Response( [
                'success' => false,
                'error'   => [ 'code' => 'NOT_FOUND', 'message' => 'Rate plan not found' ],
            ], 404 );
        }

        $data = $request->get_json_params();
        $this->repo->update( $id, array_filter( [
            'name'              => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null,
            'name_ar'           => isset( $data['name_ar'] ) ? sanitize_text_field( $data['name_ar'] ) : null,
            'description'       => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : null,
            'meal_plan'         => $data['meal_plan'] ?? null,
            'price_modifier'    => isset( $data['price_modifier'] ) ? (float) $data['price_modifier'] : null,
            'modifier_type'     => $data['modifier_type'] ?? null,
            'is_default'        => isset( $data['is_default'] ) ? (int) $data['is_default'] : null,
            'is_refundable'     => isset( $data['is_refundable'] ) ? (int) $data['is_refundable'] : null,
            'status'            => $data['status'] ?? null,
        ], fn( $v ) => $v !== null ) );

        $updated = $this->repo->find( $id );
        return new \WP_REST_Response( [ 'success' => true, 'data' => $updated->toArray() ] );
    }

    public function destroy( \WP_REST_Request $request ): \WP_REST_Response {
        $id = (int) $request->get_param( 'id' );
        $this->repo->delete( $id );
        return new \WP_REST_Response( [ 'success' => true ] );
    }
}
