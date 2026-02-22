<?php

namespace Nozule\Core;

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

        do_action( 'nozule/booted', $this );
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
            \Nozule\Modules\Settings\SettingsModule::class,
            \Nozule\Modules\Rooms\RoomsModule::class,
            \Nozule\Modules\Guests\GuestsModule::class,
            \Nozule\Modules\Pricing\PricingModule::class,
            \Nozule\Modules\Pricing\DynamicPricingModule::class,
            \Nozule\Modules\Bookings\BookingsModule::class,
            \Nozule\Modules\Notifications\NotificationsModule::class,
            \Nozule\Modules\Channels\ChannelsModule::class,
            \Nozule\Modules\Channels\ChannelSyncModule::class,
            \Nozule\Modules\Reports\ReportsModule::class,
            \Nozule\Modules\Integrations\IntegrationsModule::class,
            \Nozule\Modules\Housekeeping\HousekeepingModule::class,
            \Nozule\Modules\Billing\BillingModule::class,
            \Nozule\Modules\Audit\AuditModule::class,
            \Nozule\Modules\Groups\GroupsModule::class,
            \Nozule\Modules\Promotions\PromotionsModule::class,
            \Nozule\Modules\Messaging\MessagingModule::class,
            \Nozule\Modules\WhatsApp\WhatsAppModule::class,
            \Nozule\Modules\Reviews\ReviewModule::class,
            \Nozule\Modules\Metasearch\MetasearchModule::class,
            \Nozule\Modules\Currency\CurrencyModule::class,
            \Nozule\Modules\Documents\DocumentsModule::class,
            \Nozule\Modules\Forecasting\ForecastingModule::class,
            \Nozule\Modules\Loyalty\LoyaltyModule::class,
            \Nozule\Modules\POS\POSModule::class,
            \Nozule\Modules\RateShopping\RateShoppingModule::class,
            \Nozule\Modules\Branding\BrandingModule::class,
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
            $admin_menu = new \Nozule\Admin\AdminMenu( $this->container );
            $admin_menu->register();

            $admin_assets = new \Nozule\Admin\AdminAssets( $this->container );
            $admin_assets->register();
        }

        // Public assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueuePublicAssets' ] );

        // Register shortcodes
        $this->registerShortcodes();

        // PWA support (manifest, meta tags, service worker)
        $pwa = new PWA();
        $pwa->register();

        // Cron events
        add_action( 'nzl_daily_maintenance', [ $this, 'runDailyMaintenance' ] );
        add_action( 'nzl_send_reminders', [ $this, 'sendReminders' ] );
    }

    /**
     * Load plugin text domain.
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'nozule',
            false,
            dirname( NZL_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Register REST API routes.
     */
    public function registerRestRoutes(): void {
        $rest = new \Nozule\API\RestController( $this->container );
        $rest->registerRoutes();

        $sse = new \Nozule\API\SSEController( $this->container );
        $sse->registerRoutes();
    }

    /**
     * Enqueue public-facing assets.
     */
    public function enqueuePublicAssets(): void {
        wp_enqueue_style(
            'nozule-public',
            NZL_PLUGIN_URL . 'assets/css/public.css',
            [],
            NZL_VERSION
        );

        // Core utilities (no Alpine dependency)
        wp_enqueue_script(
            'nozule-api',
            NZL_PLUGIN_URL . 'assets/js/core/api.js',
            [],
            NZL_VERSION,
            true
        );

        wp_enqueue_script(
            'nozule-utils',
            NZL_PLUGIN_URL . 'assets/js/core/utils.js',
            [],
            NZL_VERSION,
            true
        );

        wp_enqueue_script(
            'nozule-i18n',
            NZL_PLUGIN_URL . 'assets/js/core/i18n.js',
            [],
            NZL_VERSION,
            true
        );

        // Alpine store — registers alpine:init listener
        wp_enqueue_script(
            'nozule-store',
            NZL_PLUGIN_URL . 'assets/js/core/store.js',
            [ 'nozule-utils' ],
            NZL_VERSION,
            true
        );

        // Component scripts — register alpine:init listeners
        wp_enqueue_script(
            'nozule-booking-widget',
            NZL_PLUGIN_URL . 'assets/js/components/booking-widget.js',
            [ 'nozule-api', 'nozule-utils', 'nozule-store' ],
            NZL_VERSION,
            true
        );

        wp_enqueue_script(
            'nozule-booking-form',
            NZL_PLUGIN_URL . 'assets/js/components/booking-form.js',
            [ 'nozule-api', 'nozule-utils', 'nozule-store' ],
            NZL_VERSION,
            true
        );

        // Alpine.js CDN — loaded LAST so alpine:init listeners are ready
        wp_enqueue_script(
            'alpinejs',
            'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
            [ 'nozule-store', 'nozule-booking-widget', 'nozule-booking-form' ],
            '3.14.0',
            true
        );

        // Localize script with config
        wp_localize_script( 'nozule-api', 'NozuleConfig', [
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'apiBase'  => rest_url( 'nozule/v1' ),
            'siteUrl'  => home_url(),
            'locale'   => get_locale(),
            'currency' => $this->container->get( SettingsManager::class )->get( 'currency.default', 'USD' ),
            'dateFormat' => get_option( 'date_format', 'Y-m-d' ),
        ] );

        // RTL support
        if ( is_rtl() ) {
            wp_enqueue_style(
                'nozule-rtl',
                NZL_PLUGIN_URL . 'assets/css/rtl.css',
                [ 'nozule-public' ],
                NZL_VERSION
            );
        }
    }

    /**
     * Register shortcodes.
     */
    private function registerShortcodes(): void {
        add_shortcode( 'nozule_booking', function ( $atts ) {
            $atts = shortcode_atts( [
                'layout' => 'horizontal',
            ], $atts, 'nozule_booking' );

            ob_start();
            include NZL_PLUGIN_DIR . 'templates/public/booking-widget.php';
            return ob_get_clean();
        } );

        add_shortcode( 'nozule_rooms', function ( $atts ) {
            $atts = shortcode_atts( [
                'columns' => 3,
            ], $atts, 'nozule_rooms' );

            ob_start();
            include NZL_PLUGIN_DIR . 'templates/public/room-cards.php';
            return ob_get_clean();
        } );

        add_shortcode( 'nozule_booking_form', function () {
            ob_start();
            include NZL_PLUGIN_DIR . 'templates/public/booking-form.php';
            return ob_get_clean();
        } );

        add_shortcode( 'nozule_confirmation', function () {
            ob_start();
            include NZL_PLUGIN_DIR . 'templates/public/confirmation.php';
            return ob_get_clean();
        } );
    }

    /**
     * Daily maintenance tasks.
     */
    public function runDailyMaintenance(): void {
        // Mark no-shows
        $booking_service = $this->container->get( \Nozule\Modules\Bookings\Services\BookingService::class );
        $booking_service->markNoShows();

        // Clean old logs
        $this->container->get( Logger::class )->cleanOldEntries( 90 );
    }

    /**
     * Send scheduled reminders.
     */
    public function sendReminders(): void {
        $notification_service = $this->container->get(
            \Nozule\Modules\Notifications\Services\NotificationService::class
        );
        $notification_service->sendScheduledReminders();
    }
}
