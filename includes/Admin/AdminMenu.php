<?php

namespace Nozule\Admin;

use Nozule\Core\Container;
use Nozule\Admin\Pages\DashboardPage;
use Nozule\Admin\Pages\BookingsPage;
use Nozule\Admin\Pages\CalendarPage;
use Nozule\Admin\Pages\GuestsPage;
use Nozule\Admin\Pages\RoomsPage;
use Nozule\Admin\Pages\RatesPage;
use Nozule\Admin\Pages\InventoryPage;
use Nozule\Admin\Pages\ReportsPage;
use Nozule\Admin\Pages\ChannelsPage;
use Nozule\Admin\Pages\SettingsPage;
use Nozule\Admin\Pages\HousekeepingPage;
use Nozule\Admin\Pages\BillingPage;
use Nozule\Admin\Pages\GroupsPage;
use Nozule\Admin\Pages\PromotionsPage;
use Nozule\Admin\Pages\MessagingPage;
use Nozule\Admin\Pages\CurrencyPage;
use Nozule\Admin\Pages\DynamicPricingPage;
use Nozule\Admin\Pages\ReviewsPage;
use Nozule\Admin\Pages\WhatsAppPage;
use Nozule\Admin\Pages\ChannelSyncPage;
use Nozule\Admin\Pages\MetasearchPage;
use Nozule\Admin\Pages\ForecastingPage;
use Nozule\Admin\Pages\LoyaltyPage;
use Nozule\Admin\Pages\POSPage;
use Nozule\Admin\Pages\RateShoppingPage;
use Nozule\Admin\Pages\BrandingPage;
use Nozule\Admin\Pages\EmployeesPage;

/**
 * Registers the WordPress admin menu structure for the Nozule plugin.
 */
class AdminMenu {

