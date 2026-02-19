<?php
/**
 * Template: Admin Inventory
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlInventory">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Room Inventory', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="openBulkUpdateModal()">
            <?php esc_html_e( 'Bulk Update', 'nozule' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                <select x-model="filters.roomType" @change="loadInventory()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                    <template x-for="type in roomTypes" :key="type.id">
                        <option :value="type.id" x-text="type.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                <input type="date" x-model="filters.from" @change="loadInventory()" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                <input type="date" x-model="filters.to" @change="loadInventory()" class="nzl-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Inventory grid -->
    <template x-if="!loading">
        <div class="nzl-table-wrap" style="overflow-x:auto;">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th style="position:sticky; left:0; background:#f8fafc; z-index:1;"><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
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
            <?php esc_html_e( 'High Availability', 'nozule' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fef3c7;"></span>
            <?php esc_html_e( 'Low Availability', 'nozule' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fecaca;"></span>
            <?php esc_html_e( 'Sold Out', 'nozule' ); ?>
        </span>
    </div>
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
