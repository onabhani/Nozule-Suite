<?php
/**
 * Template: Admin Reports
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlReports">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Reports', 'nozule' ); ?></h1>
        <button class="nzl-btn" @click="exportReport()">
            <?php esc_html_e( 'Export CSV', 'nozule' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Report Type', 'nozule' ); ?></label>
                <select x-model="reportType" @change="loadReport()" class="nzl-input">
                    <option value="revenue"><?php esc_html_e( 'Revenue', 'nozule' ); ?></option>
                    <option value="occupancy"><?php esc_html_e( 'Occupancy', 'nozule' ); ?></option>
                    <option value="sources"><?php esc_html_e( 'Booking Sources', 'nozule' ); ?></option>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Period', 'nozule' ); ?></label>
                <select x-model="period" @change="loadReport()" class="nzl-input">
                    <option value="today"><?php esc_html_e( 'Today', 'nozule' ); ?></option>
                    <option value="week"><?php esc_html_e( 'This Week', 'nozule' ); ?></option>
                    <option value="month"><?php esc_html_e( 'This Month', 'nozule' ); ?></option>
                    <option value="quarter"><?php esc_html_e( 'This Quarter', 'nozule' ); ?></option>
                    <option value="year"><?php esc_html_e( 'This Year', 'nozule' ); ?></option>
                    <option value="custom"><?php esc_html_e( 'Custom Range', 'nozule' ); ?></option>
                </select>
            </div>
            <template x-if="period === 'custom'">
                <div style="display:flex; gap:0.5rem; align-items:flex-end;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                        <input type="date" x-model="customFrom" class="nzl-input">
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                        <input type="date" x-model="customTo" class="nzl-input">
                    </div>
                    <button class="nzl-btn nzl-btn-primary nzl-btn-sm" @click="loadReport()">
                        <?php esc_html_e( 'Apply', 'nozule' ); ?>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Summary cards -->
    <template x-if="!loading && summaryCards.length > 0">
        <div class="nzl-stats-grid">
            <template x-for="card in summaryCards" :key="card.label">
                <div class="nzl-stat-card">
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
        <div class="nzl-card" style="margin-top:1rem;">
            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;" x-text="chartTitle"></h2>
            <div x-ref="chartContainer" style="min-height:300px;">
                <template x-if="reportData.length === 0">
                    <p style="text-align:center; color:#94a3b8; padding:3rem 0;"><?php esc_html_e( 'No data available for the selected period.', 'nozule' ); ?></p>
                </template>
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
