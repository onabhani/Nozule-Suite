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
                <button class="vhm-tab" :class="{'active': activeTab === 'integrations'}" @click="activeTab = 'integrations'">
                    <?php esc_html_e( 'Integrations', 'venezia-hotel' ); ?>
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
                            <select x-model="settings.general.timezone" class="vhm-input">
                                <option value=""><?php esc_html_e( '-- Select Timezone --', 'venezia-hotel' ); ?></option>
                                <optgroup label="<?php esc_attr_e( 'Middle East', 'venezia-hotel' ); ?>">
                                    <option value="Asia/Riyadh">Asia/Riyadh (UTC+3)</option>
                                    <option value="Asia/Dubai">Asia/Dubai (UTC+4)</option>
                                    <option value="Asia/Qatar">Asia/Qatar (UTC+3)</option>
                                    <option value="Asia/Kuwait">Asia/Kuwait (UTC+3)</option>
                                    <option value="Asia/Bahrain">Asia/Bahrain (UTC+3)</option>
                                    <option value="Asia/Muscat">Asia/Muscat (UTC+4)</option>
                                    <option value="Asia/Baghdad">Asia/Baghdad (UTC+3)</option>
                                    <option value="Asia/Amman">Asia/Amman (UTC+3)</option>
                                    <option value="Asia/Beirut">Asia/Beirut (UTC+2)</option>
                                    <option value="Asia/Jerusalem">Asia/Jerusalem (UTC+2)</option>
                                    <option value="Africa/Cairo">Africa/Cairo (UTC+2)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Europe', 'venezia-hotel' ); ?>">
                                    <option value="Europe/London">Europe/London (UTC+0)</option>
                                    <option value="Europe/Paris">Europe/Paris (UTC+1)</option>
                                    <option value="Europe/Berlin">Europe/Berlin (UTC+1)</option>
                                    <option value="Europe/Madrid">Europe/Madrid (UTC+1)</option>
                                    <option value="Europe/Rome">Europe/Rome (UTC+1)</option>
                                    <option value="Europe/Istanbul">Europe/Istanbul (UTC+3)</option>
                                    <option value="Europe/Moscow">Europe/Moscow (UTC+3)</option>
                                    <option value="Europe/Athens">Europe/Athens (UTC+2)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Asia & Pacific', 'venezia-hotel' ); ?>">
                                    <option value="Asia/Karachi">Asia/Karachi (UTC+5)</option>
                                    <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                                    <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
                                    <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                                    <option value="Asia/Shanghai">Asia/Shanghai (UTC+8)</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
                                    <option value="Australia/Sydney">Australia/Sydney (UTC+11)</option>
                                    <option value="Pacific/Auckland">Pacific/Auckland (UTC+13)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Americas', 'venezia-hotel' ); ?>">
                                    <option value="America/New_York">America/New_York (UTC-5)</option>
                                    <option value="America/Chicago">America/Chicago (UTC-6)</option>
                                    <option value="America/Denver">America/Denver (UTC-7)</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles (UTC-8)</option>
                                    <option value="America/Toronto">America/Toronto (UTC-5)</option>
                                    <option value="America/Sao_Paulo">America/Sao_Paulo (UTC-3)</option>
                                    <option value="America/Mexico_City">America/Mexico_City (UTC-6)</option>
                                    <option value="America/Argentina/Buenos_Aires">America/Buenos_Aires (UTC-3)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Africa', 'venezia-hotel' ); ?>">
                                    <option value="Africa/Casablanca">Africa/Casablanca (UTC+1)</option>
                                    <option value="Africa/Algiers">Africa/Algiers (UTC+1)</option>
                                    <option value="Africa/Tunis">Africa/Tunis (UTC+1)</option>
                                    <option value="Africa/Nairobi">Africa/Nairobi (UTC+3)</option>
                                    <option value="Africa/Johannesburg">Africa/Johannesburg (UTC+2)</option>
                                </optgroup>
                            </select>
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
                            <select x-model="settings.currency.default" class="vhm-input" @change="onCurrencyChange()">
                                <option value="SAR">SAR - <?php esc_html_e( 'Saudi Riyal', 'venezia-hotel' ); ?></option>
                                <option value="AED">AED - <?php esc_html_e( 'UAE Dirham', 'venezia-hotel' ); ?></option>
                                <option value="QAR">QAR - <?php esc_html_e( 'Qatari Riyal', 'venezia-hotel' ); ?></option>
                                <option value="KWD">KWD - <?php esc_html_e( 'Kuwaiti Dinar', 'venezia-hotel' ); ?></option>
                                <option value="BHD">BHD - <?php esc_html_e( 'Bahraini Dinar', 'venezia-hotel' ); ?></option>
                                <option value="OMR">OMR - <?php esc_html_e( 'Omani Rial', 'venezia-hotel' ); ?></option>
                                <option value="EGP">EGP - <?php esc_html_e( 'Egyptian Pound', 'venezia-hotel' ); ?></option>
                                <option value="JOD">JOD - <?php esc_html_e( 'Jordanian Dinar', 'venezia-hotel' ); ?></option>
                                <option value="USD">USD - <?php esc_html_e( 'US Dollar', 'venezia-hotel' ); ?></option>
                                <option value="EUR">EUR - <?php esc_html_e( 'Euro', 'venezia-hotel' ); ?></option>
                                <option value="GBP">GBP - <?php esc_html_e( 'British Pound', 'venezia-hotel' ); ?></option>
                                <option value="TRY">TRY - <?php esc_html_e( 'Turkish Lira', 'venezia-hotel' ); ?></option>
                                <option value="MAD">MAD - <?php esc_html_e( 'Moroccan Dirham', 'venezia-hotel' ); ?></option>
                                <option value="INR">INR - <?php esc_html_e( 'Indian Rupee', 'venezia-hotel' ); ?></option>
                                <option value="CNY">CNY - <?php esc_html_e( 'Chinese Yuan', 'venezia-hotel' ); ?></option>
                                <option value="JPY">JPY - <?php esc_html_e( 'Japanese Yen', 'venezia-hotel' ); ?></option>
                                <option value="CHF">CHF - <?php esc_html_e( 'Swiss Franc', 'venezia-hotel' ); ?></option>
                                <option value="CAD">CAD - <?php esc_html_e( 'Canadian Dollar', 'venezia-hotel' ); ?></option>
                                <option value="AUD">AUD - <?php esc_html_e( 'Australian Dollar', 'venezia-hotel' ); ?></option>
                                <option value="BRL">BRL - <?php esc_html_e( 'Brazilian Real', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div class="vhm-form-row">
                            <label class="vhm-label"><?php esc_html_e( 'Currency Symbol', 'venezia-hotel' ); ?></label>
                            <input type="text" x-model="settings.currency.symbol" class="vhm-input" readonly>
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

            <!-- Integrations -->
            <template x-if="activeTab === 'integrations'">
                <div>
                    <!-- Enable / Provider -->
                    <div class="vhm-card" style="margin-bottom:1rem;">
                        <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'ERP / CRM Integration', 'venezia-hotel' ); ?></h2>
                        <div class="vhm-form-grid" style="max-width:600px;">
                            <div class="vhm-form-row" style="grid-column: span 2;">
                                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                    <input type="checkbox" x-model="settings.integrations.enabled" style="border-radius:0.25rem;">
                                    <?php esc_html_e( 'Enable external integrations', 'venezia-hotel' ); ?>
                                </label>
                            </div>
                            <div class="vhm-form-row" style="grid-column: span 2;">
                                <label class="vhm-label"><?php esc_html_e( 'Integration Provider', 'venezia-hotel' ); ?></label>
                                <select x-model="settings.integrations.provider" class="vhm-input" style="max-width:300px;">
                                    <option value="none"><?php esc_html_e( '-- None --', 'venezia-hotel' ); ?></option>
                                    <option value="odoo"><?php esc_html_e( 'Odoo ERP', 'venezia-hotel' ); ?></option>
                                    <option value="webhook"><?php esc_html_e( 'Custom Webhook', 'venezia-hotel' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Odoo Settings -->
                    <template x-if="settings.integrations.provider === 'odoo'">
                        <div class="vhm-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Odoo Connection', 'venezia-hotel' ); ?></h2>
                            <div class="vhm-form-grid" style="max-width:600px;">
                                <div class="vhm-form-row" style="grid-column: span 2;">
                                    <label class="vhm-label"><?php esc_html_e( 'Odoo Server URL', 'venezia-hotel' ); ?></label>
                                    <input type="url" x-model="settings.integrations.odoo_url" class="vhm-input" placeholder="https://mycompany.odoo.com">
                                </div>
                                <div class="vhm-form-row">
                                    <label class="vhm-label"><?php esc_html_e( 'Database Name', 'venezia-hotel' ); ?></label>
                                    <input type="text" x-model="settings.integrations.odoo_database" class="vhm-input">
                                </div>
                                <div class="vhm-form-row">
                                    <label class="vhm-label"><?php esc_html_e( 'Username', 'venezia-hotel' ); ?></label>
                                    <input type="text" x-model="settings.integrations.odoo_username" class="vhm-input">
                                </div>
                                <div class="vhm-form-row" style="grid-column: span 2;">
                                    <label class="vhm-label"><?php esc_html_e( 'API Key', 'venezia-hotel' ); ?></label>
                                    <input type="password" x-model="settings.integrations.odoo_api_key" class="vhm-input" style="max-width:400px;">
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Generate from Odoo: Settings > Users > API Keys', 'venezia-hotel' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Webhook Settings -->
                    <template x-if="settings.integrations.provider === 'webhook'">
                        <div class="vhm-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Webhook Configuration', 'venezia-hotel' ); ?></h2>
                            <div class="vhm-form-grid" style="max-width:600px;">
                                <div class="vhm-form-row" style="grid-column: span 2;">
                                    <label class="vhm-label"><?php esc_html_e( 'Webhook URL', 'venezia-hotel' ); ?></label>
                                    <input type="url" x-model="settings.integrations.webhook_url" class="vhm-input" placeholder="https://example.com/webhook">
                                </div>
                                <div class="vhm-form-row" style="grid-column: span 2;">
                                    <label class="vhm-label"><?php esc_html_e( 'Signing Secret', 'venezia-hotel' ); ?></label>
                                    <input type="password" x-model="settings.integrations.webhook_secret" class="vhm-input" style="max-width:400px;">
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Used to sign payloads with HMAC-SHA256. Leave empty to skip signing.', 'venezia-hotel' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Sync Options -->
                    <template x-if="settings.integrations.provider !== 'none'">
                        <div class="vhm-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Sync Options', 'venezia-hotel' ); ?></h2>
                            <div class="vhm-form-grid" style="max-width:500px;">
                                <div class="vhm-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_bookings" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync bookings', 'venezia-hotel' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Send booking events (created, confirmed, cancelled, check-in/out)', 'venezia-hotel' ); ?></p>
                                </div>
                                <div class="vhm-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_contacts" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync contacts / guests', 'venezia-hotel' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Create and update guest profiles in the external system', 'venezia-hotel' ); ?></p>
                                </div>
                                <div class="vhm-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_invoices" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync invoices / payments', 'venezia-hotel' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Send payment records to the external accounting system', 'venezia-hotel' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Test Connection -->
                    <template x-if="settings.integrations.provider !== 'none'">
                        <div class="vhm-card">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Connection Test', 'venezia-hotel' ); ?></h2>
                            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                                <button class="vhm-btn vhm-btn-secondary" @click="testConnection()" :disabled="testingConnection">
                                    <span x-show="!testingConnection"><?php esc_html_e( 'Test Connection', 'venezia-hotel' ); ?></span>
                                    <span x-show="testingConnection"><?php esc_html_e( 'Testing...', 'venezia-hotel' ); ?></span>
                                </button>
                                <template x-if="connectionResult">
                                    <span :style="connectionResult.success ? 'color:#16a34a' : 'color:#dc2626'" style="font-size:0.875rem;" x-text="connectionResult.message"></span>
                                </template>
                            </div>
                        </div>
                    </template>
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
