<?php
/**
 * Template: Admin Guests
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmGuests">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Guests', 'venezia-hotel' ); ?></h1>
    </div>

    <!-- Search -->
    <div class="vhm-card" style="margin-bottom:1rem;">
        <div style="display:flex; gap:1rem; align-items:flex-end;">
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Search', 'venezia-hotel' ); ?></label>
                <input type="text" x-model="search" @input.debounce.300ms="loadGuests()"
                       placeholder="<?php esc_attr_e( 'Name, email, phone...', 'venezia-hotel' ); ?>"
                       class="vhm-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Guests table -->
    <template x-if="!loading">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Country', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Total Bookings', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Last Stay', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="guest in guests" :key="guest.id">
                        <tr>
                            <td x-text="guest.first_name + ' ' + guest.last_name"></td>
                            <td><a :href="'mailto:' + guest.email" class="vhm-link" x-text="guest.email"></a></td>
                            <td x-text="guest.phone || '—'"></td>
                            <td x-text="guest.country || '—'"></td>
                            <td x-text="guest.total_bookings || 0"></td>
                            <td x-text="guest.last_stay ? formatDate(guest.last_stay) : '—'"></td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="viewGuest(guest.id)">
                                    <?php esc_html_e( 'View', 'venezia-hotel' ); ?>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="guests.length === 0">
                        <tr>
                            <td colspan="7" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No guests found.', 'venezia-hotel' ); ?>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Pagination -->
    <template x-if="totalPages > 1">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
            <span style="font-size:0.875rem; color:#64748b;">
                <?php esc_html_e( 'Page', 'venezia-hotel' ); ?> <span x-text="currentPage"></span>
                <?php esc_html_e( 'of', 'venezia-hotel' ); ?> <span x-text="totalPages"></span>
            </span>
            <div style="display:flex; gap:0.5rem;">
                <button class="vhm-btn vhm-btn-sm" @click="prevPage()" :disabled="currentPage <= 1"><?php esc_html_e( 'Previous', 'venezia-hotel' ); ?></button>
                <button class="vhm-btn vhm-btn-sm" @click="nextPage()" :disabled="currentPage >= totalPages"><?php esc_html_e( 'Next', 'venezia-hotel' ); ?></button>
            </div>
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
