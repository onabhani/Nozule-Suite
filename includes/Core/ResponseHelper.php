<?php
namespace NZL\Core;

class ResponseHelper {

    public static function success(
        mixed $data = null,
        string $message = '',
        int $status = 200,
        array $meta = []
    ): \WP_REST_Response {
        $body = [ 'success' => true ];
        if ( $message )    $body['message'] = $message;
        if ( $data !== null ) $body['data']  = $data;
        if ( $meta )       $body['meta']    = $meta;
        return new \WP_REST_Response( $body, $status );
    }

    public static function created( mixed $data = null, string $message = '' ): \WP_REST_Response {
        return self::success( $data, $message, 201 );
    }

    public static function error(
        string $message,
        int $status = 400,
        array $fields = [],
        string $code = ''
    ): \WP_REST_Response {
        $body = [ 'success' => false, 'message' => $message ];
        if ( $code )   $body['code']   = $code;
        if ( $fields ) $body['fields'] = $fields;
        return new \WP_REST_Response( $body, $status );
    }

    public static function notFound( string $message = 'Not found' ): \WP_REST_Response {
        return self::error( $message, 404 );
    }

    public static function forbidden( string $message = 'Forbidden' ): \WP_REST_Response {
        return self::error( $message, 403 );
    }

    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): \WP_REST_Response {
        return self::success( $items, '', 200, [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil( $total / max( 1, $perPage ) ),
        ]);
    }
}
