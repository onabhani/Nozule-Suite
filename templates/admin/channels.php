<?php
/**
 * Template: Admin Channel Manager
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlChannels">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Channel Manager', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="openCreateModal()">
            <?php esc_html_e( 'Add Channel', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Channels grid -->
    <template x-if="!loading && channels.length > 0">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:1.5rem; margin-top:1rem;">
            <template x-for="channel in channels" :key="channel.id">
                <div class="nzl-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <h3 style="font-size:1.125rem; font-weight:600; margin:0;" x-text="channel.name"></h3>
                        <span class="nzl-badge" :class="channel.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'" x-text="NozuleI18n.t(channel.status)"></span>
                    </div>
                    <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.25rem;">
                        <span><?php esc_html_e( 'Type:', 'nozule' ); ?> <strong x-text="channel.type"></strong></span>
                        <span><?php esc_html_e( 'Last Sync:', 'nozule' ); ?> <strong x-text="channel.last_sync || '<?php esc_attr_e( 'Never', 'nozule' ); ?>'"></strong></span>
                        <span><?php esc_html_e( 'Bookings:', 'nozule' ); ?> <strong x-text="channel.booking_count || 0"></strong></span>
                    </div>
                    <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="syncChannel(channel.id)" :disabled="syncing === channel.id">
                            <span x-show="syncing !== channel.id"><?php esc_html_e( 'Sync Now', 'nozule' ); ?></span>
                            <span x-show="syncing === channel.id"><?php esc_html_e( 'Syncing...', 'nozule' ); ?></span>
                        </button>
                        <button class="nzl-btn nzl-btn-sm" @click="editChannel(channel.id)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteChannel(channel.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Empty state -->
    <template x-if="!loading && channels.length === 0">
        <div class="nzl-card" style="text-align:center; padding:3rem;">
            <p style="color:#64748b; font-size:1.125rem;"><?php esc_html_e( 'No channels configured yet.', 'nozule' ); ?></p>
            <p style="color:#94a3b8; margin-top:0.5rem;"><?php esc_html_e( 'Connect to OTAs and booking platforms to sync availability and reservations.', 'nozule' ); ?></p>
            <button class="nzl-btn nzl-btn-primary" style="margin-top:1rem;" @click="openCreateModal()">
                <?php esc_html_e( 'Add Your First Channel', 'nozule' ); ?>
            </button>
        </div>
    </template>

    <!-- Add/Edit Channel Modal -->
    <template x-if="showCreateModal">
        <div class="nzl-modal-overlay" @click.self="showCreateModal = false">
            <div class="nzl-modal" style="max-width:540px;">
                <div class="nzl-modal-header">
                    <h2 x-text="editingChannelId ? '<?php esc_attr_e( 'Edit Channel', 'nozule' ); ?>' : '<?php esc_attr_e( 'Add Channel', 'nozule' ); ?>'"></h2>
                    <button class="nzl-modal-close" @click="showCreateModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Channel Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="channelForm.channel_name">
                                <option value=""><?php esc_html_e( '-- Select Channel --', 'nozule' ); ?></option>
                                <option value="booking_com"><?php esc_html_e( 'Booking.com', 'nozule' ); ?></option>
                                <option value="expedia"><?php esc_html_e( 'Expedia', 'nozule' ); ?></option>
                                <option value="airbnb"><?php esc_html_e( 'Airbnb', 'nozule' ); ?></option>
                                <option value="agoda"><?php esc_html_e( 'Agoda', 'nozule' ); ?></option>
                                <option value="hotels_com"><?php esc_html_e( 'Hotels.com', 'nozule' ); ?></option>
                                <option value="direct"><?php esc_html_e( 'Direct', 'nozule' ); ?></option>
                                <option value="custom"><?php esc_html_e( 'Custom', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="channelForm.room_type_id">
                                <option value=""><?php esc_html_e( '-- Select Room Type --', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'External Room/Property ID', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="channelForm.external_room_id" placeholder="<?php echo esc_attr__( 'e.g. 12345678', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="channelForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Sync Options', 'nozule' ); ?></label>
                        <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-top:0.25rem;">
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_availability">
                                <span><?php esc_html_e( 'Sync Availability', 'nozule' ); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_rates">
                                <span><?php esc_html_e( 'Sync Rates', 'nozule' ); ?></span>
                            </label>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                                <input type="checkbox" x-model="channelForm.sync_reservations">
                                <span><?php esc_html_e( 'Sync Reservations', 'nozule' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showCreateModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveChannel()">
                        <span x-show="!saving" x-text="editingChannelId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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
