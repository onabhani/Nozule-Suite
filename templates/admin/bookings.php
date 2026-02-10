<?php
/**
 * Template: Admin Bookings
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmBookingManager">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Bookings', 'venezia-hotel' ); ?></h1>
        <button class="vhm-btn vhm-btn-primary" @click="openCreateModal()">
            <?php esc_html_e( 'Add New Booking', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="vhm-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                <select x-model="filters.status" @change="loadBookings()" class="vhm-input">
                    <option value=""><?php esc_html_e( 'All Statuses', 'venezia-hotel' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pending', 'venezia-hotel' ); ?></option>
                    <option value="confirmed"><?php esc_html_e( 'Confirmed', 'venezia-hotel' ); ?></option>
                    <option value="checked_in"><?php esc_html_e( 'Checked In', 'venezia-hotel' ); ?></option>
                    <option value="checked_out"><?php esc_html_e( 'Checked Out', 'venezia-hotel' ); ?></option>
                    <option value="cancelled"><?php esc_html_e( 'Cancelled', 'venezia-hotel' ); ?></option>
                    <option value="no_show"><?php esc_html_e( 'No Show', 'venezia-hotel' ); ?></option>
                </select>
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'From', 'venezia-hotel' ); ?></label>
                <input type="date" x-model="filters.from" @change="loadBookings()" class="vhm-input">
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'To', 'venezia-hotel' ); ?></label>
                <input type="date" x-model="filters.to" @change="loadBookings()" class="vhm-input">
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Search', 'venezia-hotel' ); ?></label>
                <input type="text" x-model="filters.search" @input.debounce.300ms="loadBookings()"
                       placeholder="<?php esc_attr_e( 'Booking #, guest name...', 'venezia-hotel' ); ?>"
                       class="vhm-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Bookings table -->
    <template x-if="!loading">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Booking #', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Guest', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="booking in bookings" :key="booking.id">
                        <tr>
                            <td>
                                <a href="#" @click.prevent="viewBooking(booking.id)" class="vhm-link" x-text="booking.booking_number"></a>
                            </td>
                            <td x-text="booking.guest_name"></td>
                            <td x-text="booking.room_type_name"></td>
                            <td x-text="formatDate(booking.check_in)"></td>
                            <td x-text="formatDate(booking.check_out)"></td>
                            <td>
                                <span class="vhm-badge" :class="'vhm-badge-' + booking.status" x-text="booking.status"></span>
                            </td>
                            <td x-text="formatPrice(booking.total_price)"></td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <template x-if="booking.status === 'pending'">
                                        <button class="vhm-btn vhm-btn-sm vhm-btn-success" @click="confirmBooking(booking.id)">
                                            <?php esc_html_e( 'Confirm', 'venezia-hotel' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="booking.status === 'confirmed'">
                                        <button class="vhm-btn vhm-btn-sm vhm-btn-primary" @click="checkIn(booking.id)">
                                            <?php esc_html_e( 'Check In', 'venezia-hotel' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="booking.status === 'checked_in'">
                                        <button class="vhm-btn vhm-btn-sm vhm-btn-primary" @click="checkOut(booking.id)">
                                            <?php esc_html_e( 'Check Out', 'venezia-hotel' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="['pending','confirmed'].includes(booking.status)">
                                        <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="cancelBooking(booking.id)">
                                            <?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="bookings.length === 0">
                        <tr>
                            <td colspan="8" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No bookings found.', 'venezia-hotel' ); ?>
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

    <!-- Add New Booking Modal -->
    <template x-if="showCreateModal">
        <div class="vhm-modal-overlay" @click.self="showCreateModal = false">
            <div class="vhm-modal" style="max-width:640px;">
                <div class="vhm-modal-header">
                    <h2><?php esc_html_e( 'Add New Booking', 'venezia-hotel' ); ?></h2>
                    <button @click="showCreateModal = false" class="vhm-modal-close">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-grid">
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Guest First Name', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="bookingForm.guest_first_name" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Guest Last Name', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="bookingForm.guest_last_name" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Email', 'venezia-hotel' ); ?></label>
                            <input type="email" x-model="bookingForm.guest_email" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?></label>
                            <input type="tel" x-model="bookingForm.guest_phone" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></label>
                            <select x-model="bookingForm.room_type_id" class="vhm-input">
                                <option value=""><?php esc_html_e( 'Select Room Type', 'venezia-hotel' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                            <select x-model="bookingForm.status" class="vhm-input">
                                <option value="pending"><?php esc_html_e( 'Pending', 'venezia-hotel' ); ?></option>
                                <option value="confirmed"><?php esc_html_e( 'Confirmed', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?></label>
                            <input type="date" x-model="bookingForm.check_in" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?></label>
                            <input type="date" x-model="bookingForm.check_out" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Adults', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="bookingForm.adults" min="1" class="vhm-input">
                        </div>
                        <div>
                            <label class="vhm-label"><?php esc_html_e( 'Children', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="bookingForm.children" min="0" class="vhm-input">
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="vhm-label"><?php esc_html_e( 'Notes', 'venezia-hotel' ); ?></label>
                            <textarea x-model="bookingForm.notes" class="vhm-input" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showCreateModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveBooking()"><?php esc_html_e( 'Save Booking', 'venezia-hotel' ); ?></button>
                </div>
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
