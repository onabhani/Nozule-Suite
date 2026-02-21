<?php
/**
 * Template: Admin Demand Forecasting
 *
 * AI-powered demand forecasting with occupancy predictions
 * and rate suggestions based on historical data analysis.
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlForecasting">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Demand Forecasting', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="generateForecast()" :disabled="generating">
            <span x-show="!generating"><?php esc_html_e( 'Generate Forecast', 'nozule' ); ?></span>
            <span x-show="generating"><?php esc_html_e( 'Generating...', 'nozule' ); ?></span>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                <select x-model="selectedRoomType" @change="loadForecasts(); loadSummary();" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Room Types', 'nozule' ); ?></option>
                    <template x-for="rt in roomTypes" :key="rt.id">
                        <option :value="rt.id" x-text="rt.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                <input type="date" x-model="dateFrom" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                <input type="date" x-model="dateTo" class="nzl-input">
            </div>
            <div>
                <button class="nzl-btn nzl-btn-primary nzl-btn-sm" @click="loadForecasts(); loadSummary();">
                    <?php esc_html_e( 'Apply', 'nozule' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Summary Cards -->
    <template x-if="!loading && summary.forecast_count > 0">
        <div class="nzl-stats-grid" style="margin-bottom:1rem;">
            <div class="nzl-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Avg Predicted Occupancy', 'nozule' ); ?></div>
                <div class="stat-value" x-text="formatPercent(summary.avg_predicted_occupancy)"></div>
                <div style="font-size:0.75rem; margin-top:0.25rem;"
                     :style="summary.avg_predicted_occupancy >= 70 ? 'color:#16a34a' : (summary.avg_predicted_occupancy >= 40 ? 'color:#ca8a04' : 'color:#dc2626')">
                    <span x-text="summary.avg_predicted_occupancy >= 70 ? '<?php echo esc_js( __( 'High Demand', 'nozule' ) ); ?>' : (summary.avg_predicted_occupancy >= 40 ? '<?php echo esc_js( __( 'Moderate Demand', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Low Demand', 'nozule' ) ); ?>')"></span>
                </div>
            </div>
            <div class="nzl-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Suggested ADR', 'nozule' ); ?></div>
                <div class="stat-value" x-text="formatPrice(summary.suggested_adr)"></div>
                <div style="font-size:0.75rem; margin-top:0.25rem; color:#64748b;">
                    <?php esc_html_e( 'Average Daily Rate', 'nozule' ); ?>
                </div>
            </div>
            <div class="nzl-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Confidence Level', 'nozule' ); ?></div>
                <div class="stat-value" x-text="formatConfidencePercent(summary.avg_confidence)"></div>
                <div style="font-size:0.75rem; margin-top:0.25rem;"
                     :style="summary.avg_confidence >= 0.7 ? 'color:#16a34a' : (summary.avg_confidence >= 0.4 ? 'color:#ca8a04' : 'color:#dc2626')">
                    <span x-text="getConfidenceLabel(summary.avg_confidence)"></span>
                </div>
            </div>
            <div class="nzl-stat-card">
                <div class="stat-label"><?php esc_html_e( 'Forecast Records', 'nozule' ); ?></div>
                <div class="stat-value" x-text="summary.forecast_count"></div>
                <div style="font-size:0.75rem; margin-top:0.25rem; color:#64748b;">
                    <span x-text="summary.date_from + ' — ' + summary.date_to"></span>
                </div>
            </div>
        </div>
    </template>

    <!-- Empty state for summary -->
    <template x-if="!loading && summary.forecast_count === 0">
        <div class="nzl-card" style="margin-bottom:1rem; text-align:center; padding:2rem;">
            <p style="color:#94a3b8; margin-bottom:1rem;"><?php esc_html_e( 'No forecasts available yet. Click "Generate Forecast" to create predictions based on your booking history.', 'nozule' ); ?></p>
        </div>
    </template>

    <!-- Forecast Table -->
    <template x-if="!loading">
        <div class="nzl-card">
            <h2 style="font-size:1.125rem; font-weight:600; margin-bottom:1rem;"><?php esc_html_e( 'Forecast Details', 'nozule' ); ?></h2>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Predicted Occupancy', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Current Rate', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Suggested Rate', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Rate Change', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Confidence', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Factors', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="forecast in forecasts" :key="forecast.id">
                            <tr>
                                <td>
                                    <span x-text="formatDate(forecast.forecast_date)"></span>
                                    <small style="display:block; color:#94a3b8;" x-text="getDayName(forecast.forecast_date)"></small>
                                </td>
                                <td x-text="getRoomTypeName(forecast.room_type_id)"></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <div style="width:60px; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                            <div style="height:100%; border-radius:4px; transition: width 0.3s;"
                                                 :style="'width:' + Math.min(100, forecast.predicted_occupancy) + '%; background:' + getOccupancyColor(forecast.predicted_occupancy)"></div>
                                        </div>
                                        <span :style="'color:' + getOccupancyColor(forecast.predicted_occupancy)"
                                              x-text="formatPercent(forecast.predicted_occupancy)"></span>
                                    </div>
                                </td>
                                <td x-text="formatPrice(forecast.current_rate)"></td>
                                <td>
                                    <strong x-text="formatPrice(forecast.suggested_rate)"></strong>
                                </td>
                                <td>
                                    <span :style="'color:' + (getRateChange(forecast) >= 0 ? '#16a34a' : '#dc2626')"
                                          x-text="getRateChangeText(forecast)"></span>
                                </td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="getConfidenceBadgeClass(forecast.confidence)"
                                          x-text="getConfidenceLabel(forecast.confidence)"></span>
                                </td>
                                <td>
                                    <button class="nzl-btn nzl-btn-sm" @click="showFactors(forecast)" style="font-size:0.75rem;">
                                        <?php esc_html_e( 'View', 'nozule' ); ?>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="forecasts.length === 0">
                            <tr>
                                <td colspan="8" style="text-align:center; color:#94a3b8; padding:2rem;">
                                    <?php esc_html_e( 'No forecast data available for the selected filters.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- Factors Modal -->
    <template x-if="showFactorsModal">
        <div class="nzl-modal-overlay" @click.self="showFactorsModal = false">
            <div class="nzl-modal" style="max-width:480px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Forecast Factors', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showFactorsModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <p style="margin-bottom:1rem; color:#64748b;">
                        <?php esc_html_e( 'These factors contributed to the forecast for this date:', 'nozule' ); ?>
                    </p>
                    <div class="nzl-table-wrap">
                        <table class="nzl-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Factor', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Value', 'nozule' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(value, key) in selectedFactors" :key="key">
                                    <tr>
                                        <td x-text="formatFactorName(key)"></td>
                                        <td x-text="value"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showFactorsModal = false"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
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
