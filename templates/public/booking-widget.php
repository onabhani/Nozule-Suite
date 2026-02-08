<?php
/**
 * Template: Public Booking Search Widget
 *
 * @var array $atts Shortcode attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$layout_class = ( $atts['layout'] ?? 'horizontal' ) === 'vertical' ? 'vhm-vertical' : 'vhm-horizontal';
?>
<div class="vhm-widget" x-data="bookingWidget">
    <div class="vhm-card">
        <div class="vhm-card-body">
            <div class="vhm-booking-widget <?php echo esc_attr( $layout_class ); ?>">
                <!-- Check-in -->
                <div class="vhm-field">
                    <label class="vhm-label" x-text="'<?php esc_attr_e( 'Check-in', 'venezia-hotel' ); ?>'"></label>
                    <input type="date" class="vhm-input" x-model="checkIn" :min="minCheckIn">
                </div>

                <!-- Check-out -->
                <div class="vhm-field">
                    <label class="vhm-label" x-text="'<?php esc_attr_e( 'Check-out', 'venezia-hotel' ); ?>'"></label>
                    <input type="date" class="vhm-input" x-model="checkOut" :min="minCheckOut">
                </div>

                <!-- Adults -->
                <div class="vhm-field">
                    <label class="vhm-label"><?php esc_html_e( 'Adults', 'venezia-hotel' ); ?></label>
                    <select class="vhm-input" x-model.number="adults">
                        <template x-for="n in 10" :key="n">
                            <option :value="n" x-text="n"></option>
                        </template>
                    </select>
                </div>

                <!-- Children -->
                <div class="vhm-field">
                    <label class="vhm-label"><?php esc_html_e( 'Children', 'venezia-hotel' ); ?></label>
                    <select class="vhm-input" x-model.number="children">
                        <template x-for="n in 6" :key="n - 1">
                            <option :value="n - 1" x-text="n - 1"></option>
                        </template>
                    </select>
                </div>

                <!-- Search Button -->
                <div class="vhm-field">
                    <button class="vhm-btn vhm-btn-primary vhm-btn-lg" style="width:100%"
                            @click="search()"
                            :disabled="!canSearch || loading">
                        <span x-show="!loading"><?php esc_html_e( 'Search', 'venezia-hotel' ); ?></span>
                        <span x-show="loading" class="vhm-spinner"></span>
                    </button>
                </div>
            </div>

            <!-- Nights display -->
            <template x-if="nights > 0">
                <p style="text-align:center; margin-top:0.75rem; color:#6b7280; font-size:0.875rem">
                    <span x-text="nights"></span> <?php esc_html_e( 'night(s)', 'venezia-hotel' ); ?>
                </p>
            </template>
        </div>
    </div>

    <!-- Error Message -->
    <template x-if="error">
        <div class="vhm-card" style="margin-top:1rem; border-color:#ef4444;">
            <div class="vhm-card-body" style="color:#ef4444;">
                <p x-text="error"></p>
            </div>
        </div>
    </template>

    <!-- Results -->
    <template x-if="results && results.length > 0">
        <div style="margin-top:1.5rem;">
            <template x-for="result in results" :key="result.room_type.id">
                <div class="vhm-card vhm-room-card" style="margin-bottom:1rem;">
                    <div class="vhm-card-body" style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center;">
                        <div style="flex:1; min-width:200px;">
                            <h3 style="font-size:1.125rem; font-weight:600; margin:0 0 0.5rem 0;" x-text="result.room_type.name"></h3>
                            <p style="font-size:0.875rem; color:#6b7280; margin:0 0 0.5rem 0;" x-text="result.room_type.description"></p>
                            <p style="font-size:0.75rem; color:#6b7280;">
                                <span x-text="result.available"></span> <?php esc_html_e( 'available', 'venezia-hotel' ); ?>
                                &middot;
                                <?php esc_html_e( 'Max', 'venezia-hotel' ); ?> <span x-text="result.room_type.max_occupancy"></span> <?php esc_html_e( 'guests', 'venezia-hotel' ); ?>
                            </p>
                        </div>
                        <div style="text-align:center;">
                            <div class="vhm-room-price" x-text="formatPrice(result.pricing.total)"></div>
                            <p style="font-size:0.75rem; color:#6b7280; margin:0.25rem 0;">
                                <span x-text="formatPrice(result.pricing.nightlyRates ? Object.values(result.pricing.nightlyRates)[0] : 0)"></span>
                                / <?php esc_html_e( 'night', 'venezia-hotel' ); ?>
                            </p>
                            <button class="vhm-btn vhm-btn-primary"
                                    @click="selectRoom(result.room_type, result.pricing.ratePlan || null)">
                                <?php esc_html_e( 'Select', 'venezia-hotel' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- No Results -->
    <template x-if="results && results.length === 0">
        <div class="vhm-card" style="margin-top:1.5rem;">
            <div class="vhm-card-body" style="text-align:center; padding:2rem; color:#6b7280;">
                <p><?php esc_html_e( 'No rooms available for the selected dates.', 'venezia-hotel' ); ?></p>
            </div>
        </div>
    </template>
</div>
