<?php
/**
 * Template: Admin Settings
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmSettings">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Settings', 'venezia-hotel' ); ?></h1>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <template x-if="!loading">
        <div>
            <!-- Tabs -->
            <div class="vhm-tabs" style="margin-bottom:1rem;">
                <button class="vhm-tab" :class="{'active': activeTab === 'general'}" @click="activeTab = 'general'">
                    <?php esc_html_e( 'General', 'venezia-hotel' ); ?>
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'currency'}" @click="activeTab = 'currency'">
                    <?php esc_html_e( 'Currency', 'venezia-hotel' ); ?>
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'booking'}" @click="activeTab = 'booking'">
                    <?php esc_html_e( 'Booking', 'venezia-hotel' ); ?>
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'notifications'}" @click="activeTab = 'notifications'">
                    <?php esc_html_e( 'Notifications', 'venezia-hotel' ); ?>
                </button>
                <button class="vhm-tab" :class="{'active': activeTab === 'policies'}" @click="activeTab = 'policies'">
                    <?php esc_html_e( 'Policies', 'venezia-hotel' ); ?>
                </button>
            </div>

            <!-- General -->
            <template x-if="activeTab === 'general'">
                <div class="vhm-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'General Settings', 'venezia-hotel' ); ?></h2>
                    <div class="vhm-form-grid">
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Hotel Name', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="settings.general.hotel_name" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Hotel Email', 'venezia-hotel' ); ?></label>
                            <input type="email" x-model="settings.general.hotel_email" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Hotel Phone', 'venezia-hotel' ); ?></label>
                            <input type="tel" x-model="settings.general.hotel_phone" class="vhm-input">
                        </div>
                        <div class="vhm-form-row" style="grid-column: span 2;">
                            <label class="vhm-label"><?php esc_html_e( 'Hotel Address', 'venezia-hotel' ); ?></label>
                            <textarea x-model="settings.general.hotel_address" rows="3" class="vhm-input"></textarea>
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Timezone', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="settings.general.timezone" class="vhm-input" placeholder="<?php esc_attr_e( 'e.g. America/New_York', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Check-in Time', 'venezia-hotel' ); ?></label>
                            <input type="time" x-model="settings.general.check_in_time" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Check-out Time', 'venezia-hotel' ); ?></label>
                            <input type="time" x-model="settings.general.check_out_time" class="vhm-input">
                        </div>
                    </div>
                </div>
            </template>

            <!-- Currency -->
            <template x-if="activeTab === 'currency'">
                <div class="vhm-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Currency Settings', 'venezia-hotel' ); ?></h2>
                    <div class="vhm-form-grid" style="max-width:500px;">
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Default Currency', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="settings.currency.default" class="vhm-input" placeholder="USD">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Currency Symbol', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="settings.currency.symbol" class="vhm-input" placeholder="$">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Symbol Position', 'venezia-hotel' ); ?></label>
                            <select x-model="settings.currency.position" class="vhm-input">
                                <option value="before"><?php esc_html_e( 'Before amount ($100)', 'venezia-hotel' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After amount (100$)', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Booking -->
            <template x-if="activeTab === 'booking'">
                <div class="vhm-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Booking Settings', 'venezia-hotel' ); ?></h2>
                    <div class="vhm-form-grid" style="max-width:500px;">
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Minimum Stay (nights)', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="settings.booking.min_stay" min="1" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Maximum Stay (nights)', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="settings.booking.max_stay" min="1" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Advance Booking (days)', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="settings.booking.advance_days" min="0" class="vhm-input">
                        </div>
                        <div class="vhm-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.booking.auto_confirm" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Auto-confirm bookings', 'venezia-hotel' ); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Notifications -->
            <template x-if="activeTab === 'notifications'">
                <div class="vhm-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Notification Settings', 'venezia-hotel' ); ?></h2>
                    <div class="vhm-form-grid" style="max-width:500px;">
                        <div class="vhm-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.admin_new_booking" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Email admin on new booking', 'venezia-hotel' ); ?>
                            </label>
                        </div>
                        <div class="vhm-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.admin_cancellation" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Email admin on cancellation', 'venezia-hotel' ); ?>
                            </label>
                        </div>
                        <div class="vhm-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.guest_confirmation" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Send booking confirmation to guest', 'venezia-hotel' ); ?>
                            </label>
                        </div>
                        <div class="vhm-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.guest_reminder" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Send pre-arrival reminder to guest', 'venezia-hotel' ); ?>
                            </label>
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Reminder Days Before Arrival', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="settings.notifications.reminder_days" min="1" class="vhm-input">
                        </div>
                    </div>
                </div>
            </template>

            <!-- Policies -->
            <template x-if="activeTab === 'policies'">
                <div class="vhm-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Cancellation & Policies', 'venezia-hotel' ); ?></h2>
                    <div class="vhm-form-grid" style="max-width:600px;">
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Free Cancellation Hours', 'venezia-hotel' ); ?></label>
                            <input type="number" x-model="settings.policies.free_cancellation_hours" min="0" class="vhm-input">
                        </div>
                        <div class="vhm-form-row" style="grid-column: span 2;">
                            <label class="vhm-label"><?php esc_html_e( 'Cancellation Policy Text', 'venezia-hotel' ); ?></label>
                            <textarea x-model="settings.policies.cancellation_text" rows="4" class="vhm-input"></textarea>
                        </div>
                        <div class="vhm-form-row" style="grid-column: span 2;">
                            <label class="vhm-label"><?php esc_html_e( 'Terms & Conditions', 'venezia-hotel' ); ?></label>
                            <textarea x-model="settings.policies.terms" rows="6" class="vhm-input"></textarea>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Save -->
            <div style="margin-top:1.5rem; display:flex; align-items:center; gap:1rem;">
                <button class="vhm-btn vhm-btn-primary" @click="saveSettings()" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Save Settings', 'venezia-hotel' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Saving...', 'venezia-hotel' ); ?></span>
                </button>
                <span x-show="saved" x-transition style="color:#16a34a; font-size:0.875rem;">
                    <?php esc_html_e( 'Settings saved successfully.', 'venezia-hotel' ); ?>
                </span>
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
