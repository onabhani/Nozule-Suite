<?php
/**
 * Plugin Name: Venezia Hotel Manager
 * Plugin URI:  https://github.com/onabhani/Rawaq-Suite
 * Description: A comprehensive hotel booking management system for WordPress. Manage rooms, bookings, guests, pricing, channels, and reports.
 * Version:     1.0.1
 * Author:      hdqah.com
 * Author URI:  https://hdqah.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: venezia-hotel
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'VHM_VERSION', '1.0.1' );
define( 'VHM_PLUGIN_FILE', __FILE__ );
define( 'VHM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VHM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VHM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'VHM_DB_VERSION', '1.0.0' );

// Autoloader
if ( file_exists( VHM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once VHM_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Manual autoloader for when Composer is not available
    spl_autoload_register( function ( $class ) {
        $prefix = 'Venezia\\';
        $base_dir = VHM_PLUGIN_DIR . 'includes/';

        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, $len );
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );
}

/**
 * Get the plugin instance.
 *
 * @return \Venezia\Core\Plugin
 */
function venezia_hotel_manager(): \Venezia\Core\Plugin {
    return \Venezia\Core\Plugin::getInstance();
}

// Activation hook
register_activation_hook( __FILE__, function () {
    require_once VHM_PLUGIN_DIR . 'includes/Core/Activator.php';
    \Venezia\Core\Activator::activate();
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
    require_once VHM_PLUGIN_DIR . 'includes/Core/Deactivator.php';
    \Venezia\Core\Deactivator::deactivate();
} );

// Initialize the plugin
add_action( 'plugins_loaded', function () {
    venezia_hotel_manager()->boot();
}, 10 );
