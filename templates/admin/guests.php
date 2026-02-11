<?php
/**
 * Template: Admin Guests (CRM)
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
        <button class="vhm-btn vhm-btn-primary" @click="openGuestModal()">
            <?php esc_html_e( 'Add Guest', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Search -->
    <div class="vhm-card" style="margin-bottom:1rem;">
        <div style="display:flex; gap:1rem; align-items:flex-end;">
            <div style="flex:1;">
                <label class="vhm-label"><?php esc_html_e( 'Search', 'venezia-hotel' ); ?></label>
                <input type="text" x-model="search" @input.debounce.300ms="currentPage = 1; loadGuests()"
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
                        <th><?php esc_html_e( 'Total Spent', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="guest in guests" :key="guest.id">
                        <tr>
                            <td>
                                <a href="#" class="vhm-link" @click.prevent="viewGuest(guest.id)"
                                   x-text="guest.first_name + ' ' + guest.last_name"></a>
                            </td>
                            <td><a :href="'mailto:' + guest.email" class="vhm-link" x-text="guest.email"></a></td>
                            <td x-text="guest.phone || '—'"></td>
                            <td x-text="guest.country || '—'"></td>
                            <td x-text="guest.total_bookings || 0"></td>
                            <td x-text="formatPrice(guest.total_spent || 0)"></td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <button class="vhm-btn vhm-btn-sm" @click="viewGuest(guest.id)">
                                        <?php esc_html_e( 'View', 'venezia-hotel' ); ?>
                                    </button>
                                    <button class="vhm-btn vhm-btn-sm" @click="editGuest(guest)">
                                        <?php esc_html_e( 'Edit', 'venezia-hotel' ); ?>
                                    </button>
                                </div>
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

    <!-- ======================= ADD/EDIT GUEST MODAL ======================= -->
    <template x-if="showGuestModal">
        <div class="vhm-modal-overlay" @click.self="showGuestModal = false">
            <div class="vhm-modal" style="max-width:680px;">
                <div class="vhm-modal-header">
                    <h3 x-text="editingGuestId ? '<?php echo esc_js( __( 'Edit Guest', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Add Guest', 'venezia-hotel' ) ); ?>'"></h3>
                    <button class="vhm-modal-close" @click="showGuestModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <!-- Personal Info -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Personal Information', 'venezia-hotel' ); ?></h4>
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'First Name', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="guestForm.first_name">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Last Name', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="guestForm.last_name">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Email', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="email" class="vhm-input" x-model="guestForm.email">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="tel" class="vhm-input" x-model="guestForm.phone" dir="ltr">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Alternative Phone', 'venezia-hotel' ); ?></label>
                            <input type="tel" class="vhm-input" x-model="guestForm.phone_alt" dir="ltr">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Gender', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="guestForm.gender">
                                <option value=""><?php esc_html_e( '-- Select --', 'venezia-hotel' ); ?></option>
                                <option value="male"><?php esc_html_e( 'Male', 'venezia-hotel' ); ?></option>
                                <option value="female"><?php esc_html_e( 'Female', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Date of Birth', 'venezia-hotel' ); ?></label>
                            <input type="date" class="vhm-input" x-model="guestForm.date_of_birth">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Company', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.company">
                        </div>
                    </div>

                    <!-- Identity & Address -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Identity & Address', 'venezia-hotel' ); ?></h4>
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Nationality', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.nationality">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'ID Type', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="guestForm.id_type">
                                <option value=""><?php esc_html_e( '-- Select --', 'venezia-hotel' ); ?></option>
                                <option value="passport"><?php esc_html_e( 'Passport', 'venezia-hotel' ); ?></option>
                                <option value="national_id"><?php esc_html_e( 'National ID', 'venezia-hotel' ); ?></option>
                                <option value="driving_license"><?php esc_html_e( 'Driving License', 'venezia-hotel' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Other', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'ID Number', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.id_number" dir="ltr">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Country', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.country">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'City', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.city">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Address', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="guestForm.address">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="vhm-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'venezia-hotel' ); ?></label>
                        <textarea class="vhm-input" rows="3" x-model="guestForm.notes" placeholder="<?php esc_attr_e( 'Internal notes about this guest...', 'venezia-hotel' ); ?>"></textarea>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showGuestModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveGuest()" :disabled="saving">
                        <span x-show="!saving" x-text="editingGuestId ? '<?php echo esc_js( __( 'Update', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'venezia-hotel' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'venezia-hotel' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= GUEST DETAIL PANEL ======================= -->
    <template x-if="showDetailPanel">
        <div class="vhm-modal-overlay" @click.self="closeDetail()">
            <div class="vhm-modal" style="max-width:780px; max-height:90vh; overflow-y:auto;">
                <div class="vhm-modal-header">
                    <h3><?php esc_html_e( 'Guest Profile', 'venezia-hotel' ); ?></h3>
                    <button class="vhm-modal-close" @click="closeDetail()">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <!-- Loading -->
                    <template x-if="loadingDetail">
                        <div style="text-align:center; padding:2rem;"><div class="vhm-spinner vhm-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingDetail && selectedGuest">
                        <div>
                            <!-- Guest Info Card -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                                <div>
                                    <h4 style="font-size:1.25rem; font-weight:700; margin:0 0 0.5rem 0;" x-text="selectedGuest.first_name + ' ' + selectedGuest.last_name"></h4>
                                    <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                        <span><strong><?php esc_html_e( 'Email', 'venezia-hotel' ); ?>:</strong> <a :href="'mailto:' + selectedGuest.email" class="vhm-link" x-text="selectedGuest.email"></a></span>
                                        <span><strong><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.phone" dir="ltr"></span></span>
                                        <template x-if="selectedGuest.phone_alt">
                                            <span><strong><?php esc_html_e( 'Alt. Phone', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.phone_alt" dir="ltr"></span></span>
                                        </template>
                                        <template x-if="selectedGuest.company">
                                            <span><strong><?php esc_html_e( 'Company', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.company"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <template x-if="selectedGuest.nationality">
                                        <span><strong><?php esc_html_e( 'Nationality', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.nationality"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.country">
                                        <span><strong><?php esc_html_e( 'Country', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.country"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.city">
                                        <span><strong><?php esc_html_e( 'City', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.city"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.id_type">
                                        <span><strong><?php esc_html_e( 'ID', 'venezia-hotel' ); ?>:</strong> <span x-text="selectedGuest.id_number" dir="ltr"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.gender">
                                        <span><strong><?php esc_html_e( 'Gender', 'venezia-hotel' ); ?>:</strong> <span x-text="statusLabel(selectedGuest.gender)"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.date_of_birth">
                                        <span><strong><?php esc_html_e( 'Date of Birth', 'venezia-hotel' ); ?>:</strong> <span x-text="formatDate(selectedGuest.date_of_birth)"></span></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div class="vhm-stats-grid" style="margin-bottom:1.5rem;">
                                <div class="vhm-stat-card">
                                    <div class="vhm-stat-value" x-text="selectedGuest.total_bookings || 0"></div>
                                    <div class="vhm-stat-label"><?php esc_html_e( 'Total Bookings', 'venezia-hotel' ); ?></div>
                                </div>
                                <div class="vhm-stat-card">
                                    <div class="vhm-stat-value" x-text="formatPrice(selectedGuest.total_spent || 0)"></div>
                                    <div class="vhm-stat-label"><?php esc_html_e( 'Total Spent', 'venezia-hotel' ); ?></div>
                                </div>
                                <div class="vhm-stat-card">
                                    <div class="vhm-stat-value" x-text="selectedGuest.total_nights || 0"></div>
                                    <div class="vhm-stat-label"><?php esc_html_e( 'Total Nights', 'venezia-hotel' ); ?></div>
                                </div>
                                <div class="vhm-stat-card">
                                    <div class="vhm-stat-value" x-text="selectedGuest.last_stay ? formatDate(selectedGuest.last_stay) : '—'"></div>
                                    <div class="vhm-stat-label"><?php esc_html_e( 'Last Stay', 'venezia-hotel' ); ?></div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <template x-if="selectedGuest.notes">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.75rem; margin-bottom:1.5rem;">
                                    <strong style="font-size:0.8rem; color:#475569;"><?php esc_html_e( 'Notes', 'venezia-hotel' ); ?>:</strong>
                                    <p style="margin:0.25rem 0 0; font-size:0.875rem; color:#334155; white-space:pre-wrap;" x-text="selectedGuest.notes"></p>
                                </div>
                            </template>

                            <!-- Booking History -->
                            <h4 style="font-size:0.95rem; font-weight:600; color:#1e293b; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Booking History', 'venezia-hotel' ); ?></h4>
                            <template x-if="guestBookings.length > 0">
                                <div class="vhm-table-wrap">
                                    <table class="vhm-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Booking #', 'venezia-hotel' ); ?></th>
                                                <th><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?></th>
                                                <th><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?></th>
                                                <th><?php esc_html_e( 'Nights', 'venezia-hotel' ); ?></th>
                                                <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                                                <th><?php esc_html_e( 'Total', 'venezia-hotel' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="booking in guestBookings" :key="booking.id">
                                                <tr>
                                                    <td x-text="booking.booking_number"></td>
                                                    <td x-text="formatDate(booking.check_in)"></td>
                                                    <td x-text="formatDate(booking.check_out)"></td>
                                                    <td x-text="booking.nights"></td>
                                                    <td>
                                                        <span class="vhm-badge" :class="'vhm-badge-' + booking.status" x-text="statusLabel(booking.status)"></span>
                                                    </td>
                                                    <td x-text="formatPrice(booking.total_price)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="guestBookings.length === 0">
                                <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;"><?php esc_html_e( 'No bookings yet.', 'venezia-hotel' ); ?></p>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="closeDetail()"><?php esc_html_e( 'Close', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="editFromDetail()"><?php esc_html_e( 'Edit Guest', 'venezia-hotel' ); ?></button>
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
