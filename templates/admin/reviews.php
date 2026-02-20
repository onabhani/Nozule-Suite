<?php
/**
 * Admin template: Reviews & Reputation — Review Solicitation (NZL-020)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlReviews">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Reviews & Reputation', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Track review solicitation emails and manage reputation settings.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'dashboard'}" @click="switchTab('dashboard')">
            <?php esc_html_e( 'Dashboard', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'settings'}" @click="switchTab('settings')">
            <?php esc_html_e( 'Settings', 'nozule' ); ?>
        </button>
    </div>

    <!-- ═══ Dashboard Tab ═══ -->
    <div x-show="activeTab === 'dashboard'">

        <!-- Stat Cards -->
        <template x-if="loadingStats">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingStats">
            <div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Total Sent', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#1e293b;" x-text="stats.sent + stats.clicked"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Clicked', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#059669;" x-text="stats.clicked"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Click Rate', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#6366f1;" x-text="stats.click_rate + '%'"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Queued', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#f59e0b;" x-text="stats.queued"></div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="nzl-card" style="margin-bottom:1rem;">
                    <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select x-model="filters.status" @change="currentPage=1; loadRequests()" class="nzl-input">
                                <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                                <option value="queued"><?php esc_html_e( 'Queued', 'nozule' ); ?></option>
                                <option value="sent"><?php esc_html_e( 'Sent', 'nozule' ); ?></option>
                                <option value="clicked"><?php esc_html_e( 'Clicked', 'nozule' ); ?></option>
                                <option value="failed"><?php esc_html_e( 'Failed', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:180px;">
                            <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                            <input type="text" x-model="filters.search" @input.debounce.400ms="currentPage=1; loadRequests()" class="nzl-input" placeholder="<?php esc_attr_e( 'Email address...', 'nozule' ); ?>">
                        </div>
                    </div>
                </div>

                <!-- Requests Table -->
                <template x-if="loadingRequests">
                    <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                </template>

                <template x-if="!loadingRequests">
                    <div class="nzl-table-wrap">
                        <table class="nzl-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'ID', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Email', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Booking', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Platform', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Sent At', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Clicked At', 'nozule' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="req in requests" :key="req.id">
                                    <tr>
                                        <td x-text="req.id"></td>
                                        <td dir="ltr" x-text="req.to_email"></td>
                                        <td x-text="'#' + req.booking_id"></td>
                                        <td>
                                            <span style="font-family:monospace; font-size:0.8rem; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="req.review_platform"></span>
                                        </td>
                                        <td>
                                            <span class="nzl-badge"
                                                  :class="{
                                                      'nzl-badge-confirmed': req.status === 'sent' || req.status === 'clicked',
                                                      'nzl-badge-cancelled': req.status === 'failed',
                                                      'nzl-badge-pending': req.status === 'queued'
                                                  }" x-text="statusLabel(req.status)">
                                            </span>
                                        </td>
                                        <td dir="ltr" style="font-size:0.875rem; color:#94a3b8;" x-text="req.sent_at || '—'"></td>
                                        <td dir="ltr" style="font-size:0.875rem; color:#94a3b8;" x-text="req.clicked_at || '—'"></td>
                                    </tr>
                                </template>
                                <template x-if="requests.length === 0">
                                    <tr>
                                        <td colspan="7" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No review requests found.', 'nozule' ); ?></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Pagination -->
                <template x-if="totalPages > 1">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                        <span style="font-size:0.875rem; color:#64748b;">
                            <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="currentPage"></span>
                            <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="totalPages"></span>
                        </span>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="nzl-btn nzl-btn-sm" @click="prevPage()" :disabled="currentPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                            <button class="nzl-btn nzl-btn-sm" @click="nextPage()" :disabled="currentPage >= totalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ═══ Settings Tab ═══ -->
    <div x-show="activeTab === 'settings'">
        <template x-if="loadingSettings">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingSettings">
            <div class="nzl-card" style="max-width:800px;">
                <h3 style="margin:0 0 1rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'Review Solicitation Settings', 'nozule' ); ?></h3>

                <!-- Enable/Disable -->
                <div style="margin-bottom:1rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" x-model="settingsForm.enabled">
                        <?php esc_html_e( 'Enable automatic review request emails', 'nozule' ); ?>
                    </label>
                </div>

                <!-- Delay Hours -->
                <div class="nzl-form-group" style="margin-bottom:1rem;">
                    <label><?php esc_html_e( 'Delay After Checkout (hours)', 'nozule' ); ?></label>
                    <input type="number" x-model="settingsForm.delay_hours" class="nzl-input" min="0" max="168" style="max-width:120px;">
                    <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;">
                        <?php esc_html_e( 'How many hours after checkout before the review email is sent.', 'nozule' ); ?>
                    </p>
                </div>

                <!-- Review URLs -->
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Google Maps Review URL', 'nozule' ); ?></label>
                        <input type="url" x-model="settingsForm.google_review_url" class="nzl-input" dir="ltr" placeholder="https://g.page/r/...">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'TripAdvisor URL', 'nozule' ); ?></label>
                        <input type="url" x-model="settingsForm.tripadvisor_url" class="nzl-input" dir="ltr" placeholder="https://www.tripadvisor.com/...">
                    </div>
                </div>

                <!-- Email Subject bilingual -->
                <h4 style="margin:1.5rem 0 0.75rem; font-size:1rem; font-weight:600; color:#475569;"><?php esc_html_e( 'Email Template', 'nozule' ); ?></h4>

                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Subject (English)', 'nozule' ); ?></label>
                        <input type="text" x-model="settingsForm.email_subject" class="nzl-input" dir="ltr">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Subject (Arabic)', 'nozule' ); ?></label>
                        <input type="text" x-model="settingsForm.email_subject_ar" class="nzl-input" dir="rtl">
                    </div>
                </div>

                <!-- Email Body English -->
                <div class="nzl-form-group" style="margin-top:0.75rem;">
                    <label><?php esc_html_e( 'Body (English)', 'nozule' ); ?></label>
                    <textarea x-model="settingsForm.email_body" class="nzl-input" dir="ltr" rows="8"></textarea>
                    <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;">
                        <?php esc_html_e( 'Variables: {{guest_name}}, {{hotel_name}}, {{booking_number}}, {{google_review_url}}, {{tripadvisor_url}}', 'nozule' ); ?>
                    </p>
                </div>

                <!-- Email Body Arabic -->
                <div class="nzl-form-group" style="margin-top:0.75rem;">
                    <label><?php esc_html_e( 'Body (Arabic)', 'nozule' ); ?></label>
                    <textarea x-model="settingsForm.email_body_ar" class="nzl-input" dir="rtl" rows="8"></textarea>
                </div>

                <!-- Save Button -->
                <div style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
                    <button class="nzl-btn nzl-btn-primary" @click="saveSettings()" :disabled="savingSettings">
                        <span x-show="!savingSettings"><?php esc_html_e( 'Save Settings', 'nozule' ); ?></span>
                        <span x-show="savingSettings"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </template>
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
