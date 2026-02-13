<?php
/**
 * Template: Public Booking Confirmation / Lookup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-widget" x-data="bookingConfirmation">

    <!-- Lookup Form -->
    <template x-if="!booking">
        <div class="nzl-card">
            <div class="nzl-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Look Up Your Booking', 'nozule' ); ?></h3>
            </div>
            <div class="nzl-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:0.75rem; align-items:flex-end;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Booking Number', 'nozule' ); ?></label>
                        <input type="text" class="nzl-input" x-model="bookingNumber" placeholder="NZL-2026-00001">
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Email', 'nozule' ); ?></label>
                        <input type="email" class="nzl-input" x-model="email" placeholder="guest@example.com">
                    </div>
                    <div>
                        <button class="nzl-btn nzl-btn-primary" @click="lookupBooking()" :disabled="loading || !bookingNumber || !email">
                            <span x-show="!loading"><?php esc_html_e( 'Search', 'nozule' ); ?></span>
                            <span x-show="loading" class="nzl-spinner"></span>
                        </button>
                    </div>
                </div>
                <template x-if="error">
                    <p class="nzl-error-text" style="margin-top:0.75rem;" x-text="error"></p>
                </template>
            </div>
        </div>
    </template>

    <!-- Booking Details -->
    <template x-if="booking">
        <div>
            <div class="nzl-card" style="margin-bottom:1rem;">
                <div class="nzl-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:1rem;">
                        <?php esc_html_e( 'Booking', 'nozule' ); ?> #<span x-text="booking.booking_number"></span>
                    </h3>
                    <span class="nzl-badge" :class="getStatusClass(booking.status)" x-text="NozuleI18n.t(booking.status)"></span>
                </div>
                <div class="nzl-card-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.875rem;">
                        <div><strong><?php esc_html_e( 'Guest', 'nozule' ); ?>:</strong> <span x-text="booking.guest_name"></span></div>
                        <div><strong><?php esc_html_e( 'Room Type', 'nozule' ); ?>:</strong> <span x-text="booking.room_type_name"></span></div>
                        <div><strong><?php esc_html_e( 'Check-in', 'nozule' ); ?>:</strong> <span x-text="formatDate(booking.check_in)"></span></div>
                        <div><strong><?php esc_html_e( 'Check-out', 'nozule' ); ?>:</strong> <span x-text="formatDate(booking.check_out)"></span></div>
                        <div><strong><?php esc_html_e( 'Nights', 'nozule' ); ?>:</strong> <span x-text="booking.nights"></span></div>
                        <div><strong><?php esc_html_e( 'Guests', 'nozule' ); ?>:</strong> <span x-text="booking.adults + ' adults, ' + booking.children + ' children'"></span></div>
                        <div><strong><?php esc_html_e( 'Total', 'nozule' ); ?>:</strong> <span x-text="formatPrice(booking.total_price)"></span></div>
                        <div><strong><?php esc_html_e( 'Payment', 'nozule' ); ?>:</strong> <span x-text="booking.payment_status"></span></div>
                    </div>
                </div>
                <div class="nzl-card-footer" style="display:flex; gap:0.5rem;">
                    <button class="nzl-btn nzl-btn-secondary" @click="booking = null; error = null;">
                        &larr; <?php esc_html_e( 'Back', 'nozule' ); ?>
                    </button>
                    <template x-if="booking.status === 'pending' || booking.status === 'confirmed'">
                        <button class="nzl-btn nzl-btn-danger" @click="showCancelForm = !showCancelForm">
                            <?php esc_html_e( 'Cancel Booking', 'nozule' ); ?>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Cancel Form -->
            <template x-if="showCancelForm">
                <div class="nzl-card" style="border-color:#ef4444;">
                    <div class="nzl-card-body">
                        <label class="nzl-label"><?php esc_html_e( 'Cancellation Reason', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="cancelReason"></textarea>
                        <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
                            <button class="nzl-btn nzl-btn-danger" @click="cancelBooking()" :disabled="loading || !cancelReason">
                                <?php esc_html_e( 'Confirm Cancellation', 'nozule' ); ?>
                            </button>
                            <button class="nzl-btn nzl-btn-secondary" @click="showCancelForm = false">
                                <?php esc_html_e( 'Never mind', 'nozule' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
