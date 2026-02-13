<?php
/**
 * Template: Public Booking Search Widget
 *
 * @var array $atts Shortcode attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$layout_class = ( $atts['layout'] ?? 'horizontal' ) === 'vertical' ? 'nzl-vertical' : 'nzl-horizontal';
?>
<div class="nzl-widget" x-data="bookingWidget">
    <div class="nzl-card">
        <div class="nzl-card-body">
            <div class="nzl-booking-widget <?php echo esc_attr( $layout_class ); ?>">
                <!-- Check-in -->
                <div class="nzl-field">
                    <label class="nzl-label" x-text="'<?php esc_attr_e( 'Check-in', 'nozule' ); ?>'"></label>
                    <input type="date" class="nzl-input" x-model="checkIn" :min="minCheckIn">
                </div>

                <!-- Check-out -->
                <div class="nzl-field">
                    <label class="nzl-label" x-text="'<?php esc_attr_e( 'Check-out', 'nozule' ); ?>'"></label>
                    <input type="date" class="nzl-input" x-model="checkOut" :min="minCheckOut">
                </div>

                <!-- Adults -->
                <div class="nzl-field">
                    <label class="nzl-label"><?php esc_html_e( 'Adults', 'nozule' ); ?></label>
                    <select class="nzl-input" x-model.number="adults">
                        <template x-for="n in 10" :key="n">
                            <option :value="n" x-text="n"></option>
                        </template>
                    </select>
                </div>

                <!-- Children -->
                <div class="nzl-field">
                    <label class="nzl-label"><?php esc_html_e( 'Children', 'nozule' ); ?></label>
                    <select class="nzl-input" x-model.number="children">
                        <template x-for="n in 6" :key="n - 1">
                            <option :value="n - 1" x-text="n - 1"></option>
                        </template>
                    </select>
                </div>

                <!-- Search Button -->
                <div class="nzl-field">
                    <button class="nzl-btn nzl-btn-primary nzl-btn-lg" style="width:100%"
                            @click="search()"
                            :disabled="!canSearch || loading">
                        <span x-show="!loading"><?php esc_html_e( 'Search', 'nozule' ); ?></span>
                        <span x-show="loading" class="nzl-spinner"></span>
                    </button>
                </div>
            </div>

            <!-- Nights display -->
            <template x-if="nights > 0">
                <p style="text-align:center; margin-top:0.75rem; color:#6b7280; font-size:0.875rem">
                    <span x-text="nights"></span> <?php esc_html_e( 'night(s)', 'nozule' ); ?>
                </p>
            </template>
        </div>
    </div>

    <!-- Error Message -->
    <template x-if="error">
        <div class="nzl-card" style="margin-top:1rem; border-color:#ef4444;">
            <div class="nzl-card-body" style="color:#ef4444;">
                <p x-text="error"></p>
            </div>
        </div>
    </template>

    <!-- Results -->
    <template x-if="results && results.length > 0">
        <div style="margin-top:1.5rem;">
            <template x-for="result in results" :key="result.room_type.id">
                <div class="nzl-card nzl-room-card" style="margin-bottom:1rem;">
                    <div class="nzl-card-body" style="display:flex; gap:1.5rem; flex-wrap:wrap; align-items:center;">
                        <div style="flex:1; min-width:200px;">
                            <h3 style="font-size:1.125rem; font-weight:600; margin:0 0 0.5rem 0;" x-text="result.room_type.name"></h3>
                            <p style="font-size:0.875rem; color:#6b7280; margin:0 0 0.5rem 0;" x-text="result.room_type.description"></p>
                            <p style="font-size:0.75rem; color:#6b7280;">
                                <span x-text="result.available"></span> <?php esc_html_e( 'available', 'nozule' ); ?>
                                &middot;
                                <?php esc_html_e( 'Max', 'nozule' ); ?> <span x-text="result.room_type.max_occupancy"></span> <?php esc_html_e( 'guests', 'nozule' ); ?>
                            </p>
                        </div>
                        <div style="text-align:center;">
                            <div class="nzl-room-price" x-text="formatPrice(result.pricing.total)"></div>
                            <p style="font-size:0.75rem; color:#6b7280; margin:0.25rem 0;">
                                <span x-text="formatPrice(result.pricing.nightlyRates ? Object.values(result.pricing.nightlyRates)[0] : 0)"></span>
                                / <?php esc_html_e( 'night', 'nozule' ); ?>
                            </p>
                            <button class="nzl-btn nzl-btn-primary"
                                    @click="selectRoom(result.room_type, result.pricing.ratePlan || null)">
                                <?php esc_html_e( 'Select', 'nozule' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- No Results -->
    <template x-if="results && results.length === 0">
        <div class="nzl-card" style="margin-top:1.5rem;">
            <div class="nzl-card-body" style="text-align:center; padding:2rem; color:#6b7280;">
                <p><?php esc_html_e( 'No rooms available for the selected dates.', 'nozule' ); ?></p>
            </div>
        </div>
    </template>
</div>
