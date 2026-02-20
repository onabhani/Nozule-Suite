<?php
/**
 * Admin template: Google Hotel Ads / Metasearch (NZL-016)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlMetasearch">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Metasearch — Google Hotel Ads', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Configure Google Hotel Price Feed, Free Booking Links, and CPC campaigns.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'settings'}" @click="switchTab('settings')">
            <?php esc_html_e( 'Settings', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'feed'}" @click="switchTab('feed')">
            <?php esc_html_e( 'Price Feed', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'cpc'}" @click="switchTab('cpc')">
            <?php esc_html_e( 'CPC Campaigns', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- ═══ Settings Tab ═══ -->
    <div x-show="!loading && activeTab === 'settings'">
        <div class="nzl-card" style="max-width:750px;">
            <h3 style="margin:0 0 1.25rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'Hotel Information', 'nozule' ); ?></h3>

            <div style="margin-bottom:1rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" x-model="settingsForm.enabled">
                    <?php esc_html_e( 'Enable Google Hotel Ads Integration', 'nozule' ); ?>
                </label>
            </div>

            <div class="nzl-form-grid">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Google Hotel Center ID', 'nozule' ); ?> *</label>
                    <input type="text" x-model="settingsForm.hotel_id" class="nzl-input" dir="ltr" placeholder="<?php esc_attr_e( 'e.g. 12345678', 'nozule' ); ?>">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Partner Key', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.partner_key" class="nzl-input" dir="ltr">
                </div>
            </div>

            <div class="nzl-form-grid" style="margin-top:0.75rem;">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Hotel Name (English)', 'nozule' ); ?> *</label>
                    <input type="text" x-model="settingsForm.hotel_name" class="nzl-input" dir="ltr">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Hotel Name (Arabic)', 'nozule' ); ?> *</label>
                    <input type="text" x-model="settingsForm.hotel_name_ar" class="nzl-input" dir="rtl">
                </div>
            </div>

            <div class="nzl-form-group" style="margin-top:0.75rem;">
                <label><?php esc_html_e( 'Landing Page URL', 'nozule' ); ?> *</label>
                <input type="url" x-model="settingsForm.landing_page_url" class="nzl-input" dir="ltr" placeholder="https://yourhotel.com/book">
            </div>

            <div class="nzl-form-grid" style="margin-top:0.75rem;">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Hotel Address', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.hotel_address" class="nzl-input">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'City', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.hotel_city" class="nzl-input">
                </div>
            </div>

            <div class="nzl-form-grid" style="margin-top:0.75rem;">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Country Code', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.hotel_country" class="nzl-input" dir="ltr" placeholder="SY" style="max-width:100px;">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.currency" class="nzl-input" dir="ltr" placeholder="SYP" style="max-width:100px;">
                </div>
            </div>

            <div style="margin-top:1rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" x-model="settingsForm.free_booking_links">
                    <?php esc_html_e( 'Enable Free Booking Links on Google Maps', 'nozule' ); ?>
                </label>
                <p style="font-size:0.75rem; color:#94a3b8; margin:0.25rem 0 0 1.5rem;">
                    <?php esc_html_e( 'Adds JSON-LD structured data to your site for Google to display free booking links.', 'nozule' ); ?>
                </p>
            </div>

            <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                <button class="nzl-btn nzl-btn-primary" @click="saveSettings()" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Save Settings', 'nozule' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ═══ Price Feed Tab ═══ -->
    <div x-show="!loading && activeTab === 'feed'">
        <div class="nzl-card" style="margin-bottom:1.5rem;">
            <h3 style="margin:0 0 0.75rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'Google Hotel Price Feed', 'nozule' ); ?></h3>
            <p style="font-size:0.875rem; color:#64748b; margin-bottom:1rem;">
                <?php esc_html_e( 'Google crawls this URL to get your hotel rates. Share this URL in your Google Hotel Center account.', 'nozule' ); ?>
            </p>

            <div class="nzl-form-group">
                <label><?php esc_html_e( 'Feed URL', 'nozule' ); ?></label>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" class="nzl-input" dir="ltr" :value="feedUrl" readonly style="flex:1; background:#f8fafc;">
                    <button class="nzl-btn" @click="copyFeedUrl()"><?php esc_html_e( 'Copy', 'nozule' ); ?></button>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; margin-top:1rem;">
                <button class="nzl-btn nzl-btn-primary" @click="testFeed()" :disabled="testingFeed">
                    <span x-show="!testingFeed"><?php esc_html_e( 'Test Feed', 'nozule' ); ?></span>
                    <span x-show="testingFeed"><?php esc_html_e( 'Testing...', 'nozule' ); ?></span>
                </button>
                <button class="nzl-btn" @click="previewFeed()" :disabled="previewingFeed">
                    <span x-show="!previewingFeed"><?php esc_html_e( 'Preview XML', 'nozule' ); ?></span>
                    <span x-show="previewingFeed"><?php esc_html_e( 'Loading...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>

        <!-- Feed Test Results -->
        <template x-if="feedTestResult">
            <div class="nzl-card" style="margin-bottom:1rem;">
                <h4 style="margin:0 0 0.75rem;"><?php esc_html_e( 'Feed Test Results', 'nozule' ); ?></h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:1rem;">
                    <div>
                        <div style="font-size:0.75rem; color:#94a3b8;"><?php esc_html_e( 'Room Types', 'nozule' ); ?></div>
                        <div style="font-size:1.5rem; font-weight:700;" x-text="feedTestResult.room_count || 0"></div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94a3b8;"><?php esc_html_e( 'Rate Plans', 'nozule' ); ?></div>
                        <div style="font-size:1.5rem; font-weight:700;" x-text="feedTestResult.rate_count || 0"></div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94a3b8;"><?php esc_html_e( 'Date Range', 'nozule' ); ?></div>
                        <div style="font-size:0.875rem; font-weight:600;" x-text="(feedTestResult.date_from || '') + ' → ' + (feedTestResult.date_to || '')"></div>
                    </div>
                    <div>
                        <div style="font-size:0.75rem; color:#94a3b8;"><?php esc_html_e( 'Total Results', 'nozule' ); ?></div>
                        <div style="font-size:1.5rem; font-weight:700;" x-text="feedTestResult.result_count || 0"></div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Feed XML Preview -->
        <template x-if="feedPreviewXml">
            <div class="nzl-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <h4 style="margin:0;"><?php esc_html_e( 'XML Preview (first 10 results)', 'nozule' ); ?></h4>
                    <button class="nzl-btn nzl-btn-sm" @click="feedPreviewXml = null">&times; <?php esc_html_e( 'Close', 'nozule' ); ?></button>
                </div>
                <pre style="background:#0f172a; color:#e2e8f0; padding:1rem; border-radius:0.5rem; font-size:0.8rem; overflow-x:auto; max-height:400px; direction:ltr;" x-text="feedPreviewXml"></pre>
            </div>
        </template>
    </div>

    <!-- ═══ CPC Campaigns Tab ═══ -->
    <div x-show="!loading && activeTab === 'cpc'">
        <div class="nzl-card" style="max-width:600px;">
            <h3 style="margin:0 0 1rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'CPC Campaign Settings', 'nozule' ); ?></h3>

            <div style="margin-bottom:1rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                    <input type="checkbox" x-model="settingsForm.cpc_enabled">
                    <?php esc_html_e( 'Enable CPC Campaigns', 'nozule' ); ?>
                </label>
                <p style="font-size:0.75rem; color:#94a3b8; margin:0.25rem 0 0 1.5rem;">
                    <?php esc_html_e( 'Pay-per-click advertising on Google Hotel Ads. Requires a Google Ads account linked to Hotel Center.', 'nozule' ); ?>
                </p>
            </div>

            <div class="nzl-form-grid" x-show="settingsForm.cpc_enabled">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Daily Budget', 'nozule' ); ?></label>
                    <input type="number" step="0.01" min="0" x-model.number="settingsForm.cpc_budget" class="nzl-input" dir="ltr">
                    <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;"><?php esc_html_e( 'In your account currency (USD/SYP).', 'nozule' ); ?></p>
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Bid Strategy', 'nozule' ); ?></label>
                    <select x-model="settingsForm.cpc_bid_type" class="nzl-input">
                        <option value="manual"><?php esc_html_e( 'Manual CPC', 'nozule' ); ?></option>
                        <option value="auto"><?php esc_html_e( 'Automated (Maximize Clicks)', 'nozule' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                <button class="nzl-btn nzl-btn-primary" @click="saveSettings()" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Save Settings', 'nozule' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>
    </div>
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
