<?php
/**
 * Admin template: Multi-Currency & Exchange Rates (NZL-008)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlCurrency">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Currency & Exchange Rates', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage currencies, exchange rates, and pricing rules.', 'nozule' ); ?></p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'currencies'}" @click="switchTab('currencies')">
            <?php esc_html_e( 'Currencies', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'rates'}" @click="switchTab('rates')">
            <?php esc_html_e( 'Exchange Rates', 'nozule' ); ?>
        </button>
    </div>

    <!-- ═══ Currencies Tab ═══ -->
    <div x-show="activeTab === 'currencies'">
        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button class="nzl-btn nzl-btn-primary" @click="openCurrencyModal()">
                + <?php esc_html_e( 'Add Currency', 'nozule' ); ?>
            </button>
        </div>

        <template x-if="loading">
            <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
        </template>

        <template x-if="!loading">
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Code', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Symbol', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Exchange Rate', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="cur in currencies" :key="cur.id">
                            <tr>
                                <td>
                                    <span style="font-family:monospace; font-weight:600;" x-text="cur.code"></span>
                                    <template x-if="cur.is_default">
                                        <span class="nzl-badge nzl-badge-checked_in" style="margin-inline-start:0.25rem; font-size:0.7rem;"><?php esc_html_e( 'Default', 'nozule' ); ?></span>
                                    </template>
                                </td>
                                <td>
                                    <div x-text="cur.name"></div>
                                    <div style="font-size:0.8rem; color:#94a3b8;" dir="rtl" x-show="cur.name_ar" x-text="cur.name_ar"></div>
                                </td>
                                <td>
                                    <span x-text="cur.symbol"></span>
                                    <template x-if="cur.symbol_ar && cur.symbol_ar !== cur.symbol">
                                        <span style="color:#94a3b8; margin:0 0.25rem;">/</span>
                                    </template>
                                    <span x-show="cur.symbol_ar && cur.symbol_ar !== cur.symbol" dir="rtl" x-text="cur.symbol_ar"></span>
                                </td>
                                <td dir="ltr" style="font-family:monospace;" x-text="cur.exchange_rate"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="cur.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="cur.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editCurrency(cur)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <template x-if="!cur.is_default">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="setDefault(cur.id)"><?php esc_html_e( 'Set Default', 'nozule' ); ?></button>
                                        </template>
                                        <template x-if="!cur.is_default">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteCurrency(cur.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="currencies.length === 0">
                            <tr>
                                <td colspan="6" style="text-align:center; color:#94a3b8;"><?php esc_html_e( 'No currencies configured.', 'nozule' ); ?></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Syrian/Non-Syrian Pricing Info -->
        <div class="nzl-card" style="margin-top:1.5rem; background:#eff6ff; border-color:#bfdbfe;">
            <h4 style="font-size:0.875rem; font-weight:600; color:#1e40af; margin:0 0 0.5rem;"><?php esc_html_e( 'Syrian / Non-Syrian Pricing', 'nozule' ); ?></h4>
            <p style="font-size:0.875rem; color:#1d4ed8; margin:0;">
                <?php esc_html_e( 'To set different prices for Syrian and non-Syrian guests, go to Rates & Pricing and create separate rate plans with the "Guest Type" field set to "Syrian" or "Non-Syrian". The system will automatically apply the correct rate based on guest nationality.', 'nozule' ); ?>
            </p>
        </div>
    </div>

    <!-- ═══ Exchange Rates Tab ═══ -->
    <div x-show="activeTab === 'rates'">
        <!-- Update Exchange Rate -->
        <div class="nzl-card" style="margin-bottom:1rem;">
            <h4 style="font-size:1rem; font-weight:600; margin:0 0 1rem;"><?php esc_html_e( 'Update Exchange Rate', 'nozule' ); ?></h4>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div style="flex:1; min-width:140px;">
                    <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                    <select x-model="rateForm.from_currency" class="nzl-input">
                        <template x-for="cur in currencies" :key="cur.code">
                            <option :value="cur.code" x-text="cur.code + ' - ' + cur.name"></option>
                        </template>
                    </select>
                </div>
                <div style="flex:1; min-width:140px;">
                    <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                    <select x-model="rateForm.to_currency" class="nzl-input">
                        <template x-for="cur in currencies" :key="cur.code">
                            <option :value="cur.code" x-text="cur.code + ' - ' + cur.name"></option>
                        </template>
                    </select>
                </div>
                <div style="width:140px;">
                    <label class="nzl-label"><?php esc_html_e( 'Rate', 'nozule' ); ?></label>
                    <input type="number" x-model="rateForm.rate" class="nzl-input" dir="ltr" step="0.000001" min="0">
                </div>
                <div>
                    <button class="nzl-btn nzl-btn-primary" @click="saveExchangeRate()" :disabled="savingRate">
                        <span x-show="!savingRate"><?php esc_html_e( 'Save Rate', 'nozule' ); ?></span>
                        <span x-show="savingRate"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Currency Converter -->
        <div class="nzl-card" style="margin-bottom:1rem;">
            <h4 style="font-size:1rem; font-weight:600; margin:0 0 1rem;"><?php esc_html_e( 'Quick Converter', 'nozule' ); ?></h4>
            <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                <div style="flex:1; min-width:120px;">
                    <label class="nzl-label"><?php esc_html_e( 'Amount', 'nozule' ); ?></label>
                    <input type="number" x-model="convertForm.amount" class="nzl-input" dir="ltr" min="0" step="0.01">
                </div>
                <div style="flex:1; min-width:100px;">
                    <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                    <select x-model="convertForm.from" class="nzl-input">
                        <template x-for="cur in currencies" :key="'cf'+cur.code">
                            <option :value="cur.code" x-text="cur.code"></option>
                        </template>
                    </select>
                </div>
                <div style="flex:1; min-width:100px;">
                    <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                    <select x-model="convertForm.to" class="nzl-input">
                        <template x-for="cur in currencies" :key="'ct'+cur.code">
                            <option :value="cur.code" x-text="cur.code"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <button class="nzl-btn nzl-btn-primary" @click="convertCurrency()"><?php esc_html_e( 'Convert', 'nozule' ); ?></button>
                </div>
            </div>
            <template x-if="convertResult !== null">
                <div style="margin-top:1rem; padding:0.75rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:0.5rem; text-align:center;">
                    <span style="font-size:1.1rem; font-weight:600; color:#166534;" x-text="convertResult"></span>
                </div>
            </template>
        </div>

        <!-- Rate History -->
        <div class="nzl-card" style="padding:0;">
            <div style="padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0;">
                <h4 style="font-size:0.875rem; font-weight:600; margin:0;"><?php esc_html_e( 'Recent Exchange Rate Updates', 'nozule' ); ?></h4>
            </div>
            <template x-if="exchangeRates.length === 0">
                <div style="text-align:center; padding:2rem; color:#94a3b8;"><?php esc_html_e( 'No exchange rate history.', 'nozule' ); ?></div>
            </template>
            <template x-if="exchangeRates.length > 0">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'From', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'To', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Rate', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Source', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="rate in exchangeRates" :key="rate.id">
                                <tr>
                                    <td style="font-family:monospace;" x-text="rate.from_currency"></td>
                                    <td style="font-family:monospace;" x-text="rate.to_currency"></td>
                                    <td style="font-family:monospace;" dir="ltr" x-text="rate.rate"></td>
                                    <td style="color:#64748b;" x-text="rate.source"></td>
                                    <td style="color:#94a3b8;" dir="ltr" x-text="rate.effective_date"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>

    <!-- ═══ Currency Modal ═══ -->
    <template x-if="showCurrencyModal">
        <div class="nzl-modal-overlay" @click.self="showCurrencyModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingCurrencyId ? '<?php echo esc_js( __( 'Edit Currency', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Currency', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showCurrencyModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Code *', 'nozule' ); ?></label>
                            <input type="text" x-model="currencyForm.code" class="nzl-input" dir="ltr" style="font-family:monospace; text-transform:uppercase;" maxlength="3" :disabled="!!editingCurrencyId">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Decimal Places', 'nozule' ); ?></label>
                            <input type="number" x-model="currencyForm.decimal_places" class="nzl-input" dir="ltr" min="0" max="4">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (English) *', 'nozule' ); ?></label>
                            <input type="text" x-model="currencyForm.name" class="nzl-input" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="currencyForm.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Symbol *', 'nozule' ); ?></label>
                            <input type="text" x-model="currencyForm.symbol" class="nzl-input" dir="ltr" maxlength="10">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Symbol (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="currencyForm.symbol_ar" class="nzl-input" dir="rtl" maxlength="10">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Exchange Rate *', 'nozule' ); ?></label>
                            <input type="number" x-model="currencyForm.exchange_rate" class="nzl-input" dir="ltr" step="0.000001" min="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Sort Order', 'nozule' ); ?></label>
                            <input type="number" x-model="currencyForm.sort_order" class="nzl-input" dir="ltr" min="0">
                        </div>
                    </div>
                    <div style="margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="currencyForm.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showCurrencyModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveCurrency()" :disabled="saving">
                        <span x-show="!saving" x-text="editingCurrencyId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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
