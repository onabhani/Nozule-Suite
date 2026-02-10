<?php
/**
 * Template: Admin Rooms
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmRooms">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Rooms', 'venezia-hotel' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="vhm-btn vhm-btn-primary" @click="openRoomTypeModal()">
                <?php esc_html_e( 'Add Room Type', 'venezia-hotel' ); ?>
            </button>
            <button class="vhm-btn vhm-btn-primary" @click="openRoomModal()">
                <?php esc_html_e( 'Add Room', 'venezia-hotel' ); ?>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="vhm-tabs" style="margin-bottom:1rem;">
        <button class="vhm-tab" :class="{'active': activeTab === 'types'}" @click="activeTab = 'types'">
            <?php esc_html_e( 'Room Types', 'venezia-hotel' ); ?>
        </button>
        <button class="vhm-tab" :class="{'active': activeTab === 'rooms'}" @click="activeTab = 'rooms'">
            <?php esc_html_e( 'Individual Rooms', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Room Types table -->
    <template x-if="!loading && activeTab === 'types'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Base Price', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Max Occupancy', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Rooms', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
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
                                <span class="vhm-badge" :class="type.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="statusLabel(type.status)"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRoomType(type)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteRoomType(type.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="roomTypes.length === 0">
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No room types found.', 'venezia-hotel' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Individual Rooms table -->
    <template x-if="!loading && activeTab === 'rooms'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Room Number', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Floor', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="room in rooms" :key="room.id">
                        <tr>
                            <td x-text="room.room_number"></td>
                            <td x-text="room.room_type_name"></td>
                            <td x-text="room.floor || '—'"></td>
                            <td>
                                <span class="vhm-badge"
                                      :class="{
                                          'vhm-badge-confirmed': room.status === 'available',
                                          'vhm-badge-checked_in': room.status === 'occupied',
                                          'vhm-badge-pending': room.status === 'maintenance',
                                          'vhm-badge-cancelled': room.status === 'out_of_order'
                                      }" x-text="statusLabel(room.status)"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRoom(room)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteRoom(room.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="rooms.length === 0">
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No rooms found.', 'venezia-hotel' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- ======================= ROOM TYPE MODAL ======================= -->
    <template x-if="showRoomTypeModal">
        <div class="vhm-modal-overlay" @click.self="showRoomTypeModal = false">
            <div class="vhm-modal" style="max-width:560px;">
                <div class="vhm-modal-header">
                    <h3 x-text="editingRoomTypeId ? '<?php echo esc_js( __( 'Edit Room Type', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Add Room Type', 'venezia-hotel' ) ); ?>'"></h3>
                    <button class="vhm-modal-close" @click="showRoomTypeModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Name', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="rtForm.name" placeholder="<?php echo esc_attr__( 'e.g. Deluxe Suite', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Base Price', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" min="0" class="vhm-input" x-model.number="rtForm.base_price" placeholder="<?php echo esc_attr__( 'e.g. 150.00', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Max Occupancy', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" min="1" class="vhm-input" x-model.number="rtForm.max_occupancy" placeholder="2">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="rtForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'venezia-hotel' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="vhm-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Description', 'venezia-hotel' ); ?></label>
                        <textarea class="vhm-input" rows="3" x-model="rtForm.description" placeholder="<?php echo esc_attr__( 'Optional description...', 'venezia-hotel' ); ?>"></textarea>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showRoomTypeModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveRoomType()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRoomTypeId ? '<?php echo esc_js( __( 'Update', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'venezia-hotel' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'venezia-hotel' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= ROOM MODAL ======================= -->
    <template x-if="showRoomModal">
        <div class="vhm-modal-overlay" @click.self="showRoomModal = false">
            <div class="vhm-modal" style="max-width:560px;">
                <div class="vhm-modal-header">
                    <h3 x-text="editingRoomId ? '<?php echo esc_js( __( 'Edit Room', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Add Room', 'venezia-hotel' ) ); ?>'"></h3>
                    <button class="vhm-modal-close" @click="showRoomModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-grid">
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Room Number', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="vhm-input" x-model="roomForm.room_number" placeholder="<?php echo esc_attr__( 'e.g. 101', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="vhm-input" x-model.number="roomForm.room_type_id">
                                <option value=""><?php esc_html_e( '-- Select --', 'venezia-hotel' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Floor', 'venezia-hotel' ); ?></label>
                            <input type="text" class="vhm-input" x-model="roomForm.floor" placeholder="<?php echo esc_attr__( 'e.g. 1', 'venezia-hotel' ); ?>">
                        </div>
                        <div class="vhm-form-group">
                            <label><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                            <select class="vhm-input" x-model="roomForm.status">
                                <option value="available"><?php esc_html_e( 'Available', 'venezia-hotel' ); ?></option>
                                <option value="occupied"><?php esc_html_e( 'Occupied', 'venezia-hotel' ); ?></option>
                                <option value="maintenance"><?php esc_html_e( 'Maintenance', 'venezia-hotel' ); ?></option>
                                <option value="out_of_order"><?php esc_html_e( 'Out of Order', 'venezia-hotel' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="vhm-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'venezia-hotel' ); ?></label>
                        <textarea class="vhm-input" rows="3" x-model="roomForm.notes" placeholder="<?php echo esc_attr__( 'Optional notes...', 'venezia-hotel' ); ?>"></textarea>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showRoomModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveRoom()" :disabled="saving">
                        <span x-show="!saving" x-text="editingRoomId ? '<?php echo esc_js( __( 'Update', 'venezia-hotel' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'venezia-hotel' ) ); ?>'"></span>
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
