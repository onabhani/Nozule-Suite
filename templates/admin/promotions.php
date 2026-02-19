<?php
/**
 * Admin template: Promo Codes / Discounts (NZL-006)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlPromotions">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Promo Codes', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage discount codes and promotions for bookings.', 'nozule' ); ?></p>
        </div>
        <button class="nzl-btn nzl-btn-primary" @click="openModal()">
            + <?php esc_html_e( 'New Promo Code', 'nozule' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                <select x-model="filters.status" @change="currentPage=1; loadPromoCodes()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                    <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                    <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                    <option value="expired"><?php esc_html_e( 'Expired', 'nozule' ); ?></option>
                </select>
            </div>
            <div style="flex:1; min-width:180px;">
                <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                <input type="text" x-model="filters.search" @input.debounce.400ms="currentPage=1; loadPromoCodes()" class="nzl-input" placeholder="<?php esc_attr_e( 'Code or name...', 'nozule' ); ?>">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Table -->
    <template x-if="!loading">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Code', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Discount', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Validity', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Usage', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="promo in promoCodes" :key="promo.id">
                        <tr>
                            <td>
                                <span style="font-family:monospace; font-weight:600; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="promo.code"></span>
                            </td>
                            <td>
                                <div x-text="promo.name"></div>
                                <div style="font-size:0.8rem; color:#94a3b8;" dir="rtl" x-show="promo.name_ar" x-text="promo.name_ar"></div>
                            </td>
                            <td>
                                <span x-text="promo.discount_type === 'percentage' ? promo.discount_value + '%' : formatPrice(promo.discount_value)"></span>
                            </td>
                            <td style="font-size:0.875rem; color:#64748b;">
                                <template x-if="promo.valid_from || promo.valid_to">
                                    <span x-text="(promo.valid_from || '∞') + ' → ' + (promo.valid_to || '∞')"></span>
                                </template>
                                <template x-if="!promo.valid_from && !promo.valid_to">
                                    <span style="color:#94a3b8;"><?php esc_html_e( 'Always', 'nozule' ); ?></span>
                                </template>
                            </td>
                            <td x-text="promo.used_count + ' / ' + (promo.max_uses || '∞')"></td>
                            <td>
                                <span class="nzl-badge"
                                      :class="promo.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                      x-text="promo.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <button class="nzl-btn nzl-btn-sm" @click="editPromo(promo)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deletePromo(promo.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="promoCodes.length === 0">
                        <tr>
                            <td colspan="7" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No promo codes found.', 'nozule' ); ?>
                            </td>
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

    <!-- ======================= CREATE/EDIT MODAL ======================= -->
    <template x-if="showModal">
        <div class="nzl-modal-overlay" @click.self="showModal = false">
            <div class="nzl-modal" style="max-width:680px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingId ? '<?php echo esc_js( __( 'Edit Promo Code', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Promo Code', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Code & Type -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Code *', 'nozule' ); ?></label>
                            <input type="text" x-model="form.code" class="nzl-input" dir="ltr" style="font-family:monospace; text-transform:uppercase;" placeholder="SUMMER2026" :disabled="!!editingId">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Discount Type *', 'nozule' ); ?></label>
                            <select x-model="form.discount_type" class="nzl-input">
                                <option value="percentage"><?php esc_html_e( 'Percentage (%)', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <!-- Name bilingual -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (English) *', 'nozule' ); ?></label>
                            <input type="text" x-model="form.name" class="nzl-input" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="form.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <!-- Discount value -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Discount Value *', 'nozule' ); ?></label>
                            <input type="number" x-model="form.discount_value" class="nzl-input" dir="ltr" min="0" step="0.01">
                        </div>
                        <div class="nzl-form-group" x-show="form.discount_type === 'fixed'">
                            <label><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                            <select x-model="form.currency_code" class="nzl-input">
                                <option value="SYP">SYP</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="SAR">SAR</option>
                            </select>
                        </div>
                        <div class="nzl-form-group" x-show="form.discount_type === 'percentage'">
                            <label><?php esc_html_e( 'Max Discount Cap', 'nozule' ); ?></label>
                            <input type="number" x-model="form.max_discount" class="nzl-input" dir="ltr" min="0" step="0.01" placeholder="<?php esc_attr_e( 'No limit', 'nozule' ); ?>">
                        </div>
                    </div>
                    <!-- Validity dates -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Valid From', 'nozule' ); ?></label>
                            <input type="date" x-model="form.valid_from" class="nzl-input" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Valid To', 'nozule' ); ?></label>
                            <input type="date" x-model="form.valid_to" class="nzl-input" dir="ltr">
                        </div>
                    </div>
                    <!-- Usage limits -->
                    <div class="nzl-form-grid" style="grid-template-columns:repeat(3, 1fr);">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Max Uses', 'nozule' ); ?></label>
                            <input type="number" x-model="form.max_uses" class="nzl-input" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'Unlimited', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Per Guest Limit', 'nozule' ); ?></label>
                            <input type="number" x-model="form.per_guest_limit" class="nzl-input" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'Unlimited', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Min Nights', 'nozule' ); ?></label>
                            <input type="number" x-model="form.min_nights" class="nzl-input" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'None', 'nozule' ); ?>">
                        </div>
                    </div>
                    <!-- Description bilingual -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Description (English)', 'nozule' ); ?></label>
                            <textarea x-model="form.description" class="nzl-input" dir="ltr" rows="2"></textarea>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Description (Arabic)', 'nozule' ); ?></label>
                            <textarea x-model="form.description_ar" class="nzl-input" dir="rtl" rows="2"></textarea>
                        </div>
                    </div>
                    <!-- Active toggle -->
                    <div style="margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="form.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="savePromo()" :disabled="saving">
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
