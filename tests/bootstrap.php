<?php
/**
 * PHPUnit bootstrap for Nozule Suite.
 *
 * Loads the Composer autoloader and stubs the WordPress functions and
 * constants that the production code relies on. This allows unit tests
 * to run without a live WordPress installation.
 */

// Composer autoloader (loads both production and test namespaces).
require_once __DIR__ . '/../vendor/autoload.php';

// ── WordPress Constants ────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// ── WordPress Function Stubs ───────────────────────────────────────

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( (string) $email, FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = false ) {
		if ( $type === 'timestamp' || $type === 'U' ) {
			return $gmt ? time() : time() + (int) ( date( 'Z' ) );
		}
		$format = ( $type === 'mysql' ) ? 'Y-m-d H:i:s' : $type;
		return $gmt ? gmdate( $format ) : date( $format );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( ...$args ) {
		// No-op for tests.
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}
