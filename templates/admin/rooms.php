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
            <button class="vhm-btn vhm-btn-primary" @click="openCreateModal('room_type')">
                <?php esc_html_e( 'Add Room Type', 'venezia-hotel' ); ?>
            </button>
            <button class="vhm-btn vhm-btn-primary" @click="openCreateModal('room')">
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
                                <span class="vhm-badge" :class="type.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="type.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRoomType(type.id)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
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
                                      }" x-text="room.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRoom(room.id)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
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
