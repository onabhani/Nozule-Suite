<?php
/**
 * Template: Admin Reports
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmReports">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Reports', 'venezia-hotel' ); ?></h1>
        <button class="vhm-btn" @click="exportReport()">
            <?php esc_html_e( 'Export CSV', 'venezia-hotel' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="vhm-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Report Type', 'venezia-hotel' ); ?></label>
                <select x-model="reportType" @change="loadReport()" class="vhm-input">
                    <option value="revenue"><?php esc_html_e( 'Revenue', 'venezia-hotel' ); ?></option>
                    <option value="occupancy"><?php esc_html_e( 'Occupancy', 'venezia-hotel' ); ?></option>
                    <option value="sources"><?php esc_html_e( 'Booking Sources', 'venezia-hotel' ); ?></option>
                </select>
            </div>
            <div>
                <label class="vhm-label"><?php esc_html_e( 'Period', 'venezia-hotel' ); ?></label>
                <select x-model="period" @change="loadReport()" class="vhm-input">
                    <option value="today"><?php esc_html_e( 'Today', 'venezia-hotel' ); ?></option>
                    <option value="week"><?php esc_html_e( 'This Week', 'venezia-hotel' ); ?></option>
                    <option value="month"><?php esc_html_e( 'This Month', 'venezia-hotel' ); ?></option>
                    <option value="quarter"><?php esc_html_e( 'This Quarter', 'venezia-hotel' ); ?></option>
                    <option value="year"><?php esc_html_e( 'This Year', 'venezia-hotel' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Custom Range', 'venezia-hotel' ); ?></option>
                </select>
            </div>
            <template x-if="period === 'custom'">
                <div style="display:flex; gap:0.5rem; align-items:flex-end;">
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'From', 'venezia-hotel' ); ?></label>
                        <input type="date" x-model="customFrom" class="vhm-input">
                    </div>
                    <div>
                        <label class="vhm-label"><?php esc_html_e( 'To', 'venezia-hotel' ); ?></label>
                        <input type="date" x-model="customTo" class="vhm-input">
                    </div>
                    <button class="vhm-btn vhm-btn-primary vhm-btn-sm" @click="loadReport()">
                        <?php esc_html_e( 'Apply', 'venezia-hotel' ); ?>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Summary cards -->
    <template x-if="!loading && summaryCards.length > 0">
        <div class="vhm-stats-grid">
            <template x-for="card in summaryCards" :key="card.label">
                <div class="vhm-stat-card">
                    <div class="stat-label" x-text="card.label"></div>
                    <div class="stat-value" x-text="card.value"></div>
                    <div style="font-size:0.75rem; margin-top:0.25rem;"
                         :style="card.trend >= 0 ? 'color:#16a34a' : 'color:#dc2626'"
                         x-text="card.trendLabel"></div>
                </div>
            </template>
        </div>
    </template>

    <!-- Chart / data area -->
    <template x-if="!loading">
        <div class="vhm-card" style="margin-top:1rem;">
            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;" x-text="chartTitle"></h2>
            <div x-ref="chartContainer" style="min-height:300px;">
                <template x-if="reportData.length === 0">
                    <p style="text-align:center; color:#94a3b8; padding:3rem 0;"><?php esc_html_e( 'No data available for the selected period.', 'venezia-hotel' ); ?></p>
                </template>
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
