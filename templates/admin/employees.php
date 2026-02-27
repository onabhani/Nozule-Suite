<?php
/**
 * Admin template: Employees / Staff Management (NZL-042)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlEmployees">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Employees', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage hotel staff accounts, roles, and permissions.', 'nozule' ); ?></p>
        </div>
        <button class="nzl-btn nzl-btn-primary" @click="openModal()">
            + <?php esc_html_e( 'Add Employee', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Employee Table -->
    <template x-if="!loading">
        <div class="nzl-card" style="overflow-x:auto;">
            <template x-if="employees.length === 0 && loadError">
                <div style="text-align:center; padding:3rem; color:#ef4444;">
                    <p style="font-size:1.25rem; margin-bottom:0.5rem;"><?php esc_html_e( 'Failed to load employees.', 'nozule' ); ?></p>
                    <p style="margin-bottom:1rem; font-size:0.875rem;" x-text="loadError"></p>
                    <button class="nzl-btn nzl-btn-primary" @click="loadEmployees()"><?php esc_html_e( 'Retry', 'nozule' ); ?></button>
                </div>
            </template>

            <template x-if="employees.length === 0 && !loadError">
                <div style="text-align:center; padding:3rem; color:#94a3b8;">
                    <p style="font-size:1.25rem; margin-bottom:0.5rem;"><?php esc_html_e( 'No employees yet.', 'nozule' ); ?></p>
                    <p><?php esc_html_e( 'Add your first staff member to get started.', 'nozule' ); ?></p>
                </div>
            </template>

            <template x-if="employees.length > 0">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Username', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Role', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Joined', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="emp in employees" :key="emp.id">
                            <tr>
                                <td x-text="emp.display_name"></td>
                                <td><code x-text="emp.username" style="font-size:0.8rem;"></code></td>
                                <td x-text="emp.email"></td>
                                <td>
                                    <span class="nzl-badge" :class="emp.role === 'nzl_manager' ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="roleLabel(emp.role)"></span>
                                </td>
                                <td x-text="formatDate(emp.registered)"></td>
                                <td style="white-space:nowrap;">
                                    <button class="nzl-btn nzl-btn-sm" @click="openModal(emp)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deactivateEmployee(emp.id)"><?php esc_html_e( 'Deactivate', 'nozule' ); ?></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </template>
        </div>
    </template>

    <!-- =================== CREATE / EDIT MODAL =================== -->
    <template x-if="showModal">
        <div class="nzl-modal-overlay" @click.self="showModal = false">
            <div class="nzl-modal" style="max-width:600px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingId ? '<?php echo esc_js( __( 'Edit Employee', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Employee', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">

                    <!-- Account -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Account', 'nozule' ); ?>
                    </h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Display Name *', 'nozule' ); ?></label>
                            <input type="text" x-model="form.display_name" class="nzl-input">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Email *', 'nozule' ); ?></label>
                            <input type="email" x-model="form.email" class="nzl-input" dir="ltr">
                        </div>
                    </div>

                    <template x-if="!editingId">
                        <div class="nzl-form-grid">
                            <div class="nzl-form-group">
                                <label><?php esc_html_e( 'Username *', 'nozule' ); ?></label>
                                <input type="text" x-model="form.username" class="nzl-input" dir="ltr" autocomplete="off">
                            </div>
                            <div class="nzl-form-group">
                                <label><?php esc_html_e( 'Password *', 'nozule' ); ?></label>
                                <input type="password" x-model="form.password" class="nzl-input" dir="ltr" autocomplete="new-password">
                            </div>
                        </div>
                    </template>

                    <template x-if="editingId">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'New Password (leave empty to keep current)', 'nozule' ); ?></label>
                            <input type="password" x-model="form.password" class="nzl-input" dir="ltr" autocomplete="new-password">
                        </div>
                    </template>

                    <!-- Role -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:1.5rem 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Role', 'nozule' ); ?>
                    </h4>
                    <div class="nzl-form-group">
                        <template x-if="isSelf()">
                            <div>
                                <p style="font-size:0.875rem; color:#94a3b8; margin:0;"><?php esc_html_e( 'You cannot change your own role.', 'nozule' ); ?></p>
                                <input type="hidden" x-model="form.role">
                            </div>
                        </template>
                        <template x-if="!isSelf()">
                            <select x-model="form.role" class="nzl-input" @change="applyRolePreset()">
                                <option value="nzl_manager"><?php esc_html_e( 'Hotel Manager', 'nozule' ); ?></option>
                                <option value="nzl_reception"><?php esc_html_e( 'Hotel Reception', 'nozule' ); ?></option>
                                <option value="nzl_housekeeper"><?php esc_html_e( 'Housekeeper', 'nozule' ); ?></option>
                                <option value="nzl_finance"><?php esc_html_e( 'Finance', 'nozule' ); ?></option>
                                <option value="nzl_concierge"><?php esc_html_e( 'Concierge', 'nozule' ); ?></option>
                            </select>
                        </template>
                    </div>

                    <!-- Capabilities -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:1.5rem 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Permissions', 'nozule' ); ?>
                    </h4>
                    <template x-if="isSelf()">
                        <p style="font-size:0.875rem; color:#94a3b8;"><?php esc_html_e( 'You cannot change your own permissions.', 'nozule' ); ?></p>
                    </template>
                    <template x-if="!isSelf()">
                        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:0.5rem;">
                            <template x-for="cap in allCapabilities" :key="cap.key">
                                <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.875rem; padding:0.35rem 0;">
                                    <input type="checkbox" :value="cap.key" x-model="form.capabilities">
                                    <span x-text="isArabic ? cap.label_ar : cap.label"></span>
                                </label>
                            </template>
                        </div>
                    </template>

                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="save()" :disabled="saving">
                        <span x-show="!saving" x-text="editingId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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
