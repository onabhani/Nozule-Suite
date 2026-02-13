<?php
/**
 * Template: Admin Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlDashboard">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Hotel Dashboard', 'nozule' ); ?></h1>
        <span style="font-size:0.875rem; color:#64748b;"><?php echo esc_html( current_time( 'l, F j, Y' ) ); ?></span>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Stats -->
    <template x-if="!loading && stats">
        <div>
            <div class="nzl-stats-grid">
                <div class="nzl-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Arrivals", 'nozule' ); ?></div>
                    <div class="stat-value" x-text="stats.arrivals_count || 0"></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Departures", 'nozule' ); ?></div>
                    <div class="stat-value" x-text="stats.departures_count || 0"></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="stat-label"><?php esc_html_e( 'In-House Guests', 'nozule' ); ?></div>
                    <div class="stat-value" x-text="stats.in_house_count || 0"></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="stat-label"><?php esc_html_e( 'Occupancy Rate', 'nozule' ); ?></div>
                    <div class="stat-value" x-text="(stats.occupancy_rate || 0) + '%'"></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="stat-label"><?php esc_html_e( "Today's Revenue", 'nozule' ); ?></div>
                    <div class="stat-value" x-text="formatPrice(stats.today_revenue || 0)"></div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="nzl-tabs">
                <button class="nzl-tab" :class="{'active': activeTab === 'arrivals'}" @click="activeTab = 'arrivals'">
                    <?php esc_html_e( 'Arrivals', 'nozule' ); ?> (<span x-text="arrivals.length"></span>)
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'departures'}" @click="activeTab = 'departures'">
                    <?php esc_html_e( 'Departures', 'nozule' ); ?> (<span x-text="departures.length"></span>)
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'in-house'}" @click="activeTab = 'in-house'">
                    <?php esc_html_e( 'In-House', 'nozule' ); ?> (<span x-text="inHouse.length"></span>)
                </button>
            </div>

            <!-- Arrivals Table -->
            <template x-if="activeTab === 'arrivals'">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Nights', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="b in arrivals" :key="b.id">
                                <tr>
                                    <td x-text="b.booking_number"></td>
                                    <td x-text="b.guest_name"></td>
                                    <td x-text="b.room_type_name"></td>
                                    <td x-text="b.nights"></td>
                                    <td><span class="nzl-badge" :class="{'nzl-badge-pending': b.status === 'pending', 'nzl-badge-confirmed': b.status === 'confirmed'}" x-text="NozuleI18n.t(b.status)"></span></td>
                                    <td>
                                        <template x-if="b.status === 'pending'">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="confirmBooking(b.id)"><?php esc_html_e( 'Confirm', 'nozule' ); ?></button>
                                        </template>
                                        <template x-if="b.status === 'confirmed'">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkIn(b.id)"><?php esc_html_e( 'Check In', 'nozule' ); ?></button>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="arrivals.length === 0">
                                <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No arrivals today', 'nozule' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Departures Table -->
            <template x-if="activeTab === 'departures'">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Room', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Balance', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
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
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkOut(b.id)"><?php esc_html_e( 'Check Out', 'nozule' ); ?></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="departures.length === 0">
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No departures today', 'nozule' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- In-House Table -->
            <template x-if="activeTab === 'in-house'">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Guest', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Room', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Check-out', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Balance', 'nozule' ); ?></th>
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
                                <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No guests currently in-house', 'nozule' ); ?></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>
</div>

<!-- Toast Notifications -->
<div class="nzl-toast-container" x-data x-show="$store.notifications.items.length > 0">
    <template x-for="notif in $store.notifications.items" :key="notif.id">
        <div class="nzl-toast" :class="'nzl-toast-' + notif.type">
            <span x-text="notif.message"></span>
            <button @click="$store.notifications.remove(notif.id)" style="margin-left:0.5rem; cursor:pointer; background:none; border:none;">&times;</button>
        </div>
    </template>
</div>
