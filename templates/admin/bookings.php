<?php
/**
 * Template: Admin Bookings
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlBookingManager">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Bookings', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="openCreateModal()">
            <?php esc_html_e( 'Add New Booking', 'nozule' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                <select x-model="filters.status" @change="loadBookings()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Statuses', 'nozule' ); ?></option>
                    <option value="pending"><?php esc_html_e( 'Pending', 'nozule' ); ?></option>
                    <option value="confirmed"><?php esc_html_e( 'Confirmed', 'nozule' ); ?></option>
                    <option value="checked_in"><?php esc_html_e( 'Checked In', 'nozule' ); ?></option>
                    <option value="checked_out"><?php esc_html_e( 'Checked Out', 'nozule' ); ?></option>
                    <option value="cancelled"><?php esc_html_e( 'Cancelled', 'nozule' ); ?></option>
                    <option value="no_show"><?php esc_html_e( 'No Show', 'nozule' ); ?></option>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                <input type="date" x-model="filters.from" @change="loadBookings()" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                <input type="date" x-model="filters.to" @change="loadBookings()" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                <input type="text" x-model="filters.search" @input.debounce.300ms="loadBookings()"
                       placeholder="<?php esc_attr_e( 'Booking #, guest name...', 'nozule' ); ?>"
                       class="nzl-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Bookings table -->
    <template x-if="!loading">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Guest', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Check-in', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Check-out', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="booking in bookings" :key="booking.id">
                        <tr>
                            <td>
                                <a href="#" @click.prevent="viewBooking(booking.id)" class="nzl-link" x-text="booking.booking_number"></a>
                            </td>
                            <td x-text="booking.guest_name"></td>
                            <td x-text="booking.room_type_name"></td>
                            <td x-text="formatDate(booking.check_in)"></td>
                            <td x-text="formatDate(booking.check_out)"></td>
                            <td>
                                <span class="nzl-badge" :class="'nzl-badge-' + booking.status" x-text="NozuleI18n.t(booking.status)"></span>
                            </td>
                            <td x-text="formatPrice(booking.total_price)"></td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <template x-if="booking.status === 'pending'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="confirmBooking(booking.id)">
                                            <?php esc_html_e( 'Confirm', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="booking.status === 'confirmed'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkIn(booking.id)">
                                            <?php esc_html_e( 'Check In', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="booking.status === 'checked_in'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkOut(booking.id)">
                                            <?php esc_html_e( 'Check Out', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="['pending','confirmed'].includes(booking.status)">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="cancelBooking(booking.id)">
                                            <?php esc_html_e( 'Cancel', 'nozule' ); ?>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="bookings.length === 0">
                        <tr>
                            <td colspan="8" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No bookings found.', 'nozule' ); ?>
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
                <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="currentPage"></span>
                <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="totalPages"></span>
            </span>
            <div style="display:flex; gap:0.5rem;">
                <button class="nzl-btn nzl-btn-sm" @click="prevPage()" :disabled="currentPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                <button class="nzl-btn nzl-btn-sm" @click="nextPage()" :disabled="currentPage >= totalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
            </div>
        </div>
    </template>

    <!-- Add New Booking Modal -->
    <template x-if="showCreateModal">
        <div class="nzl-modal-overlay" @click.self="showCreateModal = false">
            <div class="nzl-modal" style="max-width:640px;">
                <div class="nzl-modal-header">
                    <h2><?php esc_html_e( 'Add New Booking', 'nozule' ); ?></h2>
                    <button @click="showCreateModal = false" class="nzl-modal-close">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Guest First Name', 'nozule' ); ?></label>
                            <input type="text" x-model="bookingForm.guest_first_name" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Guest Last Name', 'nozule' ); ?></label>
                            <input type="text" x-model="bookingForm.guest_last_name" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Email', 'nozule' ); ?></label>
                            <input type="email" x-model="bookingForm.guest_email" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Phone', 'nozule' ); ?></label>
                            <input type="tel" x-model="bookingForm.guest_phone" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                            <select x-model="bookingForm.room_type_id" class="nzl-input">
                                <option value=""><?php esc_html_e( 'Select Room Type', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select x-model="bookingForm.status" class="nzl-input">
                                <option value="pending"><?php esc_html_e( 'Pending', 'nozule' ); ?></option>
                                <option value="confirmed"><?php esc_html_e( 'Confirmed', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Check-in', 'nozule' ); ?></label>
                            <input type="date" x-model="bookingForm.check_in" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Check-out', 'nozule' ); ?></label>
                            <input type="date" x-model="bookingForm.check_out" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Adults', 'nozule' ); ?></label>
                            <input type="number" x-model="bookingForm.adults" min="1" class="nzl-input">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Children', 'nozule' ); ?></label>
                            <input type="number" x-model="bookingForm.children" min="0" class="nzl-input">
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="nzl-label"><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                            <textarea x-model="bookingForm.notes" class="nzl-input" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showCreateModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveBooking()"><?php esc_html_e( 'Save Booking', 'nozule' ); ?></button>
                </div>
            </div>
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
