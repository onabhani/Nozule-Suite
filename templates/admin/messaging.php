<?php
/**
 * Admin template: Guest Messaging — Email Templates & Log (NZL-007)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlMessaging">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Guest Messaging', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage email templates and view sent messages.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex; border-bottom:2px solid #e2e8f0; margin-bottom:1.5rem;">
        <button @click="switchTab('templates')" class="nzl-btn" style="border:none; border-bottom:2px solid transparent; border-radius:0; margin-bottom:-2px; padding:0.5rem 1rem;"
            :style="activeTab === 'templates' ? 'border-bottom-color:#3b82f6; color:#3b82f6; font-weight:600;' : 'color:#64748b;'">
            <?php esc_html_e( 'Email Templates', 'nozule' ); ?>
        </button>
        <button @click="switchTab('log')" class="nzl-btn" style="border:none; border-bottom:2px solid transparent; border-radius:0; margin-bottom:-2px; padding:0.5rem 1rem;"
            :style="activeTab === 'log' ? 'border-bottom-color:#3b82f6; color:#3b82f6; font-weight:600;' : 'color:#64748b;'">
            <?php esc_html_e( 'Email Log', 'nozule' ); ?>
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
                            <th><?php esc_html_e( 'Subject', 'nozule' ); ?></th>
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
                                <td style="font-size:0.875rem; color:#64748b;" x-text="tpl.subject"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="tpl.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="tpl.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editTemplate(tpl)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="sendTestEmail(tpl.id)"><?php esc_html_e( 'Test', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteTemplate(tpl.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="templates.length === 0">
                            <tr>
                                <td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No email templates found.', 'nozule' ); ?></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- ═══ Email Log Tab ═══ -->
    <div x-show="activeTab === 'log'">
        <!-- Log Filters -->
        <div class="nzl-card" style="margin-bottom:1rem;">
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                    <select x-model="logFilters.status" @change="logCurrentPage=1; loadEmailLog()" class="nzl-input">
                        <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                        <option value="sent"><?php esc_html_e( 'Sent', 'nozule' ); ?></option>
                        <option value="failed"><?php esc_html_e( 'Failed', 'nozule' ); ?></option>
                        <option value="queued"><?php esc_html_e( 'Queued', 'nozule' ); ?></option>
                    </select>
                </div>
                <div style="flex:1; min-width:180px;">
                    <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                    <input type="text" x-model="logFilters.search" @input.debounce.400ms="logCurrentPage=1; loadEmailLog()" class="nzl-input" placeholder="<?php esc_attr_e( 'Email or subject...', 'nozule' ); ?>">
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
                            <th><?php esc_html_e( 'Subject', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Sent At', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="log in emailLogs" :key="log.id">
                            <tr>
                                <td dir="ltr" x-text="log.to_email"></td>
                                <td style="font-size:0.875rem; color:#64748b;" x-text="log.subject"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="{
                                              'nzl-badge-confirmed': log.status === 'sent',
                                              'nzl-badge-cancelled': log.status === 'failed',
                                              'nzl-badge-pending': log.status === 'queued'
                                          }" x-text="statusLabel(log.status)">
                                    </span>
                                </td>
                                <td dir="ltr" style="font-size:0.875rem; color:#94a3b8;" x-text="log.sent_at || log.created_at"></td>
                            </tr>
                        </template>
                        <template x-if="emailLogs.length === 0">
                            <tr>
                                <td colspan="4" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No emails sent yet.', 'nozule' ); ?></td>
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
                            <option value="pre_arrival"><?php esc_html_e( 'Pre-Arrival (1 day before)', 'nozule' ); ?></option>
                            <option value="booking_checked_in"><?php esc_html_e( 'Guest Checked In', 'nozule' ); ?></option>
                            <option value="booking_checked_out"><?php esc_html_e( 'Guest Checked Out', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <!-- Subject bilingual -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Subject (English) *', 'nozule' ); ?></label>
                            <input type="text" x-model="templateForm.subject" class="nzl-input" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Subject (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="templateForm.subject_ar" class="nzl-input" dir="rtl">
                        </div>
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
