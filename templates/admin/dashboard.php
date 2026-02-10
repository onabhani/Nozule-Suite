<?php
/**
 * Template: Admin Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmDashboard">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Hotel Dashboard', 'venezia-hotel' ); ?></h1>
        <span style="font-size:0.875rem; color:#64748b;"><?php echo esc_html( current_time( 'l, F j, Y' ) ); ?></span>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Stats -->
    <template x-if="!loading && stats">
        <div>
            <div class="vhm-stats-grid">
                <div class="vhm-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Arrivals", 'venezia-hotel' ); ?></div>
                    <div class="stat-value" x-text="stats.arrivals_count || 0"></div>
                </div>
                <div class="vhm-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Departures", 'venezia-hotel' ); ?></div>
                    <div class="stat-value" x-text="stats.departures_count || 0"></div>
                </div>
                <div class="vhm-stat-card">
                    <div class="stat-label"><?php esc_html_e( 'In-House Guests', 'venezia-hotel' ); ?></div>
                    <div class="stat-value" x-text="stats.in_house_count || 0"></div>
                </div>
                <div class="vhm-stat-card">
                    <div class="stat-label"><?php esc_html_e( 'Occupancy Rate', 'venezia-hotel' ); ?></div>
                    <div class="stat-value" x-text="(stats.occupancy_rate || 0) + '%'"></div>
                </div>
                <div class="vhm-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Revenue", 'venezia-hotel' ); ?></div>
                    <div class="stat-value" x-text="formatPrice(stats.today_revenue || 0)"></div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="vhm-tabs">
                <button class="vhm-tab" :class="{'active': activeTab === 'arrivals'}" @click="activeTab = 'arrivals'">
                    <?php esc_html_e( 'Arrivals', 'venezia-hotel' ); ?> (<span x-text="arrivals.length"></span>)
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'departures'}" @click="activeTab = 'departures'">
                    <?php esc_html_e( 'Departures', 'venezia-hotel' ); ?> (<span x-text="departures.length"></span>)
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'in-house'}" @click="activeTab = 'in-house'">
                    <?php esc_html_e( 'In-House', 'venezia-hotel' ); ?> (<span x-text="inHouse.length"></span>)
                </button>
            </div>

            <!-- Arrivals Table -->
            <template x-if="activeTab === 'arrivals'">
                <div class="vhm-table-wrap">
                    <table class="vhm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Nights', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="b in arrivals" :key="b.id">
                                <tr>
                                    <td x-text="b.booking_number"></td>
                                    <td x-text="b.guest_name"></td>
                                    <td x-text="b.room_type_name"></td>
                                    <td x-text="b.nights"></td>
                                    <td><span class="vhm-badge" :class="{'vhm-badge-pending': b.status === 'pending', 'vhm-badge-confirmed': b.status === 'confirmed'}" x-text="VeneziaI18n.t(b.status)"></span></td>
                                    <td>
                                        <template x-if="b.status === 'pending'">
                                            <button class="vhm-btn vhm-btn-sm vhm-btn-success" @click="confirmBooking(b.id)"><?php esc_html_e( 'Confirm', 'venezia-hotel' ); ?></button>
                                        </template>
                                        <template x-if="b.status === 'confirmed'">
                                            <button class="vhm-btn vhm-btn-sm vhm-btn-primary" @click="checkIn(b.id)"><?php esc_html_e( 'Check In', 'venezia-hotel' ); ?></button>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="arrivals.length === 0">
                                <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No arrivals today', 'venezia-hotel' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Departures Table -->
            <template x-if="activeTab === 'departures'">
                <div class="vhm-table-wrap">
                    <table class="vhm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Room', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Balance', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="b in departures" :key="b.id">
                                <tr>
                                    <td x-text="b.booking_number"></td>
                                    <td x-text="b.guest_name"></td>
                                    <td x-text="b.room_number || b.room_type_name"></td>
                                    <td x-text="formatPrice(b.total_price - b.amount_paid)"></td>
                                    <td>
                                        <button class="vhm-btn vhm-btn-sm vhm-btn-primary" @click="checkOut(b.id)"><?php esc_html_e( 'Check Out', 'venezia-hotel' ); ?></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="departures.length === 0">
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No departures today', 'venezia-hotel' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- In-House Table -->
            <template x-if="activeTab === 'in-house'">
                <div class="vhm-table-wrap">
                    <table class="vhm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Room', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?></th>
                                <th><?php esc_html_e( 'Balance', 'venezia-hotel' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="b in inHouse" :key="b.id">
                                <tr>
                                    <td x-text="b.booking_number"></td>
                                    <td x-text="b.guest_name"></td>
                                    <td x-text="b.room_number || b.room_type_name"></td>
                                    <td x-text="formatDate(b.check_out)"></td>
                                    <td x-text="formatPrice(b.total_price - b.amount_paid)"></td>
                                </tr>
                            </template>
                            <template x-if="inHouse.length === 0">
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No guests currently in-house', 'venezia-hotel' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>
</div>

<!-- Toast Notifications -->
<div class="vhm-toast-container" x-data x-show="$store.notifications.items.length > 0">
    <template x-for="notif in $store.notifications.items" :key="notif.id">
        <div class="vhm-toast" :class="'vhm-toast-' + notif.type">
            <span x-text="notif.message"></span>
            <button @click="$store.notifications.remove(notif.id)" style="margin-left:0.5rem; cursor:pointer; background:none; border:none;">&times;</button>
        </div>
    </template>
</div>
