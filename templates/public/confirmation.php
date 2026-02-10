<?php
/**
 * Template: Public Booking Confirmation / Lookup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-widget" x-data="bookingConfirmation">

    <!-- Lookup Form -->
    <template x-if="!booking">
        <div class="vhm-card">
            <div class="vhm-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Look Up Your Booking', 'venezia-hotel' ); ?></h3>
            </div>
            <div class="vhm-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:0.75rem; align-items:flex-end;">
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Booking Number', 'venezia-hotel' ); ?></label>
                        <input type="text" class="vhm-input" x-model="bookingNumber" placeholder="VHM-2026-00001">
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Email', 'venezia-hotel' ); ?></label>
                        <input type="email" class="vhm-input" x-model="email" placeholder="guest@example.com">
                    </div>
                    <div>
                        <button class="vhm-btn vhm-btn-primary" @click="lookupBooking()" :disabled="loading || !bookingNumber || !email">
                            <span x-show="!loading"><?php esc_html_e( 'Search', 'venezia-hotel' ); ?></span>
                            <span x-show="loading" class="vhm-spinner"></span>
                        </button>
                    </div>
                </div>
                <template x-if="error">
                    <p class="vhm-error-text" style="margin-top:0.75rem;" x-text="error"></p>
                </template>
            </div>
        </div>
    </template>

    <!-- Booking Details -->
    <template x-if="booking">
        <div>
            <div class="vhm-card" style="margin-bottom:1rem;">
                <div class="vhm-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; font-size:1rem;">
                        <?php esc_html_e( 'Booking', 'venezia-hotel' ); ?> #<span x-text="booking.booking_number"></span>
                    </h3>
                    <span class="vhm-badge" :class="getStatusClass(booking.status)" x-text="VeneziaI18n.t(booking.status)"></span>
                </div>
                <div class="vhm-card-body">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.875rem;">
                        <div><strong><?php esc_html_e( 'Guest', 'venezia-hotel' ); ?>:</strong> <span x-text="booking.guest_name"></span></div>
                        <div><strong><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?>:</strong> <span x-text="booking.room_type_name"></span></div>
                        <div><strong><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?>:</strong> <span x-text="formatDate(booking.check_in)"></span></div>
                        <div><strong><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?>:</strong> <span x-text="formatDate(booking.check_out)"></span></div>
                        <div><strong><?php esc_html_e( 'Nights', 'venezia-hotel' ); ?>:</strong> <span x-text="booking.nights"></span></div>
                        <div><strong><?php esc_html_e( 'Guests', 'venezia-hotel' ); ?>:</strong> <span x-text="booking.adults + ' adults, ' + booking.children + ' children'"></span></div>
                        <div><strong><?php esc_html_e( 'Total', 'venezia-hotel' ); ?>:</strong> <span x-text="formatPrice(booking.total_price)"></span></div>
                        <div><strong><?php esc_html_e( 'Payment', 'venezia-hotel' ); ?>:</strong> <span x-text="booking.payment_status"></span></div>
                    </div>
                </div>
                <div class="vhm-card-footer" style="display:flex; gap:0.5rem;">
                    <button class="vhm-btn vhm-btn-secondary" @click="booking = null; error = null;">
                        &larr; <?php esc_html_e( 'Back', 'venezia-hotel' ); ?>
                    </button>
                    <template x-if="booking.status === 'pending' || booking.status === 'confirmed'">
                        <button class="vhm-btn vhm-btn-danger" @click="showCancelForm = !showCancelForm">
                            <?php esc_html_e( 'Cancel Booking', 'venezia-hotel' ); ?>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Cancel Form -->
            <template x-if="showCancelForm">
                <div class="vhm-card" style="border-color:#ef4444;">
                    <div class="vhm-card-body">
                        <label class="vhm-label"><?php esc_html_e( 'Cancellation Reason', 'venezia-hotel' ); ?></label>
                        <textarea class="vhm-input" rows="3" x-model="cancelReason"></textarea>
                        <div style="margin-top:0.75rem; display:flex; gap:0.5rem;">
                            <button class="vhm-btn vhm-btn-danger" @click="cancelBooking()" :disabled="loading || !cancelReason">
                                <?php esc_html_e( 'Confirm Cancellation', 'venezia-hotel' ); ?>
                            </button>
                            <button class="vhm-btn vhm-btn-secondary" @click="showCancelForm = false">
                                <?php esc_html_e( 'Never mind', 'venezia-hotel' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
