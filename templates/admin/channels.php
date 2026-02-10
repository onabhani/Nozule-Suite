<?php
/**
 * Template: Admin Channel Manager
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmChannels">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Channel Manager', 'venezia-hotel' ); ?></h1>
        <button class="vhm-btn vhm-btn-primary" @click="openCreateModal()">
            <?php esc_html_e( 'Add Channel', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Channels grid -->
    <template x-if="!loading && channels.length > 0">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:1.5rem; margin-top:1rem;">
            <template x-for="channel in channels" :key="channel.id">
                <div class="vhm-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="font-size:1.125rem; font-weight:600; margin:0;" x-text="channel.name"></h3>
                        <span class="vhm-badge" :class="channel.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="channel.status"></span>
                    </div>
                    <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.25rem;">
                        <span><?php esc_html_e( 'Type:', 'venezia-hotel' ); ?> <strong x-text="channel.type"></strong></span>
                        <span><?php esc_html_e( 'Last Sync:', 'venezia-hotel' ); ?> <strong x-text="channel.last_sync || '<?php esc_attr_e( 'Never', 'venezia-hotel' ); ?>'"></strong></span>
                        <span><?php esc_html_e( 'Bookings:', 'venezia-hotel' ); ?> <strong x-text="channel.booking_count || 0"></strong></span>
                    </div>
                    <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                        <button class="vhm-btn vhm-btn-sm vhm-btn-primary" @click="syncChannel(channel.id)" :disabled="syncing === channel.id">
                            <span x-show="syncing !== channel.id"><?php esc_html_e( 'Sync Now', 'venezia-hotel' ); ?></span>
                            <span x-show="syncing === channel.id"><?php esc_html_e( 'Syncing...', 'venezia-hotel' ); ?></span>
                        </button>
                        <button class="vhm-btn vhm-btn-sm" @click="editChannel(channel.id)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                        <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteChannel(channel.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Empty state -->
    <template x-if="!loading && channels.length === 0">
        <div class="vhm-card" style="text-align:center; padding:3rem;">
            <p style="color:#64748b; font-size:1.125rem;"><?php esc_html_e( 'No channels configured yet.', 'venezia-hotel' ); ?></p>
            <p style="color:#94a3b8; margin-top:0.5rem;"><?php esc_html_e( 'Connect to OTAs and booking platforms to sync availability and reservations.', 'venezia-hotel' ); ?></p>
            <button class="vhm-btn vhm-btn-primary" style="margin-top:1rem;" @click="openCreateModal()">
                <?php esc_html_e( 'Add Your First Channel', 'venezia-hotel' ); ?>
            </button>
        </div>
    </template>

    <!-- Add/Edit Channel Modal -->
    <template x-if="showCreateModal">
        <div class="vhm-modal-overlay" @click.self="showCreateModal = false">
            <div class="vhm-modal" style="max-width:540px;">
                <div class="vhm-modal-header">
                    <h2 x-text="editingChannelId ? '<?php esc_attr_e( 'Edit Channel', 'venezia-hotel' ); ?>' : '<?php esc_attr_e( 'Add Channel', 'venezia-hotel' ); ?>'"></h2>
                    <button class="vhm-modal-close" @click="showCreateModal = false">&times;</button>
                </div>
                <div class="vhm-modal-body">
                    <div class="vhm-form-group">
                        <label class="vhm-form-label"><?php esc_html_e( 'Channel Name', 'venezia-hotel' ); ?></label>
                        <select class="vhm-form-input" x-model="channelForm.channel_name">
                            <option value=""><?php esc_html_e( '-- Select Channel --', 'venezia-hotel' ); ?></option>
                            <option value="booking_com"><?php esc_html_e( 'Booking.com', 'venezia-hotel' ); ?></option>
                            <option value="expedia"><?php esc_html_e( 'Expedia', 'venezia-hotel' ); ?></option>
                            <option value="airbnb"><?php esc_html_e( 'Airbnb', 'venezia-hotel' ); ?></option>
                            <option value="agoda"><?php esc_html_e( 'Agoda', 'venezia-hotel' ); ?></option>
                            <option value="hotels_com"><?php esc_html_e( 'Hotels.com', 'venezia-hotel' ); ?></option>
                            <option value="direct"><?php esc_html_e( 'Direct', 'venezia-hotel' ); ?></option>
                            <option value="custom"><?php esc_html_e( 'Custom', 'venezia-hotel' ); ?></option>
                        </select>
                    </div>
                    <div class="vhm-form-group">
                        <label class="vhm-form-label"><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></label>
                        <select class="vhm-form-input" x-model="channelForm.room_type_id">
                            <option value=""><?php esc_html_e( '-- Select Room Type --', 'venezia-hotel' ); ?></option>
                            <template x-for="rt in roomTypes" :key="rt.id">
                                <option :value="rt.id" x-text="rt.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="vhm-form-group">
                        <label class="vhm-form-label"><?php esc_html_e( 'External Room/Property ID', 'venezia-hotel' ); ?></label>
                        <input type="text" class="vhm-form-input" x-model="channelForm.external_room_id" placeholder="<?php esc_attr_e( 'e.g. 12345678', 'venezia-hotel' ); ?>">
                    </div>
                    <div class="vhm-form-group">
                        <label class="vhm-form-label"><?php esc_html_e( 'Sync Options', 'venezia-hotel' ); ?></label>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_availability">
                                <span><?php esc_html_e( 'Availability', 'venezia-hotel' ); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_rates">
                                <span><?php esc_html_e( 'Rates', 'venezia-hotel' ); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_reservations">
                                <span><?php esc_html_e( 'Reservations', 'venezia-hotel' ); ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="vhm-form-group">
                        <label class="vhm-form-label"><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></label>
                        <select class="vhm-form-input" x-model="channelForm.status">
                            <option value="active"><?php esc_html_e( 'Active', 'venezia-hotel' ); ?></option>
                            <option value="inactive"><?php esc_html_e( 'Inactive', 'venezia-hotel' ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="vhm-modal-footer">
                    <button class="vhm-btn" @click="showCreateModal = false"><?php esc_html_e( 'Cancel', 'venezia-hotel' ); ?></button>
                    <button class="vhm-btn vhm-btn-primary" @click="saveChannel()">
                        <span x-text="editingChannelId ? '<?php esc_attr_e( 'Update Channel', 'venezia-hotel' ); ?>' : '<?php esc_attr_e( 'Create Channel', 'venezia-hotel' ); ?>'"></span>
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
