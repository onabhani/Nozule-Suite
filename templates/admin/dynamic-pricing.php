<?php
/**
 * Template: Admin Dynamic Pricing
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlDynamicPricing">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Dynamic Pricing', 'nozule' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <template x-if="activeTab === 'occupancy'">
                <button class="nzl-btn nzl-btn-primary" @click="openOccupancyModal()">
                    <?php esc_html_e( 'Add Occupancy Rule', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'dow'">
                <button class="nzl-btn nzl-btn-primary" @click="openDowModal()">
                    <?php esc_html_e( 'Add Day-of-Week Rule', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'events'">
                <button class="nzl-btn nzl-btn-primary" @click="openEventModal()">
                    <?php esc_html_e( 'Add Event Override', 'nozule' ); ?>
                </button>
            </template>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'occupancy'}" @click="activeTab = 'occupancy'">
            <?php esc_html_e( 'Occupancy Rules', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'dow'}" @click="activeTab = 'dow'">
            <?php esc_html_e( 'Day-of-Week', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'events'}" @click="activeTab = 'events'">
            <?php esc_html_e( 'Event Overrides', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- ======================= OCCUPANCY RULES TABLE ======================= -->
    <template x-if="!loading && activeTab === 'occupancy'">
        <div class="nzl-card">
            <p style="margin-bottom:1rem; color:#64748b;"><?php esc_html_e( 'Automatically adjust rates based on hotel occupancy levels. When occupancy exceeds the threshold, the modifier is applied.', 'nozule' ); ?></p>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Threshold', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Modifier', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Priority', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="rule in occupancyRules" :key="rule.id">
                            <tr>
                                <td x-text="getRoomTypeName(rule.room_type_id)"></td>
                                <td x-text="rule.threshold_percent + '%'"></td>
                                <td>
                                    <span x-text="rule.modifier_type === 'percentage' ? rule.modifier_value + '%' : rule.modifier_value"></span>
                                    <small x-text="'(' + rule.modifier_type + ')'" style="color:#94a3b8;"></small>
                                </td>
                                <td x-text="rule.priority"></td>
                                <td>
                                    <span class="nzl-badge" :class="rule.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(rule.status)"></span>
                                </td>
                                <td>
                                    <button class="nzl-btn nzl-btn-sm" @click="editOccupancyRule(rule)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteOccupancyRule(rule.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="occupancyRules.length === 0">
                            <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No occupancy rules found.', 'nozule' ); ?></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= DAY-OF-WEEK TABLE ======================= -->
    <template x-if="!loading && activeTab === 'dow'">
        <div class="nzl-card">
            <p style="margin-bottom:1rem; color:#64748b;"><?php esc_html_e( 'Set different rate modifiers for specific days of the week. For example, increase rates on Fridays and Saturdays.', 'nozule' ); ?></p>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Day', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Modifier', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="rule in dowRules" :key="rule.id">
                            <tr>
                                <td x-text="rule.day_name || getDayName(rule.day_of_week)"></td>
                                <td x-text="getRoomTypeName(rule.room_type_id)"></td>
                                <td>
                                    <span x-text="rule.modifier_type === 'percentage' ? rule.modifier_value + '%' : rule.modifier_value"></span>
                                    <small x-text="'(' + rule.modifier_type + ')'" style="color:#94a3b8;"></small>
                                </td>
                                <td>
                                    <span class="nzl-badge" :class="rule.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(rule.status)"></span>
                                </td>
                                <td>
                                    <button class="nzl-btn nzl-btn-sm" @click="editDowRule(rule)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteDowRule(rule.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="dowRules.length === 0">
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No day-of-week rules found.', 'nozule' ); ?></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= EVENT OVERRIDES TABLE ======================= -->
    <template x-if="!loading && activeTab === 'events'">
        <div class="nzl-card">
            <p style="margin-bottom:1rem; color:#64748b;"><?php esc_html_e( 'Create rate overrides for named events and holidays with specific date ranges.', 'nozule' ); ?></p>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Event Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Start Date', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'End Date', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Modifier', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Priority', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="evt in eventOverrides" :key="evt.id">
                            <tr>
                                <td>
                                    <span x-text="evt.name"></span>
                                    <template x-if="evt.name_ar">
                                        <small x-text="'(' + evt.name_ar + ')'" style="color:#94a3b8; display:block;"></small>
                                    </template>
                                </td>
                                <td x-text="getRoomTypeName(evt.room_type_id)"></td>
                                <td x-text="formatDate(evt.start_date)"></td>
                                <td x-text="formatDate(evt.end_date)"></td>
                                <td>
                                    <span x-text="evt.modifier_type === 'percentage' ? evt.modifier_value + '%' : evt.modifier_value"></span>
                                    <small x-text="'(' + evt.modifier_type + ')'" style="color:#94a3b8;"></small>
                                </td>
                                <td x-text="evt.priority"></td>
                                <td>
                                    <span class="nzl-badge" :class="evt.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(evt.status)"></span>
                                </td>
                                <td>
                                    <button class="nzl-btn nzl-btn-sm" @click="editEvent(evt)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteEvent(evt.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="eventOverrides.length === 0">
                            <tr><td colspan="8" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No event overrides found.', 'nozule' ); ?></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= OCCUPANCY RULE MODAL ======================= -->
    <template x-if="showOccupancyModal">
        <div class="nzl-modal-overlay" @click.self="showOccupancyModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingOccupancyId ? '<?php echo esc_js( __( 'Edit Occupancy Rule', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Occupancy Rule', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showOccupancyModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="occForm.room_type_id">
                                <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Threshold (%)', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" min="0" max="100" class="nzl-input" x-model.number="occForm.threshold_percent" placeholder="<?php echo esc_attr__( 'e.g. 70', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="occForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="nzl-input" x-model.number="occForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 10 for +10%', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Priority', 'nozule' ); ?></label>
                            <input type="number" min="0" class="nzl-input" x-model.number="occForm.priority" placeholder="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="occForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showOccupancyModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveOccupancyRule()" :disabled="saving">
                        <span x-show="!saving" x-text="editingOccupancyId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= DOW RULE MODAL ======================= -->
    <template x-if="showDowModal">
        <div class="nzl-modal-overlay" @click.self="showDowModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingDowId ? '<?php echo esc_js( __( 'Edit Day-of-Week Rule', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Day-of-Week Rule', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showDowModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Day of Week', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="dowForm.day_of_week">
                                <option value="0"><?php esc_html_e( 'Sunday', 'nozule' ); ?></option>
                                <option value="1"><?php esc_html_e( 'Monday', 'nozule' ); ?></option>
                                <option value="2"><?php esc_html_e( 'Tuesday', 'nozule' ); ?></option>
                                <option value="3"><?php esc_html_e( 'Wednesday', 'nozule' ); ?></option>
                                <option value="4"><?php esc_html_e( 'Thursday', 'nozule' ); ?></option>
                                <option value="5"><?php esc_html_e( 'Friday', 'nozule' ); ?></option>
                                <option value="6"><?php esc_html_e( 'Saturday', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="dowForm.room_type_id">
                                <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="dowForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="nzl-input" x-model.number="dowForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 15 for +15%', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="dowForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showDowModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveDowRule()" :disabled="saving">
                        <span x-show="!saving" x-text="editingDowId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= EVENT OVERRIDE MODAL ======================= -->
    <template x-if="showEventModal">
        <div class="nzl-modal-overlay" @click.self="showEventModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingEventId ? '<?php echo esc_js( __( 'Edit Event Override', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Event Override', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showEventModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Event Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="evtForm.name" placeholder="<?php echo esc_attr__( 'e.g. Eid Holiday', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Event Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="evtForm.name_ar" placeholder="<?php echo esc_attr__( 'e.g.', 'nozule' ); ?> <?php echo esc_attr( "\u{0639}\u{064A}\u{062F}" ); ?>" dir="rtl">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="evtForm.room_type_id">
                                <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Start Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="evtForm.start_date">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'End Date', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="evtForm.end_date">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="evtForm.modifier_type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Modifier Value', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" class="nzl-input" x-model.number="evtForm.modifier_value" placeholder="<?php echo esc_attr__( 'e.g. 50 for +50%', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Priority', 'nozule' ); ?></label>
                            <input type="number" min="0" class="nzl-input" x-model.number="evtForm.priority" placeholder="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="evtForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showEventModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveEvent()" :disabled="saving">
                        <span x-show="!saving" x-text="editingEventId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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
