<?php
/**
 * Admin template: WhatsApp Messaging — Templates, Message Log & Settings (NZL-023)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlWhatsApp">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'WhatsApp Messaging', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage WhatsApp message templates, view sent messages, and configure API settings.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'templates'}" @click="switchTab('templates')">
            <?php esc_html_e( 'Templates', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'log'}" @click="switchTab('log')">
            <?php esc_html_e( 'Message Log', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'settings'}" @click="switchTab('settings')">
            <?php esc_html_e( 'Settings', 'nozule' ); ?>
        </button>
    </div>

    <!-- ═══ Templates Tab ═══ -->
    <div x-show="activeTab === 'templates'">
        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button class="nzl-btn nzl-btn-primary" @click="openTemplateModal()">
                + <?php esc_html_e( 'New Template', 'nozule' ); ?>
            </button>
        </div>

        <template x-if="loading">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loading">
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Trigger', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Body Preview', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <tr>
                                <td x-text="tpl.name"></td>
                                <td>
                                    <span style="font-family:monospace; font-size:0.8rem; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="tpl.trigger_event || '—'"></span>
                                </td>
                                <td style="font-size:0.875rem; color:#64748b; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="truncateBody(tpl.body)"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="tpl.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="tpl.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editTemplate(tpl)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="testTemplate(tpl.id)"><?php esc_html_e( 'Test', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteTemplate(tpl.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="templates.length === 0">
                            <tr>
                                <td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No WhatsApp templates found.', 'nozule' ); ?></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- ═══ Message Log Tab ═══ -->
    <div x-show="activeTab === 'log'">
        <!-- Log Filters -->
        <div class="nzl-card" style="margin-bottom:1rem;">
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                    <select x-model="logFilters.status" @change="logCurrentPage=1; loadMessageLog()" class="nzl-input">
                        <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                        <option value="sent"><?php esc_html_e( 'Sent', 'nozule' ); ?></option>
                        <option value="delivered"><?php esc_html_e( 'Delivered', 'nozule' ); ?></option>
                        <option value="read"><?php esc_html_e( 'Read', 'nozule' ); ?></option>
                        <option value="failed"><?php esc_html_e( 'Failed', 'nozule' ); ?></option>
                        <option value="queued"><?php esc_html_e( 'Queued', 'nozule' ); ?></option>
                    </select>
                </div>
                <div style="flex:1; min-width:180px;">
                    <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                    <input type="text" x-model="logFilters.search" @input.debounce.400ms="logCurrentPage=1; loadMessageLog()" class="nzl-input" placeholder="<?php esc_attr_e( 'Phone or message...', 'nozule' ); ?>">
                </div>
            </div>
        </div>

        <template x-if="loadingLog">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingLog">
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'To', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Sent At', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Error', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="log in messageLogs" :key="log.id">
                            <tr>
                                <td dir="ltr" x-text="log.to_phone"></td>
                                <td style="font-size:0.875rem; color:#64748b; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="truncateBody(log.body)"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="{
                                              'nzl-badge-confirmed': log.status === 'sent' || log.status === 'delivered' || log.status === 'read',
                                              'nzl-badge-cancelled': log.status === 'failed',
                                              'nzl-badge-pending': log.status === 'queued'
                                          }" x-text="statusLabel(log.status)">
                                    </span>
                                </td>
                                <td dir="ltr" style="font-size:0.875rem; color:#94a3b8;" x-text="log.sent_at || log.created_at"></td>
                                <td style="font-size:0.8rem; color:#ef4444; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" x-text="log.error_message || ''"></td>
                            </tr>
                        </template>
                        <template x-if="messageLogs.length === 0">
                            <tr>
                                <td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No WhatsApp messages sent yet.', 'nozule' ); ?></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Log Pagination -->
        <template x-if="logTotalPages > 1">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                <span style="font-size:0.875rem; color:#64748b;">
                    <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="logCurrentPage"></span>
                    <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="logTotalPages"></span>
                </span>
                <div style="display:flex; gap:0.5rem;">
                    <button class="nzl-btn nzl-btn-sm" @click="logPrevPage()" :disabled="logCurrentPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-sm" @click="logNextPage()" :disabled="logCurrentPage >= logTotalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                </div>
            </div>
        </template>
    </div>

    <!-- ═══ Settings Tab ═══ -->
    <div x-show="activeTab === 'settings'">
        <template x-if="loadingSettings">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingSettings">
            <div class="nzl-card" style="max-width:700px;">
                <h3 style="margin-top:0;"><?php esc_html_e( 'WhatsApp Business API Configuration', 'nozule' ); ?></h3>
                <p style="font-size:0.875rem; color:#64748b; margin-bottom:1.5rem;">
                    <?php esc_html_e( 'Enter your Meta WhatsApp Business API credentials below.', 'nozule' ); ?>
                </p>

                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Phone Number ID', 'nozule' ); ?></label>
                        <input type="text" x-model="settingsForm.phone_number_id" class="nzl-input" dir="ltr" placeholder="123456789012345">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Business ID', 'nozule' ); ?></label>
                        <input type="text" x-model="settingsForm.business_id" class="nzl-input" dir="ltr" placeholder="123456789012345">
                    </div>
                </div>

                <div class="nzl-form-group" style="margin-top:0.75rem;">
                    <label><?php esc_html_e( 'Access Token', 'nozule' ); ?></label>
                    <input type="password" x-model="settingsForm.access_token" class="nzl-input" dir="ltr" placeholder="<?php esc_attr_e( 'Enter access token...', 'nozule' ); ?>">
                    <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;">
                        <?php esc_html_e( 'Leave blank to keep the current token.', 'nozule' ); ?>
                        <span x-show="settingsMaskedToken" x-text="'<?php echo esc_js( __( 'Current:', 'nozule' ) ); ?> ' + settingsMaskedToken" style="font-family:monospace;"></span>
                    </p>
                </div>

                <div class="nzl-form-group" style="margin-top:0.75rem;">
                    <label><?php esc_html_e( 'API Version', 'nozule' ); ?></label>
                    <input type="text" x-model="settingsForm.api_version" class="nzl-input" dir="ltr" placeholder="v21.0" style="max-width:150px;">
                </div>

                <div style="margin-top:1rem;">
                    <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                        <input type="checkbox" x-model="settingsForm.enabled" true-value="1" false-value="0">
                        <?php esc_html_e( 'Enable WhatsApp Messaging', 'nozule' ); ?>
                    </label>
                </div>

                <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                    <button class="nzl-btn nzl-btn-primary" @click="saveSettings()" :disabled="savingSettings">
                        <span x-show="!savingSettings"><?php esc_html_e( 'Save Settings', 'nozule' ); ?></span>
                        <span x-show="savingSettings"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                    <button class="nzl-btn" @click="testConnection()" :disabled="testingConnection">
                        <span x-show="!testingConnection"><?php esc_html_e( 'Test Connection', 'nozule' ); ?></span>
                        <span x-show="testingConnection"><?php esc_html_e( 'Testing...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <!-- ═══ Template Modal ═══ -->
    <template x-if="showTemplateModal">
        <div class="nzl-modal-overlay" @click.self="showTemplateModal = false">
            <div class="nzl-modal" style="max-width:760px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingTemplateId ? '<?php echo esc_js( __( 'Edit Template', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Template', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showTemplateModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Name & Slug -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name *', 'nozule' ); ?></label>
                            <input type="text" x-model="templateForm.name" class="nzl-input">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Slug *', 'nozule' ); ?></label>
                            <input type="text" x-model="templateForm.slug" class="nzl-input" dir="ltr" style="font-family:monospace;" :disabled="!!editingTemplateId">
                        </div>
                    </div>
                    <!-- Trigger -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Trigger Event', 'nozule' ); ?></label>
                        <select x-model="templateForm.trigger_event" class="nzl-input">
                            <option value=""><?php esc_html_e( 'Manual Only', 'nozule' ); ?></option>
                            <option value="booking_confirmed"><?php esc_html_e( 'Booking Confirmed', 'nozule' ); ?></option>
                            <option value="pre_arrival"><?php esc_html_e( 'Pre-Arrival', 'nozule' ); ?></option>
                            <option value="booking_checked_in"><?php esc_html_e( 'Guest Checked In', 'nozule' ); ?></option>
                            <option value="booking_checked_out"><?php esc_html_e( 'Guest Checked Out', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <!-- Body English -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Body (English) *', 'nozule' ); ?></label>
                        <textarea x-model="templateForm.body" class="nzl-input" dir="ltr" rows="6"></textarea>
                        <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;">
                            <?php esc_html_e( 'Variables: {{guest_name}}, {{booking_number}}, {{check_in}}, {{check_out}}, {{room_type}}, {{room_number}}, {{total_amount}}, {{currency}}, {{hotel_name}}, {{hotel_phone}}', 'nozule' ); ?>
                        </p>
                    </div>
                    <!-- Body Arabic -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Body (Arabic)', 'nozule' ); ?></label>
                        <textarea x-model="templateForm.body_ar" class="nzl-input" dir="rtl" rows="6"></textarea>
                    </div>
                    <!-- Active -->
                    <div style="margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="templateForm.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showTemplateModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveTemplate()" :disabled="saving">
                        <span x-show="!saving" x-text="editingTemplateId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ═══ Test Message Modal ═══ -->
    <template x-if="showTestModal">
        <div class="nzl-modal-overlay" @click.self="showTestModal = false">
            <div class="nzl-modal" style="max-width:480px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Send Test Message', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showTestModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Phone Number (international format)', 'nozule' ); ?></label>
                        <input type="text" x-model="testPhone" class="nzl-input" dir="ltr" placeholder="+966501234567">
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showTestModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="confirmTestMessage()" :disabled="sendingTest">
                        <span x-show="!sendingTest"><?php esc_html_e( 'Send Test', 'nozule' ); ?></span>
                        <span x-show="sendingTest"><?php esc_html_e( 'Sending...', 'nozule' ); ?></span>
                    </button>
                </div>
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
