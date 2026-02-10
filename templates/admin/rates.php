<?php
/**
 * Template: Admin Rates & Pricing
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmRates">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Rates & Pricing', 'venezia-hotel' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="vhm-btn vhm-btn-primary" @click="openRatePlanModal()">
                <?php esc_html_e( 'Add Rate Plan', 'venezia-hotel' ); ?>
            </button>
            <button class="vhm-btn vhm-btn-primary" @click="openSeasonalModal()">
                <?php esc_html_e( 'Add Seasonal Rate', 'venezia-hotel' ); ?>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="vhm-tabs" style="margin-bottom:1rem;">
        <button class="vhm-tab" :class="{'active': activeTab === 'rate_plans'}" @click="activeTab = 'rate_plans'">
            <?php esc_html_e( 'Rate Plans', 'venezia-hotel' ); ?>
        </button>
        <button class="vhm-tab" :class="{'active': activeTab === 'seasonal'}" @click="activeTab = 'seasonal'">
            <?php esc_html_e( 'Seasonal Rates', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Rate Plans table -->
    <template x-if="!loading && activeTab === 'rate_plans'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Code', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Modifier Type', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Modifier Value', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Min Stay', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="plan in ratePlans" :key="plan.id">
                        <tr>
                            <td x-text="plan.name"></td>
                            <td><code x-text="plan.code" style="font-size:0.85em; background:#f1f5f9; padding:2px 6px; border-radius:3px;"></code></td>
                            <td x-text="plan.modifier_type"></td>
                            <td>
                                <span x-text="plan.modifier_type === 'percentage' ? plan.modifier_value + '%' : plan.modifier_value"></span>
                            </td>
                            <td x-text="plan.min_stay || '-'"></td>
                            <td>
                                <span class="vhm-badge" :class="plan.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="plan.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRatePlan(plan)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteRatePlan(plan.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="ratePlans.length === 0">
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No rate plans found.', 'venezia-hotel' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Seasonal Rates table -->
    <template x-if="!loading && activeTab === 'seasonal'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Season Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Start Date', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'End Date', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Modifier', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="rate in seasonalRates" :key="rate.id">
                        <tr>
                            <td x-text="rate.name"></td>
                            <td x-text="formatDate(rate.start_date)"></td>
                            <td x-text="formatDate(rate.end_date)"></td>
                            <td>
                                <span x-text="rate.modifier_type === 'percentage' ? rate.modifier_value + '%' : rate.modifier_value"></span>
                                <small x-text="'(' + rate.modifier_type + ')'" style="color:#94a3b8;"></small>
                            </td>
                            <td x-text="rate.priority"></td>
                            <td>
                                <span class="vhm-badge" :class="rate.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="rate.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editSeasonalRate(rate)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteSeasonalRate(rate.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="seasonalRates.length === 0">
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No seasonal rates found.', 'venezia-hotel' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- ======================= RATE PLAN MODAL ======================= -->
    <template x-if="showRatePlanModal">
        <div class="vhm-modal-overlay" @click.self="showRatePlanModal = false">
            <div class="vhm-modal" style="max-width:560px;">
                <div class="vhm-modal-header">
                    <h3 x-text="editingRatePlanId ? '<?php echo esc_js( __( 'Edit Rate Plan', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Add Rate Plan', 'venezia-hotel' ) ); ?>'"></h3>
                    <button class="vhm-modal-close" @click="showRatePlanModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Plan Name', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="rpForm.name" placeholder="<?php echo esc_attr__( 'e.g. Standard Rate', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Code', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="rpForm.code" placeholder="<?php echo esc_attr__( 'e.g. standard-rate', 'venezia-hotel' ); ?>" @input="rpForm.code = rpForm.code.toLowerCase().replace(/[^a-z0-9-]/g, '-')">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="vhm-input" x-model="rpForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'venezia-hotel' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'venezia-hotel' ); ?></option>
                                <option value="absolute"><?php esc_html_e( 'Absolute Price', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="vhm-input" x-model.number="rpForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 10 for 10%', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Min Stay (nights)', 'venezia-hotel' ); ?></label>
                            <input type="number" min="1" class="vhm-input" x-model.number="rpForm.min_stay" placeholder="1">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Max Stay (nights)', 'venezia-hotel' ); ?></label>
                            <input type="number" min="0" class="vhm-input" x-model.number="rpForm.max_stay" placeholder="0 = unlimited">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Priority', 'venezia-hotel' ); ?></label>
                            <input type="number" min="0" class="vhm-input" x-model.number="rpForm.priority" placeholder="0">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="rpForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'venezia-hotel' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="vhm-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Description', 'venezia-hotel' ); ?></label>
                        <textarea class="vhm-input" rows="3" x-model="rpForm.description" placeholder="<?php echo esc_attr__( 'Optional description...', 'venezia-hotel' ); ?>"></textarea>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showRatePlanModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveRatePlan()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRatePlanId ? '<?php echo esc_js( __( 'Update', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'venezia-hotel' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'venezia-hotel' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= SEASONAL RATE MODAL ======================= -->
    <template x-if="showSeasonalModal">
        <div class="vhm-modal-overlay" @click.self="showSeasonalModal = false">
            <div class="vhm-modal" style="max-width:560px;">
                <div class="vhm-modal-header">
                    <h3 x-text="editingSeasonalId ? '<?php echo esc_js( __( 'Edit Seasonal Rate', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Add Seasonal Rate', 'venezia-hotel' ) ); ?>'"></h3>
                    <button class="vhm-modal-close" @click="showSeasonalModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Season Name', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="srForm.name" placeholder="<?php echo esc_attr__( 'e.g. Summer Peak', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Room Type ID', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="vhm-input" x-model.number="srForm.room_type_id">
                                <option value=""><?php esc_html_e( '-- Select --', 'venezia-hotel' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Start Date', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="vhm-input" x-model="srForm.start_date">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'End Date', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="vhm-input" x-model="srForm.end_date">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="vhm-input" x-model="srForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'venezia-hotel' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'venezia-hotel' ); ?></option>
                                <option value="absolute"><?php esc_html_e( 'Absolute Price', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="vhm-input" x-model.number="srForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 25 for 25%', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Priority', 'venezia-hotel' ); ?></label>
                            <input type="number" min="0" class="vhm-input" x-model.number="srForm.priority" placeholder="0">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Min Stay', 'venezia-hotel' ); ?></label>
                            <input type="number" min="1" class="vhm-input" x-model.number="srForm.min_stay" placeholder="1">
                        </div>
                        <div class="vhm-form-group" style="grid-column: span 2;">
                            <label><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="srForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'venezia-hotel' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showSeasonalModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveSeasonalRate()" :disabled="saving">
                        <span x-show="!saving" x-text="editingSeasonalId ? '<?php echo esc_js( __( 'Update', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'venezia-hotel' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'venezia-hotel' ); ?></span>
                    </button>
                </div>
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

<style>
.vhm-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
}
.vhm-modal {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    width: 90%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}
.vhm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}
.vhm-modal-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 600;
}
.vhm-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
    padding: 0;
    line-height: 1;
}
.vhm-modal-close:hover { color: #334155; }
.vhm-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
}
.vhm-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
}
.vhm-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.vhm-form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: #475569;
}
.vhm-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: border-color 0.15s;
    box-sizing: border-box;
}
.vhm-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
select.vhm-input { appearance: auto; }
textarea.vhm-input { resize: vertical; }
</style>