    private Container $container;

    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Hook into WordPress to register admin menus.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'addMenuPages' ] );
    }

    /**
     * Build the top-level menu and all sub-menus.
     */
    public function addMenuPages(): void {

        // ------------------------------------------------------------------
        // Top-level menu
        // ------------------------------------------------------------------
        add_menu_page(
            __( 'Nozule', 'nozule' ),
            __( 'Nozule', 'nozule' ),
            'nzl_staff',                   // minimum capability
            'nzl-dashboard',               // menu slug
            [ $this, 'renderDashboard' ],
            'dashicons-building',
            30
        );

        // ------------------------------------------------------------------
        // Sub-menus (staff-level)
        // ------------------------------------------------------------------
        add_submenu_page(
            'nzl-dashboard',
            __( 'Dashboard', 'nozule' ),
            __( 'Dashboard', 'nozule' ),
            'nzl_staff',
            'nzl-dashboard',
            [ $this, 'renderDashboard' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Bookings', 'nozule' ),
            __( 'Bookings', 'nozule' ),
            'nzl_staff',
            'nzl-bookings',
            [ $this, 'renderBookings' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Calendar', 'nozule' ),
            __( 'Calendar', 'nozule' ),
            'nzl_staff',
            'nzl-calendar',
            [ $this, 'renderCalendar' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Guests', 'nozule' ),
            __( 'Guests', 'nozule' ),
            'nzl_staff',
            'nzl-guests',
            [ $this, 'renderGuests' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Housekeeping', 'nozule' ),
            __( 'Housekeeping', 'nozule' ),
            'nzl_staff',
            'nzl-housekeeping',
            [ $this, 'renderHousekeeping' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Billing', 'nozule' ),
            __( 'Billing', 'nozule' ),
            'nzl_staff',
            'nzl-billing',
            [ $this, 'renderBilling' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Group Bookings', 'nozule' ),
            __( 'Group Bookings', 'nozule' ),
            'nzl_staff',
            'nzl-groups',
            [ $this, 'renderGroups' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Promotions', 'nozule' ),
            __( 'Promotions', 'nozule' ),
            'nzl_staff',
            'nzl-promotions',
            [ $this, 'renderPromotions' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Messaging', 'nozule' ),
            __( 'Messaging', 'nozule' ),
            'nzl_staff',
            'nzl-messaging',
            [ $this, 'renderMessaging' ]
        );

        // ------------------------------------------------------------------
        // Sub-menus (admin-level)
        // ------------------------------------------------------------------
        add_submenu_page(
            'nzl-dashboard',
            __( 'Rooms', 'nozule' ),
            __( 'Rooms', 'nozule' ),
            'nzl_admin',
            'nzl-rooms',
            [ $this, 'renderRooms' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Rates & Pricing', 'nozule' ),
            __( 'Rates & Pricing', 'nozule' ),
            'nzl_admin',
            'nzl-rates',
            [ $this, 'renderRates' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Inventory', 'nozule' ),
            __( 'Inventory', 'nozule' ),
            'nzl_admin',
            'nzl-inventory',
            [ $this, 'renderInventory' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Reports', 'nozule' ),
            __( 'Reports', 'nozule' ),
            'nzl_admin',
            'nzl-reports',
            [ $this, 'renderReports' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Channel Manager', 'nozule' ),
            __( 'Channel Manager', 'nozule' ),
            'nzl_admin',
            'nzl-channels',
            [ $this, 'renderChannels' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Dynamic Pricing', 'nozule' ),
            __( 'Dynamic Pricing', 'nozule' ),
            'nzl_admin',
            'nzl-dynamic-pricing',
            [ $this, 'renderDynamicPricing' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Currency', 'nozule' ),
            __( 'Currency', 'nozule' ),
            'nzl_admin',
            'nzl-currency',
            [ $this, 'renderCurrency' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Channel Sync', 'nozule' ),
            __( 'Channel Sync', 'nozule' ),
            'nzl_admin',
            'nzl-channel-sync',
            [ $this, 'renderChannelSync' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Reviews', 'nozule' ),
            __( 'Reviews', 'nozule' ),
            'nzl_admin',
            'nzl-reviews',
            [ $this, 'renderReviews' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'WhatsApp', 'nozule' ),
            __( 'WhatsApp', 'nozule' ),
            'nzl_admin',
            'nzl-whatsapp',
            [ $this, 'renderWhatsApp' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Metasearch', 'nozule' ),
            __( 'Metasearch', 'nozule' ),
            'nzl_admin',
            'nzl-metasearch',
            [ $this, 'renderMetasearch' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Forecasting', 'nozule' ),
            __( 'Forecasting', 'nozule' ),
            'nzl_admin',
            'nzl-forecasting',
            [ $this, 'renderForecasting' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Loyalty', 'nozule' ),
            __( 'Loyalty', 'nozule' ),
            'nzl_admin',
            'nzl-loyalty',
            [ $this, 'renderLoyalty' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'POS', 'nozule' ),
            __( 'POS', 'nozule' ),
            'nzl_staff',
            'nzl-pos',
            [ $this, 'renderPOS' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Rate Shopping', 'nozule' ),
            __( 'Rate Shopping', 'nozule' ),
            'nzl_admin',
            'nzl-rate-shopping',
            [ $this, 'renderRateShopping' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Branding', 'nozule' ),
            __( 'Branding', 'nozule' ),
            'nzl_admin',
            'nzl-branding',
            [ $this, 'renderBranding' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Employees', 'nozule' ),
            __( 'Employees', 'nozule' ),
            'nzl_manage_employees',
            'nzl-employees',
            [ $this, 'renderEmployees' ]
        );

        add_submenu_page(
            'nzl-dashboard',
            __( 'Settings', 'nozule' ),
            __( 'Settings', 'nozule' ),
            'nzl_admin',
            'nzl-settings',
            [ $this, 'renderSettings' ]
        );
    }

    // ------------------------------------------------------------------
    // Render callbacks
    // ------------------------------------------------------------------

    public function renderDashboard(): void {
        $this->container->get( DashboardPage::class )->render();
    }

    public function renderBookings(): void {
        $this->container->get( BookingsPage::class )->render();
    }

    public function renderCalendar(): void {
        $this->container->get( CalendarPage::class )->render();
    }

    public function renderGuests(): void {
        $this->container->get( GuestsPage::class )->render();
    }

    public function renderRooms(): void {
        $this->container->get( RoomsPage::class )->render();
    }

    public function renderRates(): void {
        $this->container->get( RatesPage::class )->render();
    }

    public function renderInventory(): void {
        $this->container->get( InventoryPage::class )->render();
    }

    public function renderReports(): void {
        $this->container->get( ReportsPage::class )->render();
    }

    public function renderChannels(): void {
        $this->container->get( ChannelsPage::class )->render();
    }

    public function renderSettings(): void {
        $this->container->get( SettingsPage::class )->render();
    }

    public function renderHousekeeping(): void {
        ( new HousekeepingPage() )->render();
    }

    public function renderBilling(): void {
        ( new BillingPage() )->render();
    }

    public function renderGroups(): void {
        ( new GroupsPage() )->render();
    }

    public function renderPromotions(): void {
        ( new PromotionsPage() )->render();
    }

    public function renderMessaging(): void {
        ( new MessagingPage() )->render();
    }

    public function renderCurrency(): void {
        ( new CurrencyPage() )->render();
    }

    public function renderDynamicPricing(): void {
        ( new DynamicPricingPage() )->render();
    }

    public function renderReviews(): void {
        ( new ReviewsPage() )->render();
    }

    public function renderWhatsApp(): void {
        ( new WhatsAppPage() )->render();
    }

    public function renderChannelSync(): void {
        ( new ChannelSyncPage() )->render();
    }

    public function renderMetasearch(): void {
        ( new MetasearchPage() )->render();
    }

    public function renderForecasting(): void {
        ( new ForecastingPage() )->render();
    }

    public function renderLoyalty(): void {
        ( new LoyaltyPage() )->render();
    }

    public function renderPOS(): void {
        ( new POSPage() )->render();
    }

    public function renderRateShopping(): void {
        ( new RateShoppingPage() )->render();
    }

    public function renderBranding(): void {
        ( new BrandingPage() )->render();
    }

    public function renderEmployees(): void {
        ( new EmployeesPage() )->render();
    }
}
