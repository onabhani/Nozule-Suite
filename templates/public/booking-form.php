<?php
/**
 * Template: Public Booking Form
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-widget" x-data="bookingForm">
    <!-- No selection warning -->
    <template x-if="!hasSelection && step !== 'confirmed'">
        <div class="nzl-card">
            <div class="nzl-card-body" style="text-align:center; padding:2rem; color:#6b7280;">
                <p><?php esc_html_e( 'Please select a room first using the booking search widget.', 'nozule' ); ?></p>
            </div>
        </div>
    </template>

    <!-- Booking Summary Bar -->
    <template x-if="hasSelection && step !== 'confirmed'">
        <div class="nzl-card" style="margin-bottom:1.5rem; background:#f8fafc;">
            <div class="nzl-card-body" style="display:flex; flex-wrap:wrap; gap:1.5rem; justify-content:space-between; align-items:center;">
                <div>
                    <strong x-text="selection.roomType.name"></strong>
                    <span style="color:#6b7280;">&middot;</span>
                    <span x-text="formatDate(selection.checkIn)"></span> &rarr; <span x-text="formatDate(selection.checkOut)"></span>
                    <span style="color:#6b7280;">(<span x-text="selection.nights"></span> <?php esc_html_e( 'nights', 'nozule' ); ?>)</span>
                </div>
                <div style="font-weight:700; color:#1e40af; font-size:1.125rem;" x-text="formatPrice(selection.ratePlan ? selection.ratePlan.total : 0)"></div>
            </div>
        </div>
    </template>

    <!-- Step: Guest Details -->
    <template x-if="hasSelection && step === 'details'">
        <div class="nzl-card">
            <div class="nzl-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Guest Details', 'nozule' ); ?></h3>
            </div>
            <div class="nzl-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'First Name', 'nozule' ); ?> *</label>
                        <input type="text" class="nzl-input" :class="{'is-error': errors.first_name}" x-model="guest.first_name">
                        <template x-if="errors.first_name"><p class="nzl-error-text" x-text="errors.first_name"></p></template>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Last Name', 'nozule' ); ?> *</label>
                        <input type="text" class="nzl-input" :class="{'is-error': errors.last_name}" x-model="guest.last_name">
                        <template x-if="errors.last_name"><p class="nzl-error-text" x-text="errors.last_name"></p></template>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Email', 'nozule' ); ?> *</label>
                        <input type="email" class="nzl-input" :class="{'is-error': errors.email}" x-model="guest.email">
                        <template x-if="errors.email"><p class="nzl-error-text" x-text="errors.email"></p></template>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Phone', 'nozule' ); ?> *</label>
                        <input type="tel" class="nzl-input" :class="{'is-error': errors.phone}" x-model="guest.phone">
                        <template x-if="errors.phone"><p class="nzl-error-text" x-text="errors.phone"></p></template>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Nationality', 'nozule' ); ?></label>
                        <input type="text" class="nzl-input" x-model="guest.nationality">
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Estimated Arrival Time', 'nozule' ); ?></label>
                        <input type="time" class="nzl-input" x-model="guest.arrival_time">
                    </div>
                </div>
                <div style="margin-top:1rem;">
                    <label class="nzl-label"><?php esc_html_e( 'Special Requests', 'nozule' ); ?></label>
                    <textarea class="nzl-input" rows="3" x-model="guest.special_requests"></textarea>
                </div>
            </div>
            <div class="nzl-card-footer" style="text-align:right;">
                <button class="nzl-btn nzl-btn-primary nzl-btn-lg" @click="goToReview()">
                    <?php esc_html_e( 'Continue to Review', 'nozule' ); ?> &rarr;
                </button>
            </div>
        </div>
    </template>

    <!-- Step: Review -->
    <template x-if="hasSelection && step === 'review'">
        <div class="nzl-card">
            <div class="nzl-card-header">
                <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Review Your Booking', 'nozule' ); ?></h3>
            </div>
            <div class="nzl-card-body">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; font-size:0.875rem;">
                    <div><strong><?php esc_html_e( 'Name', 'nozule' ); ?>:</strong> <span x-text="guest.first_name + ' ' + guest.last_name"></span></div>
                    <div><strong><?php esc_html_e( 'Email', 'nozule' ); ?>:</strong> <span x-text="guest.email"></span></div>
                    <div><strong><?php esc_html_e( 'Phone', 'nozule' ); ?>:</strong> <span x-text="guest.phone"></span></div>
                    <div><strong><?php esc_html_e( 'Room', 'nozule' ); ?>:</strong> <span x-text="selection.roomType.name"></span></div>
                    <div><strong><?php esc_html_e( 'Check-in', 'nozule' ); ?>:</strong> <span x-text="formatDate(selection.checkIn)"></span></div>
                    <div><strong><?php esc_html_e( 'Check-out', 'nozule' ); ?>:</strong> <span x-text="formatDate(selection.checkOut)"></span></div>
                    <div><strong><?php esc_html_e( 'Guests', 'nozule' ); ?>:</strong> <span x-text="selection.adults + ' adults, ' + selection.children + ' children'"></span></div>
                    <div><strong><?php esc_html_e( 'Nights', 'nozule' ); ?>:</strong> <span x-text="selection.nights"></span></div>
                </div>
                <template x-if="errors.submit">
                    <div style="margin-top:1rem; padding:0.75rem; background:#fef2f2; border:1px solid #fecaca; border-radius:0.375rem; color:#991b1b; font-size:0.875rem;" x-text="errors.submit"></div>
                </template>
            </div>
            <div class="nzl-card-footer" style="display:flex; justify-content:space-between;">
                <button class="nzl-btn nzl-btn-secondary" @click="goBack()">&larr; <?php esc_html_e( 'Back', 'nozule' ); ?></button>
                <button class="nzl-btn nzl-btn-primary nzl-btn-lg" @click="submitBooking()" :disabled="loading">
                    <?php esc_html_e( 'Confirm Booking', 'nozule' ); ?>
                </button>
            </div>
        </div>
    </template>

    <!-- Step: Processing -->
    <template x-if="step === 'processing'">
        <div class="nzl-card">
            <div class="nzl-card-body" style="text-align:center; padding:3rem;">
                <div class="nzl-spinner nzl-spinner-lg" style="margin-bottom:1rem;"></div>
                <p style="color:#6b7280;"><?php esc_html_e( 'Processing your booking...', 'nozule' ); ?></p>
            </div>
        </div>
    </template>

    <!-- Step: Confirmed -->
    <template x-if="step === 'confirmed' && booking">
        <div class="nzl-card" style="border-color:#10b981;">
            <div class="nzl-card-body" style="text-align:center; padding:2rem;">
                <div style="font-size:3rem; margin-bottom:0.5rem;">&#10003;</div>
                <h2 style="color:#10b981; margin:0 0 0.5rem 0;"><?php esc_html_e( 'Booking Confirmed!', 'nozule' ); ?></h2>
                <p style="font-size:1.25rem; margin:0 0 1.5rem 0;">
                    <?php esc_html_e( 'Booking Number', 'nozule' ); ?>: <strong x-text="booking.booking_number"></strong>
                </p>
                <p style="color:#6b7280; font-size:0.875rem;">
                    <?php esc_html_e( 'A confirmation email has been sent to', 'nozule' ); ?>
                    <strong x-text="booking.guest_email || guest.email"></strong>
                </p>
            </div>
        </div>
    </template>
</div>
