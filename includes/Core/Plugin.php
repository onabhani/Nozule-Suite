<?php

namespace Venezia\Core;

/**
 * Main Plugin bootstrap class.
 */
class Plugin {

    private static ?Plugin $instance = null;
    private Container $container;
    private bool $booted = false;

    private function __construct() {
        $this->container = new Container();
    }

    public static function getInstance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the service container.
     */
    public function container(): Container {
        return $this->container;
    }

    /**
     * Boot the plugin: register services, modules, hooks.
     */
    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;

        $this->registerCoreServices();
        $this->registerModules();
        $this->registerHooks();

        do_action( 'venezia/booted', $this );
    }

    /**
     * Register core services into the container.
     */
    private function registerCoreServices(): void {
        $this->container->singleton( Database::class, function () {
            return new Database();
        } );

        $this->container->singleton( CacheManager::class, function () {
            return new CacheManager();
        } );

        $this->container->singleton( SettingsManager::class, function ( Container $c ) {
            return new SettingsManager( $c->get( Database::class ) );
        } );

        $this->container->singleton( Logger::class, function ( Container $c ) {
            return new Logger( $c->get( Database::class ) );
        } );

        $this->container->singleton( EventDispatcher::class, function () {
            return new EventDispatcher();
        } );
    }

    /**
     * Register all plugin modules.
     */
    private function registerModules(): void {
        $modules = [
            \Venezia\Modules\Settings\SettingsModule::class,
            \Venezia\Modules\Rooms\RoomsModule::class,
            \Venezia\Modules\Guests\GuestsModule::class,
            \Venezia\Modules\Pricing\PricingModule::class,
            \Venezia\Modules\Bookings\BookingsModule::class,
            \Venezia\Modules\Notifications\NotificationsModule::class,
            \Venezia\Modules\Channels\ChannelsModule::class,
            \Venezia\Modules\Reports\ReportsModule::class,
            \Venezia\Modules\Integrations\IntegrationsModule::class,
        ];

        foreach ( $modules as $module_class ) {
            $module = new $module_class( $this->container );
            $module->register();
        }
    }

    /**
     * Register global WordPress hooks.
     */
    private function registerHooks(): void {
        // Load translations
        add_action( 'init', [ $this, 'loadTextDomain' ] );

        // Register REST API routes
        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

        // Admin hooks
        if ( is_admin() ) {
            $admin_menu = new \Venezia\Admin\AdminMenu( $this->container );
            $admin_menu->register();

            $admin_assets = new \Venezia\Admin\AdminAssets();
            $admin_assets->register();
        }

        // Public assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueuePublicAssets' ] );

        // Register shortcodes
        $this->registerShortcodes();

        // Cron events
        add_action( 'vhm_daily_maintenance', [ $this, 'runDailyMaintenance' ] );
        add_action( 'vhm_send_reminders', [ $this, 'sendReminders' ] );
    }

    /**
     * Load plugin text domain.
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'venezia-hotel',
            false,
            dirname( VHM_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Register REST API routes.
     */
    public function registerRestRoutes(): void {
        $rest = new \Venezia\API\RestController( $this->container );
        $rest->registerRoutes();

        $sse = new \Venezia\API\SSEController( $this->container );
        $sse->registerRoutes();
    }

    /**
     * Enqueue public-facing assets.
     */
    public function enqueuePublicAssets(): void {
        wp_enqueue_style(
            'venezia-public',
            VHM_PLUGIN_URL . 'assets/css/public.css',
            [],
            VHM_VERSION
        );

        // Core utilities (no Alpine dependency)
        wp_enqueue_script(
            'venezia-api',
            VHM_PLUGIN_URL . 'assets/js/core/api.js',
            [],
            VHM_VERSION,
            true
        );

        wp_enqueue_script(
            'venezia-utils',
            VHM_PLUGIN_URL . 'assets/js/core/utils.js',
            [],
            VHM_VERSION,
            true
        );

        wp_enqueue_script(
            'venezia-i18n',
            VHM_PLUGIN_URL . 'assets/js/core/i18n.js',
            [],
            VHM_VERSION,
            true
        );

        // Alpine store — registers alpine:init listener
        wp_enqueue_script(
            'venezia-store',
            VHM_PLUGIN_URL . 'assets/js/core/store.js',
            [ 'venezia-utils' ],
            VHM_VERSION,
            true
        );

        // Component scripts — register alpine:init listeners
        wp_enqueue_script(
            'venezia-booking-widget',
            VHM_PLUGIN_URL . 'assets/js/components/booking-widget.js',
            [ 'venezia-api', 'venezia-utils', 'venezia-store' ],
            VHM_VERSION,
            true
        );

        wp_enqueue_script(
            'venezia-booking-form',
            VHM_PLUGIN_URL . 'assets/js/components/booking-form.js',
            [ 'venezia-api', 'venezia-utils', 'venezia-store' ],
            VHM_VERSION,
            true
        );

        // Alpine.js CDN — loaded LAST so alpine:init listeners are ready
        wp_enqueue_script(
            'alpinejs',
            'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
            [ 'venezia-store', 'venezia-booking-widget', 'venezia-booking-form' ],
            '3.14.0',
            true
        );

        // Localize script with config
        wp_localize_script( 'venezia-api', 'VeneziaConfig', [
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'apiBase'  => rest_url( 'venezia/v1' ),
            'siteUrl'  => home_url(),
            'locale'   => get_locale(),
            'currency' => $this->container->get( SettingsManager::class )->get( 'currency.default', 'USD' ),
            'dateFormat' => get_option( 'date_format', 'Y-m-d' ),
        ] );

        // RTL support
        if ( is_rtl() ) {
            wp_enqueue_style(
                'venezia-rtl',
                VHM_PLUGIN_URL . 'assets/css/rtl.css',
                [ 'venezia-public' ],
                VHM_VERSION
            );
        }
    }

    /**
     * Register shortcodes.
     */
    private function registerShortcodes(): void {
        add_shortcode( 'venezia_booking', function ( $atts ) {
            $atts = shortcode_atts( [
                'layout' => 'horizontal',
            ], $atts, 'venezia_booking' );

            ob_start();
            include VHM_PLUGIN_DIR . 'templates/public/booking-widget.php';
            return ob_get_clean();
        } );

        add_shortcode( 'venezia_rooms', function ( $atts ) {
            $atts = shortcode_atts( [
                'columns' => 3,
            ], $atts, 'venezia_rooms' );

            ob_start();
            include VHM_PLUGIN_DIR . 'templates/public/room-cards.php';
            return ob_get_clean();
        } );

        add_shortcode( 'venezia_booking_form', function () {
            ob_start();
            include VHM_PLUGIN_DIR . 'templates/public/booking-form.php';
            return ob_get_clean();
        } );

        add_shortcode( 'venezia_confirmation', function () {
            ob_start();
            include VHM_PLUGIN_DIR . 'templates/public/confirmation.php';
            return ob_get_clean();
        } );
    }

    /**
     * Daily maintenance tasks.
     */
    public function runDailyMaintenance(): void {
        // Mark no-shows
        $booking_service = $this->container->get( \Venezia\Modules\Bookings\Services\BookingService::class );
        $booking_service->markNoShows();

        // Clean old logs
        $this->container->get( Logger::class )->cleanOldEntries( 90 );
    }

    /**
     * Send scheduled reminders.
     */
    public function sendReminders(): void {
        $notification_service = $this->container->get(
            \Venezia\Modules\Notifications\Services\NotificationService::class
        );
        $notification_service->sendScheduledReminders();
    }
}
