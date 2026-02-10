<?php

namespace Venezia\Admin;

use Venezia\Core\Container;
use Venezia\Admin\Pages\DashboardPage;
use Venezia\Admin\Pages\BookingsPage;
use Venezia\Admin\Pages\CalendarPage;
use Venezia\Admin\Pages\GuestsPage;
use Venezia\Admin\Pages\RoomsPage;
use Venezia\Admin\Pages\RatesPage;
use Venezia\Admin\Pages\InventoryPage;
use Venezia\Admin\Pages\ReportsPage;
use Venezia\Admin\Pages\ChannelsPage;
use Venezia\Admin\Pages\SettingsPage;

/**
 * Registers the WordPress admin menu structure for the Hotel Manager plugin.
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
            __( 'Hotel Manager', 'venezia-hotel' ),
            __( 'Hotel Manager', 'venezia-hotel' ),
            'vhm_staff',                   // minimum capability
            'vhm-dashboard',               // menu slug
            [ $this, 'renderDashboard' ],
            'dashicons-building',
            30
        );

        // ------------------------------------------------------------------
        // Sub-menus (staff-level)
        // ------------------------------------------------------------------
        add_submenu_page(
            'vhm-dashboard',
            __( 'Dashboard', 'venezia-hotel' ),
            __( 'Dashboard', 'venezia-hotel' ),
            'vhm_staff',
            'vhm-dashboard',
            [ $this, 'renderDashboard' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Bookings', 'venezia-hotel' ),
            __( 'Bookings', 'venezia-hotel' ),
            'vhm_staff',
            'vhm-bookings',
            [ $this, 'renderBookings' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Calendar', 'venezia-hotel' ),
            __( 'Calendar', 'venezia-hotel' ),
            'vhm_staff',
            'vhm-calendar',
            [ $this, 'renderCalendar' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Guests', 'venezia-hotel' ),
            __( 'Guests', 'venezia-hotel' ),
            'vhm_staff',
            'vhm-guests',
            [ $this, 'renderGuests' ]
        );

        // ------------------------------------------------------------------
        // Sub-menus (admin-level)
        // ------------------------------------------------------------------
        add_submenu_page(
            'vhm-dashboard',
            __( 'Rooms', 'venezia-hotel' ),
            __( 'Rooms', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-rooms',
            [ $this, 'renderRooms' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Rates & Pricing', 'venezia-hotel' ),
            __( 'Rates & Pricing', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-rates',
            [ $this, 'renderRates' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Inventory', 'venezia-hotel' ),
            __( 'Inventory', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-inventory',
            [ $this, 'renderInventory' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Reports', 'venezia-hotel' ),
            __( 'Reports', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-reports',
            [ $this, 'renderReports' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Channel Manager', 'venezia-hotel' ),
            __( 'Channel Manager', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-channels',
            [ $this, 'renderChannels' ]
        );

        add_submenu_page(
            'vhm-dashboard',
            __( 'Settings', 'venezia-hotel' ),
            __( 'Settings', 'venezia-hotel' ),
            'vhm_admin',
            'vhm-settings',
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
}
