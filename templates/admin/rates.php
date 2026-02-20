<?php
/**
 * Template: Admin Rates & Pricing
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlRates">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Rates & Pricing', 'nozule' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="nzl-btn nzl-btn-primary" x-show="activeTab === 'rate_plans'" @click="openRatePlanModal()">
                <?php esc_html_e( 'Add Rate Plan', 'nozule' ); ?>
            </button>
            <button class="nzl-btn nzl-btn-primary" x-show="activeTab === 'seasonal'" @click="openSeasonalModal()">
                <?php esc_html_e( 'Add Seasonal Rate', 'nozule' ); ?>
            </button>
            <button class="nzl-btn nzl-btn-primary" x-show="activeTab === 'restrictions'" @click="openRestrictionModal()">
                <?php esc_html_e( 'Add Restriction', 'nozule' ); ?>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'rate_plans'}" @click="activeTab = 'rate_plans'">
            <?php esc_html_e( 'Rate Plans', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'seasonal'}" @click="activeTab = 'seasonal'">
            <?php esc_html_e( 'Seasonal Rates', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'restrictions'}" @click="switchTab('restrictions')">
            <?php esc_html_e( 'Restrictions', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Rate Plans table -->
    <template x-if="!loading && activeTab === 'rate_plans'">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Code', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Guest Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Modifier Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Modifier Value', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Min Stay', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="plan in ratePlans" :key="plan.id">
                        <tr>
                            <td x-text="plan.name"></td>
                            <td><code x-text="plan.code" style="font-size:0.85em; background:#f1f5f9; padding:2px 6px; border-radius:3px;"></code></td>
                            <td>
                                <span class="nzl-badge" :class="{'nzl-badge-confirmed': plan.guest_type === 'syrian', 'nzl-badge-cancelled': plan.guest_type === 'non_syrian'}" x-text="NozuleI18n.t('guest_type_' + (plan.guest_type || 'all'))"></span>
                            </td>
                            <td x-text="plan.modifier_type"></td>
                            <td>
                                <span x-text="plan.modifier_type === 'percentage' ? plan.modifier_value + '%' : plan.modifier_value"></span>
                            </td>
                            <td x-text="plan.min_stay || '-'"></td>
                            <td>
                                <span class="nzl-badge" :class="plan.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(plan.status)"></span>
                            </td>
                            <td>
                                <button class="nzl-btn nzl-btn-sm" @click="editRatePlan(plan)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteRatePlan(plan.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="ratePlans.length === 0">
                        <tr><td colspan="8" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No rate plans found.', 'nozule' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Seasonal Rates table -->
    <template x-if="!loading && activeTab === 'seasonal'">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Season Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Start Date', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'End Date', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Modifier', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
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
                                <span class="nzl-badge" :class="rate.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(rate.status)"></span>
                            </td>
                            <td>
                                <button class="nzl-btn nzl-btn-sm" @click="editSeasonalRate(rate)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteSeasonalRate(rate.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="seasonalRates.length === 0">
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No seasonal rates found.', 'nozule' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- ======================= RATE PLAN MODAL ======================= -->
    <template x-if="showRatePlanModal">
        <div class="nzl-modal-overlay" @click.self="showRatePlanModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingRatePlanId ? '<?php echo esc_js( __( 'Edit Rate Plan', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Rate Plan', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showRatePlanModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Plan Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="rpForm.name" placeholder="<?php echo esc_attr__( 'e.g. Standard Rate', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Code', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="rpForm.code" placeholder="<?php echo esc_attr__( 'e.g. standard-rate', 'nozule' ); ?>" @input="rpForm.code = rpForm.code.toLowerCase().replace(/[^a-z0-9-]/g, '-')">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="rpForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                                <option value="absolute"><?php esc_html_e( 'Absolute Price', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="nzl-input" x-model.number="rpForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 10 for 10%', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Min Stay (nights)', 'nozule' ); ?></label>
                            <input type="number" min="1" class="nzl-input" x-model.number="rpForm.min_stay" placeholder="1">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Max Stay (nights)', 'nozule' ); ?></label>
                            <input type="number" min="0" class="nzl-input" x-model.number="rpForm.max_stay" placeholder="0 = unlimited">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Guest Type', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="rpForm.guest_type">
                                <option value="all"><?php esc_html_e( 'All Guests', 'nozule' ); ?></option>
                                <option value="syrian"><?php esc_html_e( 'Syrian', 'nozule' ); ?></option>
                                <option value="non_syrian"><?php esc_html_e( 'Non-Syrian', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="rpForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Description', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="rpForm.description" placeholder="<?php echo esc_attr__( 'Optional description...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRatePlanModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveRatePlan()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRatePlanId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= RESTRICTIONS TABLE (NZL-017) ======================= -->
    <template x-if="!loading && activeTab === 'restrictions'">
        <div>
            <!-- Filters -->
            <div style="display:flex; gap:0.75rem; margin-bottom:1rem; flex-wrap:wrap;">
                <select x-model="restrictionFilters.room_type_id" @change="loadRestrictions()" class="nzl-input" style="min-width:180px;">
                    <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                    <template x-for="rt in roomTypes" :key="rt.id">
                        <option :value="rt.id" x-text="rt.name"></option>
                    </template>
                </select>
                <select x-model="restrictionFilters.restriction_type" @change="loadRestrictions()" class="nzl-input" style="min-width:180px;">
                    <option value=""><?php esc_html_e( 'All Types', 'nozule' ); ?></option>
                    <option value="min_stay"><?php esc_html_e( 'Min Stay', 'nozule' ); ?></option>
                    <option value="max_stay"><?php esc_html_e( 'Max Stay', 'nozule' ); ?></option>
                    <option value="cta"><?php esc_html_e( 'Closed to Arrival (CTA)', 'nozule' ); ?></option>
                    <option value="ctd"><?php esc_html_e( 'Closed to Departure (CTD)', 'nozule' ); ?></option>
                    <option value="stop_sell"><?php esc_html_e( 'Stop Sell', 'nozule' ); ?></option>
                </select>
            </div>

            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Date Range', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Days', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Channel', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="r in restrictions" :key="r.id">
                            <tr>
                                <td>
                                    <span class="nzl-badge"
                                          :class="{
                                              'nzl-badge-info': r.restriction_type === 'min_stay' || r.restriction_type === 'max_stay',
                                              'nzl-badge-warning': r.restriction_type === 'cta' || r.restriction_type === 'ctd',
                                              'nzl-badge-cancelled': r.restriction_type === 'stop_sell'
                                          }"
                                          x-text="restrictionTypeLabel(r.restriction_type)"></span>
                                </td>
                                <td x-text="getRoomTypeName(r.room_type_id)"></td>
                                <td dir="ltr" style="font-size:0.875rem;" x-text="formatDate(r.date_from) + ' → ' + formatDate(r.date_to)"></td>
                                <td x-text="(r.restriction_type === 'min_stay' || r.restriction_type === 'max_stay') ? (r.value + ' ' + NozuleI18n.t('nights')) : '—'"></td>
                                <td style="font-size:0.8rem;" x-text="r.days_of_week ? r.days_of_week : NozuleI18n.t('all_days')"></td>
                                <td x-text="r.channel || NozuleI18n.t('all_channels')"></td>
                                <td>
                                    <span class="nzl-badge" :class="r.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="r.is_active ? NozuleI18n.t('active') : NozuleI18n.t('inactive')"></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.5rem;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editRestriction(r)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteRestriction(r.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="restrictions.length === 0">
                            <tr><td colspan="8" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No restrictions found.', 'nozule' ); ?></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= RESTRICTION MODAL (NZL-017) ======================= -->
    <template x-if="showRestrictionModal">
        <div class="nzl-modal-overlay" @click.self="showRestrictionModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingRestrictionId ? '<?php echo esc_js( __( 'Edit Restriction', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Restriction', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showRestrictionModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Restriction Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="rrForm.restriction_type">
                                <option value="min_stay"><?php esc_html_e( 'Min Stay', 'nozule' ); ?></option>
                                <option value="max_stay"><?php esc_html_e( 'Max Stay', 'nozule' ); ?></option>
                                <option value="cta"><?php esc_html_e( 'Closed to Arrival (CTA)', 'nozule' ); ?></option>
                                <option value="ctd"><?php esc_html_e( 'Closed to Departure (CTD)', 'nozule' ); ?></option>
                                <option value="stop_sell"><?php esc_html_e( 'Stop Sell', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="rrForm.room_type_id">
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Start Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="rrForm.date_from">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'End Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="rrForm.date_to">
                        </div>
                    </div>

                    <!-- Value (for min_stay / max_stay) -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;"
                         x-show="rrForm.restriction_type === 'min_stay' || rrForm.restriction_type === 'max_stay'">
                        <label><?php esc_html_e( 'Nights', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="number" min="1" class="nzl-input" x-model.number="rrForm.value" style="max-width:150px;">
                    </div>

                    <!-- Channel (for stop_sell) -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;"
                         x-show="rrForm.restriction_type === 'stop_sell'">
                        <label><?php esc_html_e( 'Channel', 'nozule' ); ?></label>
                        <select class="nzl-input" x-model="rrForm.channel">
                            <option value=""><?php esc_html_e( 'All Channels', 'nozule' ); ?></option>
                            <option value="booking_com">Booking.com</option>
                            <option value="expedia">Expedia</option>
                            <option value="google_hpa">Google Hotel Ads</option>
                            <option value="website"><?php esc_html_e( 'Direct Website', 'nozule' ); ?></option>
                        </select>
                    </div>

                    <!-- Rate Plan (optional) -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Rate Plan (optional)', 'nozule' ); ?></label>
                        <select class="nzl-input" x-model="rrForm.rate_plan_id">
                            <option value=""><?php esc_html_e( 'All Rate Plans', 'nozule' ); ?></option>
                            <template x-for="rp in ratePlans" :key="rp.id">
                                <option :value="rp.id" x-text="rp.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Days of week -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Days of Week', 'nozule' ); ?></label>
                        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-top:0.25rem;">
                            <template x-for="day in ['sun','mon','tue','wed','thu','fri','sat']" :key="day">
                                <label style="display:flex; align-items:center; gap:0.25rem; cursor:pointer; font-size:0.875rem;">
                                    <input type="checkbox" :value="day" x-model="rrForm.daysArray">
                                    <span x-text="NozuleI18n.t('day_' + day)"></span>
                                </label>
                            </template>
                        </div>
                        <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;"><?php esc_html_e( 'Leave unchecked for all days.', 'nozule' ); ?></p>
                    </div>

                    <!-- Status -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                        <select class="nzl-input" x-model="rrForm.is_active" style="max-width:180px;">
                            <option :value="true"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                            <option :value="false"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRestrictionModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveRestriction()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRestrictionId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= SEASONAL RATE MODAL ======================= -->
    <template x-if="showSeasonalModal">
        <div class="nzl-modal-overlay" @click.self="showSeasonalModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingSeasonalId ? '<?php echo esc_js( __( 'Edit Seasonal Rate', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Seasonal Rate', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showSeasonalModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Season Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="srForm.name" placeholder="<?php echo esc_attr__( 'e.g. Summer Peak', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type ID', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="srForm.room_type_id">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Start Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="srForm.start_date">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'End Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="srForm.end_date">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="srForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                                <option value="absolute"><?php esc_html_e( 'Absolute Price', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="nzl-input" x-model.number="srForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 25 for 25%', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Priority', 'nozule' ); ?></label>
                            <input type="number" min="0" class="nzl-input" x-model.number="srForm.priority" placeholder="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="srForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showSeasonalModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveSeasonalRate()" :disabled="saving">
                        <span x-show="!saving" x-text="editingSeasonalId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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

