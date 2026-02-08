<?php
/**
 * Template: Admin Inventory
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmInventory">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Room Inventory', 'venezia-hotel' ); ?></h1>
        <button class="vhm-btn vhm-btn-primary" @click="openBulkUpdateModal()">
            <?php esc_html_e( 'Bulk Update', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="vhm-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></label>
                <select x-model="filters.roomType" @change="loadInventory()" class="vhm-input">
                    <option value=""><?php esc_html_e( 'All Room Types', 'venezia-hotel' ); ?></option>
                    <template x-for="type in roomTypes" :key="type.id">
                        <option :value="type.id" x-text="type.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'From', 'venezia-hotel' ); ?></label>
                <input type="date" x-model="filters.from" @change="loadInventory()" class="vhm-input">
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'To', 'venezia-hotel' ); ?></label>
                <input type="date" x-model="filters.to" @change="loadInventory()" class="vhm-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Inventory grid -->
    <template x-if="!loading">
        <div class="vhm-table-wrap" style="overflow-x:auto;">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th style="position:sticky; left:0; background:#f8fafc; z-index:1;"><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></th>
                        <template x-for="date in dateRange" :key="date">
                            <th style="text-align:center; min-width:60px;" x-text="formatShortDate(date)"></th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="type in inventoryData" :key="type.id">
                        <tr>
                            <td style="position:sticky; left:0; background:#fff; z-index:1; font-weight:600;" x-text="type.name"></td>
                            <template x-for="date in dateRange" :key="type.id + '-' + date">
                                <td style="text-align:center;"
                                    :style="getCellStyle(type.availability[date], type.total_rooms)"
                                    @click="editInventoryCell(type.id, date)">
                                    <span x-text="type.availability[date] ?? '—'"></span>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Legend -->
    <div style="margin-top:1rem; display:flex; gap:1.5rem; font-size:0.875rem; color:#64748b;">
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#dcfce7;"></span>
            <?php esc_html_e( 'High Availability', 'venezia-hotel' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fef3c7;"></span>
            <?php esc_html_e( 'Low Availability', 'venezia-hotel' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fecaca;"></span>
            <?php esc_html_e( 'Sold Out', 'venezia-hotel' ); ?>
        </span>
    </div>
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
