<?php
/**
 * Template: Admin Guests (CRM)
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlGuests">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Guests', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="openGuestModal()">
            <?php esc_html_e( 'Add Guest', 'nozule' ); ?>
        </button>
    </div>

    <!-- Search -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; gap:1rem; align-items:flex-end;">
            <div style="flex:1;">
                <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                <input type="text" x-model="search" @input.debounce.300ms="currentPage = 1; loadGuests()"
                       placeholder="<?php esc_attr_e( 'Name, email, phone...', 'nozule' ); ?>"
                       class="nzl-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Guests table -->
    <template x-if="!loading">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Phone', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Country', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Total Bookings', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Total Spent', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="guest in guests" :key="guest.id">
                        <tr>
                            <td>
                                <a href="#" class="nzl-link" @click.prevent="viewGuest(guest.id)"
                                   x-text="guest.first_name + ' ' + guest.last_name"></a>
                            </td>
                            <td><a :href="'mailto:' + guest.email" class="nzl-link" x-text="guest.email"></a></td>
                            <td x-text="guest.phone || '—'"></td>
                            <td x-text="guest.country || '—'"></td>
                            <td x-text="guest.total_bookings || 0"></td>
                            <td x-text="formatPrice(guest.total_spent || 0)"></td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <button class="nzl-btn nzl-btn-sm" @click="viewGuest(guest.id)">
                                        <?php esc_html_e( 'View', 'nozule' ); ?>
                                    </button>
                                    <button class="nzl-btn nzl-btn-sm" @click="editGuest(guest)">
                                        <?php esc_html_e( 'Edit', 'nozule' ); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="guests.length === 0">
                        <tr>
                            <td colspan="7" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No guests found.', 'nozule' ); ?>
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

    <!-- ======================= ADD/EDIT GUEST MODAL ======================= -->
    <template x-if="showGuestModal">
        <div class="nzl-modal-overlay" @click.self="showGuestModal = false">
            <div class="nzl-modal" style="max-width:680px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingGuestId ? '<?php echo esc_js( __( 'Edit Guest', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Guest', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showGuestModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Personal Info -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Personal Information', 'nozule' ); ?></h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'First Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="guestForm.first_name">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Last Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="guestForm.last_name">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Email', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="email" class="nzl-input" x-model="guestForm.email">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Phone', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="tel" class="nzl-input" x-model="guestForm.phone" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Alternative Phone', 'nozule' ); ?></label>
                            <input type="tel" class="nzl-input" x-model="guestForm.phone_alt" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Gender', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="guestForm.gender">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <option value="male"><?php esc_html_e( 'Male', 'nozule' ); ?></option>
                                <option value="female"><?php esc_html_e( 'Female', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Date of Birth', 'nozule' ); ?></label>
                            <input type="date" class="nzl-input" x-model="guestForm.date_of_birth">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Company', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.company">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Preferred Language', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="guestForm.language">
                                <option value="ar"><?php esc_html_e( 'Arabic', 'nozule' ); ?></option>
                                <option value="en"><?php esc_html_e( 'English', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Identity & Address -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Identity & Address', 'nozule' ); ?></h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Nationality', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.nationality">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'ID Type', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="guestForm.id_type">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <option value="passport"><?php esc_html_e( 'Passport', 'nozule' ); ?></option>
                                <option value="national_id"><?php esc_html_e( 'National ID', 'nozule' ); ?></option>
                                <option value="driving_license"><?php esc_html_e( 'Driving License', 'nozule' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Other', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'ID Number', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.id_number" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Country', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.country">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'City', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.city">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Address', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="guestForm.address">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="guestForm.notes" placeholder="<?php esc_attr_e( 'Internal notes about this guest...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showGuestModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveGuest()" :disabled="saving">
                        <span x-show="!saving" x-text="editingGuestId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= GUEST DETAIL PANEL ======================= -->
    <template x-if="showDetailPanel">
        <div class="nzl-modal-overlay" @click.self="closeDetail()">
            <div class="nzl-modal" style="max-width:780px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Guest Profile', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="closeDetail()">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Loading -->
                    <template x-if="loadingDetail">
                        <div style="text-align:center; padding:2rem;"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingDetail && selectedGuest">
                        <div>
                            <!-- Guest Info Card -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                                <div>
                                    <h4 style="font-size:1.25rem; font-weight:700; margin:0 0 0.5rem 0;" x-text="selectedGuest.first_name + ' ' + selectedGuest.last_name"></h4>
                                    <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                        <span><strong><?php esc_html_e( 'Email', 'nozule' ); ?>:</strong> <a :href="'mailto:' + selectedGuest.email" class="nzl-link" x-text="selectedGuest.email"></a></span>
                                        <span><strong><?php esc_html_e( 'Phone', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.phone" dir="ltr"></span></span>
                                        <template x-if="selectedGuest.phone_alt">
                                            <span><strong><?php esc_html_e( 'Alt. Phone', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.phone_alt" dir="ltr"></span></span>
                                        </template>
                                        <template x-if="selectedGuest.company">
                                            <span><strong><?php esc_html_e( 'Company', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.company"></span></span>
                                        </template>
                                        <span><strong><?php esc_html_e( 'Preferred Language', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.language === 'en' ? '<?php echo esc_js( __( 'English', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Arabic', 'nozule' ) ); ?>'"></span></span>
                                    </div>
                                </div>
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <template x-if="selectedGuest.nationality">
                                        <span><strong><?php esc_html_e( 'Nationality', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.nationality"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.country">
                                        <span><strong><?php esc_html_e( 'Country', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.country"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.city">
                                        <span><strong><?php esc_html_e( 'City', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.city"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.id_type">
                                        <span><strong><?php esc_html_e( 'ID', 'nozule' ); ?>:</strong> <span x-text="selectedGuest.id_number" dir="ltr"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.gender">
                                        <span><strong><?php esc_html_e( 'Gender', 'nozule' ); ?>:</strong> <span x-text="statusLabel(selectedGuest.gender)"></span></span>
                                    </template>
                                    <template x-if="selectedGuest.date_of_birth">
                                        <span><strong><?php esc_html_e( 'Date of Birth', 'nozule' ); ?>:</strong> <span x-text="formatDate(selectedGuest.date_of_birth)"></span></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Stats Cards -->
                            <div class="nzl-stats-grid" style="margin-bottom:1.5rem;">
                                <div class="nzl-stat-card">
                                    <div class="nzl-stat-value" x-text="selectedGuest.total_bookings || 0"></div>
                                    <div class="nzl-stat-label"><?php esc_html_e( 'Total Bookings', 'nozule' ); ?></div>
                                </div>
                                <div class="nzl-stat-card">
                                    <div class="nzl-stat-value" x-text="formatPrice(selectedGuest.total_spent || 0)"></div>
                                    <div class="nzl-stat-label"><?php esc_html_e( 'Total Spent', 'nozule' ); ?></div>
                                </div>
                                <div class="nzl-stat-card">
                                    <div class="nzl-stat-value" x-text="selectedGuest.total_nights || 0"></div>
                                    <div class="nzl-stat-label"><?php esc_html_e( 'Total Nights', 'nozule' ); ?></div>
                                </div>
                                <div class="nzl-stat-card">
                                    <div class="nzl-stat-value" x-text="selectedGuest.last_stay ? formatDate(selectedGuest.last_stay) : '—'"></div>
                                    <div class="nzl-stat-label"><?php esc_html_e( 'Last Stay', 'nozule' ); ?></div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <template x-if="selectedGuest.notes">
                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.75rem; margin-bottom:1.5rem;">
                                    <strong style="font-size:0.8rem; color:#475569;"><?php esc_html_e( 'Notes', 'nozule' ); ?>:</strong>
                                    <p style="margin:0.25rem 0 0; font-size:0.875rem; color:#334155; white-space:pre-wrap;" x-text="selectedGuest.notes"></p>
                                </div>
                            </template>

                            <!-- Booking History -->
                            <h4 style="font-size:0.95rem; font-weight:600; color:#1e293b; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Booking History', 'nozule' ); ?></h4>
                            <template x-if="guestBookings.length > 0">
                                <div class="nzl-table-wrap">
                                    <table class="nzl-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Check-in', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Check-out', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Nights', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Total', 'nozule' ); ?></th>
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
                                                        <span class="nzl-badge" :class="'nzl-badge-' + booking.status" x-text="statusLabel(booking.status)"></span>
                                                    </td>
                                                    <td x-text="formatPrice(booking.total_price)"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="guestBookings.length === 0">
                                <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;"><?php esc_html_e( 'No bookings yet.', 'nozule' ); ?></p>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="closeDetail()"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="editFromDetail()"><?php esc_html_e( 'Edit Guest', 'nozule' ); ?></button>
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
