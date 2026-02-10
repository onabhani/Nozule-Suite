<?php
/**
 * Template: Admin Rates & Pricing
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmRates">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Rates & Pricing', 'venezia-hotel' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="vhm-btn vhm-btn-primary" @click="openCreateModal('rate_plan')">
                <?php esc_html_e( 'Add Rate Plan', 'venezia-hotel' ); ?>
            </button>
            <button class="vhm-btn vhm-btn-primary" @click="openCreateModal('seasonal_rate')">
                <?php esc_html_e( 'Add Seasonal Rate', 'venezia-hotel' ); ?>
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="vhm-tabs" style="margin-bottom:1rem;">
        <button class="vhm-tab" :class="{'active': activeTab === 'rate_plans'}" @click="activeTab = 'rate_plans'">
            <?php esc_html_e( 'Rate Plans', 'venezia-hotel' ); ?>
        </button>
        <button class="vhm-tab" :class="{'active': activeTab === 'seasonal'}" @click="activeTab = 'seasonal'">
            <?php esc_html_e( 'Seasonal Rates', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Rate Plans table -->
    <template x-if="!loading && activeTab === 'rate_plans'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Modifier', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Room Types', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="plan in ratePlans" :key="plan.id">
                        <tr>
                            <td x-text="plan.name"></td>
                            <td x-text="plan.type"></td>
                            <td x-text="plan.modifier_display"></td>
                            <td x-text="plan.room_type_count || 'All'"></td>
                            <td>
                                <span class="vhm-badge" :class="plan.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="plan.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editRatePlan(plan.id)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteRatePlan(plan.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="ratePlans.length === 0">
                        <tr><td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No rate plans found.', 'venezia-hotel' ); ?></td></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Seasonal Rates table -->
    <template x-if="!loading && activeTab === 'seasonal'">
        <div class="vhm-table-wrap">
            <table class="vhm-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Season Name', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Start Date', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'End Date', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Modifier', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'venezia-hotel' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'venezia-hotel' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="rate in seasonalRates" :key="rate.id">
                        <tr>
                            <td x-text="rate.name"></td>
                            <td x-text="formatDate(rate.start_date)"></td>
                            <td x-text="formatDate(rate.end_date)"></td>
                            <td x-text="rate.modifier_display"></td>
                            <td x-text="rate.priority"></td>
                            <td>
                                <span class="vhm-badge" :class="rate.status === 'active' ? 'vhm-badge-confirmed' : 'vhm-badge-cancelled'" x-text="rate.status"></span>
                            </td>
                            <td>
                                <button class="vhm-btn vhm-btn-sm" @click="editSeasonalRate(rate.id)"><?php esc_html_e( 'Edit', 'venezia-hotel' ); ?></button>
                                <button class="vhm-btn vhm-btn-sm vhm-btn-danger" @click="deleteSeasonalRate(rate.id)"><?php esc_html_e( 'Delete', 'venezia-hotel' ); ?></button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="seasonalRates.length === 0">
                        <tr><td colspan="7" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No seasonal rates found.', 'venezia-hotel' ); ?></td></tr>
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
