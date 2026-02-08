<?php
/**
 * Template: Public Booking Form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-widget" x-data="bookingForm">
    <!-- No selection warning -->
    <template x-if="!hasSelection && step !== 'confirmed'">
        <div class="vhm-card">
            <div class="vhm-card-body" style="text-align:center; padding:2rem; color:#6b7280;">
                <p><?php esc_html_e( 'Please select a room first using the booking search widget.', 'venezia-hotel' ); ?></p>
            </div>
        </div>
    </template>

    <!-- Booking Summary Bar -->
    <template x-if="hasSelection && step !== 'confirmed'">
        <div class="vhm-card" style="margin-bottom:1.5rem; background:#f8fafc;">
            <div class="vhm-card-body" style="display:flex; flex-wrap:wrap; gap:1.5rem; justify-content:space-between; align-items:center;">
                <div>
                    <strong x-text="selection.roomType.name"></strong>
                    <span style="color:#6b7280;">&middot;</span>
                    <span x-text="formatDate(selection.checkIn)"></span> &rarr; <span x-text="formatDate(selection.checkOut)"></span>
                    <span style="color:#6b7280;">(<span x-text="selection.nights"></span> <?php esc_html_e( 'nights', 'venezia-hotel' ); ?>)</span>
                </div>
                <div style="font-weight:700; color:#1e40af; font-size:1.125rem;" x-text="formatPrice(selection.ratePlan ? selection.ratePlan.total : 0)"></div>
            </div>
        </div>
    </template>

    <!-- Step: Guest Details -->
    <template x-if="hasSelection && step === 'details'">
        <div class="vhm-card">
            <div class="vhm-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Guest Details', 'venezia-hotel' ); ?></h3>
            </div>
            <div class="vhm-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'First Name', 'venezia-hotel' ); ?> *</label>
                        <input type="text" class="vhm-input" :class="{'is-error': errors.first_name}" x-model="guest.first_name">
                        <template x-if="errors.first_name"><p class="vhm-error-text" x-text="errors.first_name"></p></template>
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Last Name', 'venezia-hotel' ); ?> *</label>
                        <input type="text" class="vhm-input" :class="{'is-error': errors.last_name}" x-model="guest.last_name">
                        <template x-if="errors.last_name"><p class="vhm-error-text" x-text="errors.last_name"></p></template>
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Email', 'venezia-hotel' ); ?> *</label>
                        <input type="email" class="vhm-input" :class="{'is-error': errors.email}" x-model="guest.email">
                        <template x-if="errors.email"><p class="vhm-error-text" x-text="errors.email"></p></template>
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?> *</label>
                        <input type="tel" class="vhm-input" :class="{'is-error': errors.phone}" x-model="guest.phone">
                        <template x-if="errors.phone"><p class="vhm-error-text" x-text="errors.phone"></p></template>
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Nationality', 'venezia-hotel' ); ?></label>
                        <input type="text" class="vhm-input" x-model="guest.nationality">
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'Estimated Arrival Time', 'venezia-hotel' ); ?></label>
                        <input type="time" class="vhm-input" x-model="guest.arrival_time">
                    </div>
                </div>
                <div style="margin-top:1rem;">
                    <label class="vhm-label"><?php esc_html_e( 'Special Requests', 'venezia-hotel' ); ?></label>
                    <textarea class="vhm-input" rows="3" x-model="guest.special_requests"></textarea>
                </div>
            </div>
            <div class="vhm-card-footer" style="text-align:right;">
                <button class="vhm-btn vhm-btn-primary vhm-btn-lg" @click="goToReview()">
                    <?php esc_html_e( 'Continue to Review', 'venezia-hotel' ); ?> &rarr;
                </button>
            </div>
        </div>
    </template>

    <!-- Step: Review -->
    <template x-if="hasSelection && step === 'review'">
        <div class="vhm-card">
            <div class="vhm-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Review Your Booking', 'venezia-hotel' ); ?></h3>
            </div>
            <div class="vhm-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.875rem;">
                    <div><strong><?php esc_html_e( 'Name', 'venezia-hotel' ); ?>:</strong> <span x-text="guest.first_name + ' ' + guest.last_name"></span></div>
                    <div><strong><?php esc_html_e( 'Email', 'venezia-hotel' ); ?>:</strong> <span x-text="guest.email"></span></div>
                    <div><strong><?php esc_html_e( 'Phone', 'venezia-hotel' ); ?>:</strong> <span x-text="guest.phone"></span></div>
                    <div><strong><?php esc_html_e( 'Room', 'venezia-hotel' ); ?>:</strong> <span x-text="selection.roomType.name"></span></div>
                    <div><strong><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?>:</strong> <span x-text="formatDate(selection.checkIn)"></span></div>
                    <div><strong><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?>:</strong> <span x-text="formatDate(selection.checkOut)"></span></div>
                    <div><strong><?php esc_html_e( 'Guests', 'venezia-hotel' ); ?>:</strong> <span x-text="selection.adults + ' adults, ' + selection.children + ' children'"></span></div>
                    <div><strong><?php esc_html_e( 'Nights', 'venezia-hotel' ); ?>:</strong> <span x-text="selection.nights"></span></div>
                </div>
                <template x-if="errors.submit">
                    <div style="margin-top:1rem; padding:0.75rem; background:#fef2f2; border:1px solid #fecaca; border-radius:0.375rem; color:#991b1b; font-size:0.875rem;" x-text="errors.submit"></div>
                </template>
            </div>
            <div class="vhm-card-footer" style="display:flex; justify-content:space-between;">
                <button class="vhm-btn vhm-btn-secondary" @click="goBack()">&larr; <?php esc_html_e( 'Back', 'venezia-hotel' ); ?></button>
                <button class="vhm-btn vhm-btn-primary vhm-btn-lg" @click="submitBooking()" :disabled="loading">
                    <?php esc_html_e( 'Confirm Booking', 'venezia-hotel' ); ?>
                </button>
            </div>
        </div>
    </template>

    <!-- Step: Processing -->
    <template x-if="step === 'processing'">
        <div class="vhm-card">
            <div class="vhm-card-body" style="text-align:center; padding:3rem;">
                <div class="vhm-spinner vhm-spinner-lg" style="margin-bottom:1rem;"></div>
                <p style="color:#6b7280;"><?php esc_html_e( 'Processing your booking...', 'venezia-hotel' ); ?></p>
            </div>
        </div>
    </template>

    <!-- Step: Confirmed -->
    <template x-if="step === 'confirmed' && booking">
        <div class="vhm-card" style="border-color:#10b981;">
            <div class="vhm-card-body" style="text-align:center; padding:2rem;">
                <div style="font-size:3rem; margin-bottom:0.5rem;">&#10003;</div>
                <h2 style="color:#10b981; margin:0 0 0.5rem 0;"><?php esc_html_e( 'Booking Confirmed!', 'venezia-hotel' ); ?></h2>
                <p style="font-size:1.25rem; margin:0 0 1.5rem 0;">
                    <?php esc_html_e( 'Booking Number', 'venezia-hotel' ); ?>: <strong x-text="booking.booking_number"></strong>
                </p>
                <p style="color:#6b7280; font-size:0.875rem;">
                    <?php esc_html_e( 'A confirmation email has been sent to', 'venezia-hotel' ); ?>
                    <strong x-text="booking.guest_email || guest.email"></strong>
                </p>
            </div>
        </div>
    </template>
</div>
