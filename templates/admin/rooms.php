<?php
/**
 * Template: Admin Rooms
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlRooms">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Rooms', 'nozule' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="nzl-btn nzl-btn-primary" @click="openRoomTypeModal()">
                <?php esc_html_e( 'Add Room Type', 'nozule' ); ?>
            </button>
            <button class="nzl-btn nzl-btn-primary" @click="openRoomModal()">
                <?php esc_html_e( 'Add Room', 'nozule' ); ?>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'types'}" @click="activeTab = 'types'">
            <?php esc_html_e( 'Room Types', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'rooms'}" @click="activeTab = 'rooms'">
            <?php esc_html_e( 'Individual Rooms', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Room Types table -->
    <template x-if="!loading && activeTab === 'types'">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Base Price', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Max Occupancy', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Rooms', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="type in roomTypes" :key="type.id">
                        <tr>
                            <td x-text="type.name"></td>
                            <td x-text="formatPrice(type.base_price)"></td>
                            <td x-text="type.max_occupancy"></td>
                            <td x-text="type.room_count || 0"></td>
                            <td>
                                <span class="nzl-badge" :class="type.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="statusLabel(type.status)"></span>
                            </td>
                            <td>
                                <button class="nzl-btn nzl-btn-sm" @click="editRoomType(type)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteRoomType(type.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="roomTypes.length === 0">
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No room types found.', 'nozule' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Individual Rooms table -->
    <template x-if="!loading && activeTab === 'rooms'">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Room Number', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Floor', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="room in rooms" :key="room.id">
                        <tr>
                            <td x-text="room.room_number"></td>
                            <td x-text="room.room_type_name"></td>
                            <td x-text="room.floor || '—'"></td>
                            <td>
                                <span class="nzl-badge"
                                      :class="{
                                          'nzl-badge-confirmed': room.status === 'available',
                                          'nzl-badge-checked_in': room.status === 'occupied',
                                          'nzl-badge-pending': room.status === 'maintenance',
                                          'nzl-badge-cancelled': room.status === 'out_of_order'
                                      }" x-text="statusLabel(room.status)"></span>
                            </td>
                            <td>
                                <button class="nzl-btn nzl-btn-sm" @click="editRoom(room)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteRoom(room.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="rooms.length === 0">
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No rooms found.', 'nozule' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- ======================= ROOM TYPE MODAL ======================= -->
    <template x-if="showRoomTypeModal">
        <div class="nzl-modal-overlay" @click.self="showRoomTypeModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingRoomTypeId ? '<?php echo esc_js( __( 'Edit Room Type', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Room Type', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showRoomTypeModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="rtForm.name" placeholder="<?php echo esc_attr__( 'e.g. Deluxe Suite', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Base Price', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" min="0" class="nzl-input" x-model.number="rtForm.base_price" placeholder="<?php echo esc_attr__( 'e.g. 150.00', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Max Occupancy', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" min="1" class="nzl-input" x-model.number="rtForm.max_occupancy" placeholder="2">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="rtForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Description', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="rtForm.description" placeholder="<?php echo esc_attr__( 'Optional description...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRoomTypeModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveRoomType()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRoomTypeId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= ROOM MODAL ======================= -->
    <template x-if="showRoomModal">
        <div class="nzl-modal-overlay" @click.self="showRoomModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingRoomId ? '<?php echo esc_js( __( 'Edit Room', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Room', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showRoomModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Number', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="roomForm.room_number" placeholder="<?php echo esc_attr__( 'e.g. 101', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="roomForm.room_type_id">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Floor', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="roomForm.floor" placeholder="<?php echo esc_attr__( 'e.g. 1', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="roomForm.status">
                                <option value="available"><?php esc_html_e( 'Available', 'nozule' ); ?></option>
                                <option value="occupied"><?php esc_html_e( 'Occupied', 'nozule' ); ?></option>
                                <option value="maintenance"><?php esc_html_e( 'Maintenance', 'nozule' ); ?></option>
                                <option value="out_of_order"><?php esc_html_e( 'Out of Order', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="roomForm.notes" placeholder="<?php echo esc_attr__( 'Optional notes...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRoomModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveRoom()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRoomId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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
