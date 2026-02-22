<?php
/**
 * Admin template: Competitive Rate Shopping (NZL-039)
 *
 * Monitor competitor pricing on OTAs, detect parity violations,
 * and manage alerts.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlRateShopping">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Rate Shopping', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Monitor competitor pricing on OTAs and track rate parity.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'dashboard'}" @click="switchTab('dashboard')">
            <?php esc_html_e( 'Dashboard', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'competitors'}" @click="switchTab('competitors')">
            <?php esc_html_e( 'Competitors', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'record'}" @click="switchTab('record')">
            <?php esc_html_e( 'Record Rates', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'alerts'}" @click="switchTab('alerts')">
            <?php esc_html_e( 'Alerts', 'nozule' ); ?>
            <template x-if="stats.unresolved_alerts > 0">
                <span class="nzl-badge nzl-badge-cancelled" style="margin-left:0.35rem; font-size:0.7rem;" x-text="stats.unresolved_alerts"></span>
            </template>
        </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Dashboard Tab                                                  -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'dashboard'">

        <!-- Stat Cards -->
        <template x-if="loadingStats">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingStats">
            <div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Total Competitors', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#1e293b;" x-text="stats.total_competitors"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Parity Violations', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700;" :style="stats.unresolved_alerts > 0 ? 'color:#dc2626' : 'color:#059669'" x-text="stats.unresolved_alerts"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Avg Rate Diff', 'nozule' ); ?></div>
                        <div style="font-size:2rem; font-weight:700; color:#6366f1;" x-text="stats.avg_rate_diff + '%'"></div>
                    </div>
                    <div class="nzl-card" style="text-align:center; padding:1.25rem;">
                        <div style="font-size:0.75rem; text-transform:uppercase; color:#94a3b8; margin-bottom:0.5rem;"><?php esc_html_e( 'Last Shop Date', 'nozule' ); ?></div>
                        <div style="font-size:1.25rem; font-weight:700; color:#f59e0b;" x-text="stats.last_shop_date ? formatDate(stats.last_shop_date) : '—'"></div>
                    </div>
                </div>

                <!-- Date Range Filter -->
                <div class="nzl-card" style="margin-bottom:1rem;">
                    <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                            <input type="date" x-model="parityFilters.date_from" class="nzl-input" dir="ltr">
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                            <input type="date" x-model="parityFilters.date_to" class="nzl-input" dir="ltr">
                        </div>
                        <button class="nzl-btn nzl-btn-primary" @click="loadParityReport()"><?php esc_html_e( 'Apply', 'nozule' ); ?></button>
                    </div>
                </div>

                <!-- Parity Report Table -->
                <template x-if="loadingParity">
                    <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                </template>

                <template x-if="!loadingParity">
                    <div class="nzl-table-wrap">
                        <table class="nzl-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Check Date', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Our Rate', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Competitor', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Their Rate', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Difference', 'nozule' ); ?></th>
                                    <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in parityReport" :key="row.check_date + '-' + row.competitor_id">
                                    <tr>
                                        <td dir="ltr" style="font-size:0.875rem;" x-text="row.check_date"></td>
                                        <td dir="ltr" x-text="row.our_rate !== null ? formatPrice(row.our_rate) : '—'"></td>
                                        <td x-text="row.competitor_name"></td>
                                        <td dir="ltr" x-text="formatPrice(row.their_rate)"></td>
                                        <td dir="ltr">
                                            <template x-if="row.difference !== null">
                                                <span :style="row.difference < 0 ? 'color:#dc2626' : (row.difference > 0 ? 'color:#f59e0b' : 'color:#059669')">
                                                    <span x-text="(row.difference > 0 ? '+' : '') + row.difference"></span>
                                                    (<span x-text="(row.pct_difference > 0 ? '+' : '') + row.pct_difference + '%'"></span>)
                                                </span>
                                            </template>
                                            <template x-if="row.difference === null">
                                                <span style="color:#94a3b8;">—</span>
                                            </template>
                                        </td>
                                        <td>
                                            <span class="nzl-badge"
                                                  :class="{
                                                      'nzl-badge-confirmed': row.status === 'parity',
                                                      'nzl-badge-cancelled': row.status === 'undercut',
                                                      'nzl-badge-pending': row.status === 'overpriced'
                                                  }"
                                                  x-text="parityStatusLabel(row.status)">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="parityReport.length === 0">
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:#94a3b8;">
                                            <?php esc_html_e( 'No parity data found for the selected date range.', 'nozule' ); ?>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Competitors Tab                                                -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'competitors'">

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button class="nzl-btn nzl-btn-primary" @click="openCompetitorModal()">
                + <?php esc_html_e( 'Add Competitor', 'nozule' ); ?>
            </button>
        </div>

        <template x-if="loadingCompetitors">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingCompetitors">
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Room Type Match', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Notes', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="comp in competitors" :key="comp.id">
                            <tr>
                                <td x-text="comp.name"></td>
                                <td dir="rtl" x-text="comp.name_ar || '—'"></td>
                                <td>
                                    <span style="font-family:monospace; font-size:0.8rem; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="sourceLabel(comp.source)"></span>
                                </td>
                                <td x-text="roomTypeName(comp.room_type_match) || '—'"></td>
                                <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" x-text="comp.notes || '—'"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="comp.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="comp.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editCompetitor(comp)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteCompetitor(comp.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="competitors.length === 0">
                            <tr>
                                <td colspan="7" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No competitors configured yet.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Record Rates Tab                                               -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'record'">

        <!-- Entry Mode Toggle -->
        <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
            <button class="nzl-btn nzl-btn-sm" :class="{'nzl-btn-primary': entryMode === 'single'}" @click="entryMode = 'single'">
                <?php esc_html_e( 'Single Entry', 'nozule' ); ?>
            </button>
            <button class="nzl-btn nzl-btn-sm" :class="{'nzl-btn-primary': entryMode === 'bulk'}" @click="entryMode = 'bulk'">
                <?php esc_html_e( 'Bulk Entry', 'nozule' ); ?>
            </button>
        </div>

        <!-- Single Entry Form -->
        <div x-show="entryMode === 'single'" class="nzl-card" style="max-width:600px; margin-bottom:1.5rem;">
            <h3 style="margin:0 0 1rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'Record a Rate', 'nozule' ); ?></h3>
            <div class="nzl-form-grid">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Competitor *', 'nozule' ); ?></label>
                    <select x-model="rateForm.competitor_id" class="nzl-input">
                        <option value=""><?php esc_html_e( 'Select competitor...', 'nozule' ); ?></option>
                        <template x-for="comp in competitors" :key="comp.id">
                            <option :value="comp.id" x-text="comp.name + ' (' + sourceLabel(comp.source) + ')'"></option>
                        </template>
                    </select>
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Check Date *', 'nozule' ); ?></label>
                    <input type="date" x-model="rateForm.check_date" class="nzl-input" dir="ltr">
                </div>
            </div>
            <div class="nzl-form-grid">
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Rate *', 'nozule' ); ?></label>
                    <input type="number" x-model="rateForm.rate" class="nzl-input" dir="ltr" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                    <select x-model="rateForm.currency" class="nzl-input">
                        <option value="SAR">SAR</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="SYP">SYP</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
                <button class="nzl-btn nzl-btn-primary" @click="submitSingleRate()" :disabled="savingRate">
                    <span x-show="!savingRate"><?php esc_html_e( 'Record Rate', 'nozule' ); ?></span>
                    <span x-show="savingRate"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>

        <!-- Bulk Entry Form -->
        <div x-show="entryMode === 'bulk'" class="nzl-card" style="margin-bottom:1.5rem;">
            <h3 style="margin:0 0 1rem; font-size:1.125rem; font-weight:600;"><?php esc_html_e( 'Bulk Rate Entry', 'nozule' ); ?></h3>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end; margin-bottom:1rem;">
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Check Date *', 'nozule' ); ?></label>
                    <input type="date" x-model="bulkDate" class="nzl-input" dir="ltr">
                </div>
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                    <select x-model="bulkCurrency" class="nzl-input">
                        <option value="SAR">SAR</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="SYP">SYP</option>
                    </select>
                </div>
            </div>

            <template x-if="competitors.length > 0">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Competitor', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Source', 'nozule' ); ?></th>
                                <th style="width:150px;"><?php esc_html_e( 'Rate', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="comp in activeCompetitors()" :key="comp.id">
                                <tr>
                                    <td x-text="comp.name"></td>
                                    <td>
                                        <span style="font-family:monospace; font-size:0.8rem; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="sourceLabel(comp.source)"></span>
                                    </td>
                                    <td>
                                        <input type="number" class="nzl-input" dir="ltr" min="0" step="0.01" placeholder="0.00"
                                               :value="bulkRates[comp.id] || ''"
                                               @input="bulkRates[comp.id] = $event.target.value">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <div style="margin-top:1rem; display:flex; justify-content:flex-end;">
                <button class="nzl-btn nzl-btn-primary" @click="submitBulkRates()" :disabled="savingRate">
                    <span x-show="!savingRate"><?php esc_html_e( 'Submit All Rates', 'nozule' ); ?></span>
                    <span x-show="savingRate"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>

        <!-- Recent Entries -->
        <h3 style="font-size:1.125rem; font-weight:600; margin-bottom:0.75rem;"><?php esc_html_e( 'Recent Entries', 'nozule' ); ?></h3>

        <template x-if="loadingResults">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingResults">
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Competitor', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Check Date', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Rate', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Currency', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Captured', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="result in recentResults" :key="result.id">
                            <tr>
                                <td x-text="competitorName(result.competitor_id)"></td>
                                <td dir="ltr" style="font-size:0.875rem;" x-text="result.check_date"></td>
                                <td dir="ltr" x-text="formatPrice(result.rate)"></td>
                                <td x-text="result.currency"></td>
                                <td>
                                    <span style="font-family:monospace; font-size:0.8rem; background:#f1f5f9; padding:0.15rem 0.5rem; border-radius:0.25rem;" x-text="result.source"></span>
                                </td>
                                <td dir="ltr" style="font-size:0.875rem; color:#94a3b8;" x-text="formatDate(result.captured_at)"></td>
                            </tr>
                        </template>
                        <template x-if="recentResults.length === 0">
                            <tr>
                                <td colspan="6" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No rate entries recorded yet.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Alerts Tab                                                     -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div x-show="activeTab === 'alerts'">

        <!-- Filters -->
        <div class="nzl-card" style="margin-bottom:1rem;">
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                    <select x-model="alertFilters.status" @change="alertPage=1; loadAlerts()" class="nzl-input">
                        <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                        <option value="unresolved"><?php esc_html_e( 'Unresolved', 'nozule' ); ?></option>
                        <option value="resolved"><?php esc_html_e( 'Resolved', 'nozule' ); ?></option>
                    </select>
                </div>
                <div>
                    <label class="nzl-label"><?php esc_html_e( 'Competitor', 'nozule' ); ?></label>
                    <select x-model="alertFilters.competitor_id" @change="alertPage=1; loadAlerts()" class="nzl-input">
                        <option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
                        <template x-for="comp in competitors" :key="comp.id">
                            <option :value="comp.id" x-text="comp.name"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <template x-if="loadingAlerts">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loadingAlerts">
            <div>
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Competitor', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Our Rate', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Their Rate', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Difference', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="alert in alerts" :key="alert.id">
                                <tr>
                                    <td dir="ltr" style="font-size:0.875rem;" x-text="alert.check_date"></td>
                                    <td x-text="competitorName(alert.competitor_id)"></td>
                                    <td dir="ltr" x-text="formatPrice(alert.our_rate)"></td>
                                    <td dir="ltr" x-text="formatPrice(alert.their_rate)"></td>
                                    <td dir="ltr">
                                        <span :style="alert.difference < 0 ? 'color:#dc2626' : 'color:#f59e0b'">
                                            <span x-text="(alert.difference > 0 ? '+' : '') + alert.difference"></span>
                                            (<span x-text="(alert.pct_difference > 0 ? '+' : '') + alert.pct_difference + '%'"></span>)
                                        </span>
                                    </td>
                                    <td>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-cancelled': alert.alert_type === 'undercut',
                                                  'nzl-badge-pending': alert.alert_type === 'overpriced'
                                              }"
                                              x-text="alertTypeLabel(alert.alert_type)">
                                        </span>
                                    </td>
                                    <td>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-cancelled': alert.status === 'unresolved',
                                                  'nzl-badge-confirmed': alert.status === 'resolved'
                                              }"
                                              x-text="alert.status === 'resolved' ? '<?php echo esc_js( __( 'Resolved', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Unresolved', 'nozule' ) ); ?>'">
                                        </span>
                                    </td>
                                    <td>
                                        <template x-if="alert.status === 'unresolved'">
                                            <button class="nzl-btn nzl-btn-sm" @click="resolveAlert(alert.id)"><?php esc_html_e( 'Resolve', 'nozule' ); ?></button>
                                        </template>
                                        <template x-if="alert.status === 'resolved'">
                                            <span style="font-size:0.8rem; color:#94a3b8;" x-text="formatDate(alert.resolved_at)"></span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="alerts.length === 0">
                                <tr>
                                    <td colspan="8" style="text-align:center; color:#94a3b8;">
                                        <?php esc_html_e( 'No alerts found.', 'nozule' ); ?>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Alerts Pagination -->
                <template x-if="alertTotalPages > 1">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                        <span style="font-size:0.875rem; color:#64748b;">
                            <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="alertPage"></span>
                            <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="alertTotalPages"></span>
                        </span>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="nzl-btn nzl-btn-sm" @click="prevAlertPage()" :disabled="alertPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                            <button class="nzl-btn nzl-btn-sm" @click="nextAlertPage()" :disabled="alertPage >= alertTotalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- Competitor Modal (Add/Edit)                                    -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <template x-if="showCompetitorModal">
        <div class="nzl-modal-overlay" @click.self="showCompetitorModal = false">
            <div class="nzl-modal" style="max-width:600px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingCompetitorId ? '<?php echo esc_js( __( 'Edit Competitor', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Competitor', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showCompetitorModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Name bilingual -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (English) *', 'nozule' ); ?></label>
                            <input type="text" x-model="competitorForm.name" class="nzl-input" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="competitorForm.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <!-- Source & Room Type -->
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Source (OTA) *', 'nozule' ); ?></label>
                            <select x-model="competitorForm.source" class="nzl-input">
                                <option value=""><?php esc_html_e( 'Select...', 'nozule' ); ?></option>
                                <option value="booking_com">Booking.com</option>
                                <option value="expedia">Expedia</option>
                                <option value="agoda">Agoda</option>
                                <option value="google_hotels">Google Hotels</option>
                                <option value="other"><?php esc_html_e( 'Other', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Type Match', 'nozule' ); ?></label>
                            <select x-model="competitorForm.room_type_match" class="nzl-input">
                                <option value=""><?php esc_html_e( 'None', 'nozule' ); ?></option>
                                <template x-for="rt in roomTypes" :key="rt.id">
                                    <option :value="rt.id" x-text="rt.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <!-- Notes -->
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                        <textarea x-model="competitorForm.notes" class="nzl-input" rows="3"></textarea>
                    </div>
                    <!-- Active toggle -->
                    <div style="margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="competitorForm.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showCompetitorModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveCompetitor()" :disabled="savingCompetitor">
                        <span x-show="!savingCompetitor" x-text="editingCompetitorId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="savingCompetitor"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
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
