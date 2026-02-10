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
