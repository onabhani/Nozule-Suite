<?php
/**
 * Template: Admin Settings
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlSettings">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Settings', 'nozule' ); ?></h1>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <template x-if="!loading">
        <div>
            <!-- Tabs -->
            <div class="nzl-tabs" style="margin-bottom:1rem;">
                <button class="nzl-tab" :class="{'active': activeTab === 'general'}" @click="activeTab = 'general'">
                    <?php esc_html_e( 'General', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'currency'}" @click="activeTab = 'currency'">
                    <?php esc_html_e( 'Currency', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'booking'}" @click="activeTab = 'booking'">
                    <?php esc_html_e( 'Booking', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'notifications'}" @click="activeTab = 'notifications'">
                    <?php esc_html_e( 'Notifications', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'policies'}" @click="activeTab = 'policies'">
                    <?php esc_html_e( 'Policies', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'integrations'}" @click="activeTab = 'integrations'">
                    <?php esc_html_e( 'Integrations', 'nozule' ); ?>
                </button>
                <button class="nzl-tab" :class="{'active': activeTab === 'compliance'}" @click="activeTab = 'compliance'"
                        x-show="countryProfile && countryProfile.features && (countryProfile.features.zatca || countryProfile.features.shomos)">
                    <?php esc_html_e( 'Compliance', 'nozule' ); ?>
                </button>
            </div>

            <!-- General -->
            <template x-if="activeTab === 'general'">
                <div>
                    <!-- Country Selector -->
                    <div class="nzl-card" style="margin-bottom:1rem; background:#f0f9ff; border-color:#bae6fd;">
                        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                            <div style="flex:1; min-width:200px;">
                                <label class="nzl-label" style="font-weight:600; color:#0369a1; margin-bottom:0.5rem;"><?php esc_html_e( 'Operating Country', 'nozule' ); ?></label>
                                <select x-model="settings.general.operating_country" class="nzl-input" @change="onCountryChange()" style="max-width:300px;">
                                    <option value=""><?php esc_html_e( '-- Select Country --', 'nozule' ); ?></option>
                                    <option value="SY"><?php esc_html_e( 'Syria', 'nozule' ); ?> (سوريا)</option>
                                    <option value="SA"><?php esc_html_e( 'Saudi Arabia', 'nozule' ); ?> (المملكة العربية السعودية)</option>
                                </select>
                                <p style="font-size:0.75rem; color:#0284c7; margin-top:0.25rem;"><?php esc_html_e( 'Sets default currency, timezone, taxes, and feature visibility.', 'nozule' ); ?></p>
                            </div>
                            <div x-show="settings.general.operating_country">
                                <button class="nzl-btn nzl-btn-primary" @click="applyCountryDefaults()" :disabled="applyingProfile">
                                    <span x-show="!applyingProfile"><?php esc_html_e( 'Apply Country Defaults', 'nozule' ); ?></span>
                                    <span x-show="applyingProfile"><?php esc_html_e( 'Applying...', 'nozule' ); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="nzl-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'General Settings', 'nozule' ); ?></h2>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Hotel Name', 'nozule' ); ?></label>
                            <input type="text" x-model="settings.general.hotel_name" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Hotel Email', 'nozule' ); ?></label>
                            <input type="email" x-model="settings.general.hotel_email" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Hotel Phone', 'nozule' ); ?></label>
                            <input type="tel" x-model="settings.general.hotel_phone" class="nzl-input">
                        </div>
                        <div class="nzl-form-row" style="grid-column: span 2;">
                            <label class="nzl-label"><?php esc_html_e( 'Hotel Address', 'nozule' ); ?></label>
                            <textarea x-model="settings.general.hotel_address" rows="3" class="nzl-input"></textarea>
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Timezone', 'nozule' ); ?></label>
                            <select x-model="settings.general.timezone" class="nzl-input">
                                <option value=""><?php esc_html_e( '-- Select Timezone --', 'nozule' ); ?></option>
                                <optgroup label="<?php esc_attr_e( 'Middle East', 'nozule' ); ?>">
                                    <option value="Asia/Damascus">Asia/Damascus (UTC+3)</option>
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
                                <optgroup label="<?php esc_attr_e( 'Europe', 'nozule' ); ?>">
                                    <option value="Europe/London">Europe/London (UTC+0)</option>
                                    <option value="Europe/Paris">Europe/Paris (UTC+1)</option>
                                    <option value="Europe/Berlin">Europe/Berlin (UTC+1)</option>
                                    <option value="Europe/Madrid">Europe/Madrid (UTC+1)</option>
                                    <option value="Europe/Rome">Europe/Rome (UTC+1)</option>
                                    <option value="Europe/Istanbul">Europe/Istanbul (UTC+3)</option>
                                    <option value="Europe/Moscow">Europe/Moscow (UTC+3)</option>
                                    <option value="Europe/Athens">Europe/Athens (UTC+2)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Asia & Pacific', 'nozule' ); ?>">
                                    <option value="Asia/Karachi">Asia/Karachi (UTC+5)</option>
                                    <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                                    <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
                                    <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                                    <option value="Asia/Shanghai">Asia/Shanghai (UTC+8)</option>
                                    <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
                                    <option value="Australia/Sydney">Australia/Sydney (UTC+11)</option>
                                    <option value="Pacific/Auckland">Pacific/Auckland (UTC+13)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Americas', 'nozule' ); ?>">
                                    <option value="America/New_York">America/New_York (UTC-5)</option>
                                    <option value="America/Chicago">America/Chicago (UTC-6)</option>
                                    <option value="America/Denver">America/Denver (UTC-7)</option>
                                    <option value="America/Los_Angeles">America/Los_Angeles (UTC-8)</option>
                                    <option value="America/Toronto">America/Toronto (UTC-5)</option>
                                    <option value="America/Sao_Paulo">America/Sao_Paulo (UTC-3)</option>
                                    <option value="America/Mexico_City">America/Mexico_City (UTC-6)</option>
                                    <option value="America/Argentina/Buenos_Aires">America/Buenos_Aires (UTC-3)</option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e( 'Africa', 'nozule' ); ?>">
                                    <option value="Africa/Casablanca">Africa/Casablanca (UTC+1)</option>
                                    <option value="Africa/Algiers">Africa/Algiers (UTC+1)</option>
                                    <option value="Africa/Tunis">Africa/Tunis (UTC+1)</option>
                                    <option value="Africa/Nairobi">Africa/Nairobi (UTC+3)</option>
                                    <option value="Africa/Johannesburg">Africa/Johannesburg (UTC+2)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Check-in Time', 'nozule' ); ?></label>
                            <input type="time" x-model="settings.general.check_in_time" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Check-out Time', 'nozule' ); ?></label>
                            <input type="time" x-model="settings.general.check_out_time" class="nzl-input">
                        </div>
                    </div>
                    </div>
                </div>
            </template>

            <!-- Currency -->
            <template x-if="activeTab === 'currency'">
                <div class="nzl-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Currency Settings', 'nozule' ); ?></h2>
                    <div class="nzl-form-grid" style="max-width:500px;">
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Default Currency', 'nozule' ); ?></label>
                            <select x-model="settings.currency.default" class="nzl-input" @change="onCurrencyChange()">
                                <option value="SYP">SYP - <?php esc_html_e( 'Syrian Pound', 'nozule' ); ?></option>
                                <option value="SAR">SAR - <?php esc_html_e( 'Saudi Riyal', 'nozule' ); ?></option>
                                <option value="AED">AED - <?php esc_html_e( 'UAE Dirham', 'nozule' ); ?></option>
                                <option value="QAR">QAR - <?php esc_html_e( 'Qatari Riyal', 'nozule' ); ?></option>
                                <option value="KWD">KWD - <?php esc_html_e( 'Kuwaiti Dinar', 'nozule' ); ?></option>
                                <option value="BHD">BHD - <?php esc_html_e( 'Bahraini Dinar', 'nozule' ); ?></option>
                                <option value="OMR">OMR - <?php esc_html_e( 'Omani Rial', 'nozule' ); ?></option>
                                <option value="EGP">EGP - <?php esc_html_e( 'Egyptian Pound', 'nozule' ); ?></option>
                                <option value="JOD">JOD - <?php esc_html_e( 'Jordanian Dinar', 'nozule' ); ?></option>
                                <option value="USD">USD - <?php esc_html_e( 'US Dollar', 'nozule' ); ?></option>
                                <option value="EUR">EUR - <?php esc_html_e( 'Euro', 'nozule' ); ?></option>
                                <option value="GBP">GBP - <?php esc_html_e( 'British Pound', 'nozule' ); ?></option>
                                <option value="TRY">TRY - <?php esc_html_e( 'Turkish Lira', 'nozule' ); ?></option>
                                <option value="MAD">MAD - <?php esc_html_e( 'Moroccan Dirham', 'nozule' ); ?></option>
                                <option value="INR">INR - <?php esc_html_e( 'Indian Rupee', 'nozule' ); ?></option>
                                <option value="CNY">CNY - <?php esc_html_e( 'Chinese Yuan', 'nozule' ); ?></option>
                                <option value="JPY">JPY - <?php esc_html_e( 'Japanese Yen', 'nozule' ); ?></option>
                                <option value="CHF">CHF - <?php esc_html_e( 'Swiss Franc', 'nozule' ); ?></option>
                                <option value="CAD">CAD - <?php esc_html_e( 'Canadian Dollar', 'nozule' ); ?></option>
                                <option value="AUD">AUD - <?php esc_html_e( 'Australian Dollar', 'nozule' ); ?></option>
                                <option value="BRL">BRL - <?php esc_html_e( 'Brazilian Real', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Currency Symbol', 'nozule' ); ?></label>
                            <input type="text" x-model="settings.currency.symbol" class="nzl-input" readonly>
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Symbol Position', 'nozule' ); ?></label>
                            <select x-model="settings.currency.position" class="nzl-input">
                                <option value="before"><?php esc_html_e( 'Before amount ($100)', 'nozule' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After amount (100$)', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Booking -->
            <template x-if="activeTab === 'booking'">
                <div class="nzl-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Booking Settings', 'nozule' ); ?></h2>
                    <div class="nzl-form-grid" style="max-width:500px;">
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Minimum Stay (nights)', 'nozule' ); ?></label>
                            <input type="number" x-model="settings.booking.min_stay" min="1" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Maximum Stay (nights)', 'nozule' ); ?></label>
                            <input type="number" x-model="settings.booking.max_stay" min="1" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Advance Booking (days)', 'nozule' ); ?></label>
                            <input type="number" x-model="settings.booking.advance_days" min="0" class="nzl-input">
                        </div>
                        <div class="nzl-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.booking.auto_confirm" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Auto-confirm bookings', 'nozule' ); ?>
                            </label>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Notifications -->
            <template x-if="activeTab === 'notifications'">
                <div class="nzl-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Notification Settings', 'nozule' ); ?></h2>
                    <div class="nzl-form-grid" style="max-width:500px;">
                        <div class="nzl-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.admin_new_booking" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Email admin on new booking', 'nozule' ); ?>
                            </label>
                        </div>
                        <div class="nzl-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.admin_cancellation" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Email admin on cancellation', 'nozule' ); ?>
                            </label>
                        </div>
                        <div class="nzl-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.guest_confirmation" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Send booking confirmation to guest', 'nozule' ); ?>
                            </label>
                        </div>
                        <div class="nzl-form-row">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="settings.notifications.guest_reminder" style="border-radius:0.25rem;">
                                <?php esc_html_e( 'Send pre-arrival reminder to guest', 'nozule' ); ?>
                            </label>
                        </div>
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Reminder Days Before Arrival', 'nozule' ); ?></label>
                            <input type="number" x-model="settings.notifications.reminder_days" min="1" class="nzl-input">
                        </div>
                    </div>
                </div>
            </template>

            <!-- Policies -->
            <template x-if="activeTab === 'policies'">
                <div class="nzl-card">
                    <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Cancellation & Policies', 'nozule' ); ?></h2>
                    <div class="nzl-form-grid" style="max-width:600px;">
                        <div class="nzl-form-row">
                            <label class="nzl-label"><?php esc_html_e( 'Free Cancellation Hours', 'nozule' ); ?></label>
                            <input type="number" x-model="settings.policies.free_cancellation_hours" min="0" class="nzl-input">
                        </div>
                        <div class="nzl-form-row" style="grid-column: span 2;">
                            <label class="nzl-label"><?php esc_html_e( 'Cancellation Policy Text', 'nozule' ); ?></label>
                            <textarea x-model="settings.policies.cancellation_text" rows="4" class="nzl-input"></textarea>
                        </div>
                        <div class="nzl-form-row" style="grid-column: span 2;">
                            <label class="nzl-label"><?php esc_html_e( 'Terms & Conditions', 'nozule' ); ?></label>
                            <textarea x-model="settings.policies.terms" rows="6" class="nzl-input"></textarea>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Integrations -->
            <template x-if="activeTab === 'integrations'">
                <div>
                    <!-- Enable / Provider -->
                    <div class="nzl-card" style="margin-bottom:1rem;">
                        <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'ERP / CRM Integration', 'nozule' ); ?></h2>
                        <div class="nzl-form-grid" style="max-width:600px;">
                            <div class="nzl-form-row" style="grid-column: span 2;">
                                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                    <input type="checkbox" x-model="settings.integrations.enabled" style="border-radius:0.25rem;">
                                    <?php esc_html_e( 'Enable external integrations', 'nozule' ); ?>
                                </label>
                            </div>
                            <div class="nzl-form-row" style="grid-column: span 2;">
                                <label class="nzl-label"><?php esc_html_e( 'Integration Provider', 'nozule' ); ?></label>
                                <select x-model="settings.integrations.provider" class="nzl-input" style="max-width:300px;">
                                    <option value="none"><?php esc_html_e( '-- None --', 'nozule' ); ?></option>
                                    <option value="odoo"><?php esc_html_e( 'Odoo ERP', 'nozule' ); ?></option>
                                    <option value="webhook"><?php esc_html_e( 'Custom Webhook', 'nozule' ); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Odoo Settings -->
                    <template x-if="settings.integrations.provider === 'odoo'">
                        <div class="nzl-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Odoo Connection', 'nozule' ); ?></h2>
                            <div class="nzl-form-grid" style="max-width:600px;">
                                <div class="nzl-form-row" style="grid-column: span 2;">
                                    <label class="nzl-label"><?php esc_html_e( 'Odoo Server URL', 'nozule' ); ?></label>
                                    <input type="url" x-model="settings.integrations.odoo_url" class="nzl-input" placeholder="https://mycompany.odoo.com">
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Database Name', 'nozule' ); ?></label>
                                    <input type="text" x-model="settings.integrations.odoo_database" class="nzl-input">
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Username', 'nozule' ); ?></label>
                                    <input type="text" x-model="settings.integrations.odoo_username" class="nzl-input">
                                </div>
                                <div class="nzl-form-row" style="grid-column: span 2;">
                                    <label class="nzl-label"><?php esc_html_e( 'API Key', 'nozule' ); ?></label>
                                    <input type="password" x-model="settings.integrations.odoo_api_key" class="nzl-input" style="max-width:400px;">
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Generate from Odoo: Settings > Users > API Keys', 'nozule' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Webhook Settings -->
                    <template x-if="settings.integrations.provider === 'webhook'">
                        <div class="nzl-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Webhook Configuration', 'nozule' ); ?></h2>
                            <div class="nzl-form-grid" style="max-width:600px;">
                                <div class="nzl-form-row" style="grid-column: span 2;">
                                    <label class="nzl-label"><?php esc_html_e( 'Webhook URL', 'nozule' ); ?></label>
                                    <input type="url" x-model="settings.integrations.webhook_url" class="nzl-input" placeholder="https://example.com/webhook">
                                </div>
                                <div class="nzl-form-row" style="grid-column: span 2;">
                                    <label class="nzl-label"><?php esc_html_e( 'Signing Secret', 'nozule' ); ?></label>
                                    <input type="password" x-model="settings.integrations.webhook_secret" class="nzl-input" style="max-width:400px;">
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Used to sign payloads with HMAC-SHA256. Leave empty to skip signing.', 'nozule' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Sync Options -->
                    <template x-if="settings.integrations.provider !== 'none'">
                        <div class="nzl-card" style="margin-bottom:1rem;">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Sync Options', 'nozule' ); ?></h2>
                            <div class="nzl-form-grid" style="max-width:500px;">
                                <div class="nzl-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_bookings" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync bookings', 'nozule' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Send booking events (created, confirmed, cancelled, check-in/out)', 'nozule' ); ?></p>
                                </div>
                                <div class="nzl-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_contacts" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync contacts / guests', 'nozule' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Create and update guest profiles in the external system', 'nozule' ); ?></p>
                                </div>
                                <div class="nzl-form-row">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                        <input type="checkbox" x-model="settings.integrations.sync_invoices" style="border-radius:0.25rem;">
                                        <?php esc_html_e( 'Sync invoices / payments', 'nozule' ); ?>
                                    </label>
                                    <p style="margin-top:0.25rem; font-size:0.75rem; color:#6b7280;"><?php esc_html_e( 'Send payment records to the external accounting system', 'nozule' ); ?></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Test Connection -->
                    <template x-if="settings.integrations.provider !== 'none'">
                        <div class="nzl-card">
                            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Connection Test', 'nozule' ); ?></h2>
                            <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                                <button class="nzl-btn nzl-btn-secondary" @click="testConnection()" :disabled="testingConnection">
                                    <span x-show="!testingConnection"><?php esc_html_e( 'Test Connection', 'nozule' ); ?></span>
                                    <span x-show="testingConnection"><?php esc_html_e( 'Testing...', 'nozule' ); ?></span>
                                </button>
                                <template x-if="connectionResult">
                                    <span :style="connectionResult.success ? 'color:#16a34a' : 'color:#dc2626'" style="font-size:0.875rem;" x-text="connectionResult.message"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Compliance (SA-specific) -->
            <template x-if="activeTab === 'compliance'">
                <div>
                    <!-- ZATCA E-Invoicing -->
                    <div class="nzl-card" style="margin-bottom:1rem;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:0.25rem;"><?php esc_html_e( 'ZATCA E-Invoicing', 'nozule' ); ?></h2>
                                <p style="font-size:0.875rem; color:#64748b; margin:0;">
                                    <?php esc_html_e( 'Integration with Saudi Arabia\'s Zakat, Tax and Customs Authority for electronic invoicing compliance (Fatoorah).', 'nozule' ); ?>
                                </p>
                            </div>
                            <span style="background:#fef3c7; color:#92400e; font-size:0.75rem; font-weight:600; padding:0.25rem 0.75rem; border-radius:9999px; white-space:nowrap;"><?php esc_html_e( 'Coming Soon', 'nozule' ); ?></span>
                        </div>
                        <div style="margin-top:1rem; padding:1rem; background:#f8fafc; border-radius:0.5rem; border:1px dashed #cbd5e1;">
                            <div class="nzl-form-grid" style="max-width:600px; opacity:0.5; pointer-events:none;">
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'ZATCA Environment', 'nozule' ); ?></label>
                                    <select class="nzl-input" disabled>
                                        <option><?php esc_html_e( 'Sandbox (Testing)', 'nozule' ); ?></option>
                                        <option><?php esc_html_e( 'Production', 'nozule' ); ?></option>
                                    </select>
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Tax Registration Number (TIN)', 'nozule' ); ?></label>
                                    <input type="text" class="nzl-input" disabled placeholder="3XXXXXXXXXX0003">
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Commercial Registration (CR)', 'nozule' ); ?></label>
                                    <input type="text" class="nzl-input" disabled>
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'CSID (Compliance)', 'nozule' ); ?></label>
                                    <input type="text" class="nzl-input" disabled>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shomos Tourism Platform -->
                    <div class="nzl-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:0.25rem;"><?php esc_html_e( 'Shomos Tourism Platform', 'nozule' ); ?></h2>
                                <p style="font-size:0.875rem; color:#64748b; margin:0;">
                                    <?php esc_html_e( 'Integration with Saudi Tourism Authority\'s Shomos platform for hotel registration and guest reporting.', 'nozule' ); ?>
                                </p>
                            </div>
                            <span style="background:#fef3c7; color:#92400e; font-size:0.75rem; font-weight:600; padding:0.25rem 0.75rem; border-radius:9999px; white-space:nowrap;"><?php esc_html_e( 'Coming Soon', 'nozule' ); ?></span>
                        </div>
                        <div style="margin-top:1rem; padding:1rem; background:#f8fafc; border-radius:0.5rem; border:1px dashed #cbd5e1;">
                            <div class="nzl-form-grid" style="max-width:600px; opacity:0.5; pointer-events:none;">
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Shomos License Number', 'nozule' ); ?></label>
                                    <input type="text" class="nzl-input" disabled>
                                </div>
                                <div class="nzl-form-row">
                                    <label class="nzl-label"><?php esc_html_e( 'Establishment ID', 'nozule' ); ?></label>
                                    <input type="text" class="nzl-input" disabled>
                                </div>
                                <div class="nzl-form-row" style="grid-column: span 2;">
                                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:default; opacity:0.5;">
                                        <input type="checkbox" disabled>
                                        <?php esc_html_e( 'Auto-report guest check-ins to Shomos', 'nozule' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Save -->
            <div style="margin-top:1.5rem; display:flex; align-items:center; gap:1rem;">
                <button class="nzl-btn nzl-btn-primary" @click="saveSettings()" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Save Settings', 'nozule' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
                <span x-show="saved" x-transition style="color:#16a34a; font-size:0.875rem;">
                    <?php esc_html_e( 'Settings saved successfully.', 'nozule' ); ?>
                </span>
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
