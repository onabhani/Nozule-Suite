/**
 * Nozule - Admin Currency
 *
 * Currency management, exchange rates, and converter.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlCurrency', function () {
        return {
            loading: true,
            saving: false,
            savingRate: false,
            activeTab: 'currencies',

            // Currencies
            currencies: [],
            showCurrencyModal: false,
            editingCurrencyId: null,
            currencyForm: {},

            // Exchange rates
            exchangeRates: [],
            rateForm: {
                from_currency: 'SYP',
                to_currency: 'USD',
                rate: ''
            },

            // Converter
            convertForm: {
                amount: '',
                from: 'SYP',
                to: 'USD'
            },
            convertResult: null,

            init: function () {
                this.currencyForm = this.defaultCurrencyForm();
                this.loadCurrencies();
            },

            // ---- Default forms ----

            defaultCurrencyForm: function () {
                return {
                    code: '',
                    name: '',
                    name_ar: '',
                    symbol: '',
                    symbol_ar: '',
                    decimal_places: 2,
                    exchange_rate: 1,
                    is_active: true,
                    sort_order: 0
                };
            },

            // ---- Tab switching ----

            switchTab: function (tab) {
                this.activeTab = tab;
                if (tab === 'rates' && this.exchangeRates.length === 0) {
                    this.loadExchangeRates();
                }
            },

            // ---- Data loading ----

            loadCurrencies: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/currencies').then(function (response) {
                    self.currencies = response.data || [];
                }).catch(function (err) {
                    console.error('Currencies load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_currencies'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadExchangeRates: function () {
                var self = this;

                NozuleAPI.get('/admin/exchange-rates', { limit: 50 }).then(function (response) {
                    self.exchangeRates = response.data || [];
                }).catch(function (err) {
                    console.error('Exchange rates load error:', err);
                });
            },

            // ---- Currency CRUD ----

            openCurrencyModal: function () {
                this.editingCurrencyId = null;
                this.currencyForm = this.defaultCurrencyForm();
                this.showCurrencyModal = true;
            },

            editCurrency: function (cur) {
                this.editingCurrencyId = cur.id;
                this.currencyForm = {
                    code: cur.code || '',
                    name: cur.name || '',
                    name_ar: cur.name_ar || '',
                    symbol: cur.symbol || '',
                    symbol_ar: cur.symbol_ar || '',
                    decimal_places: cur.decimal_places !== undefined ? cur.decimal_places : 2,
                    exchange_rate: cur.exchange_rate || 1,
                    is_active: cur.is_active !== undefined ? cur.is_active : true,
                    sort_order: cur.sort_order || 0
                };
                this.showCurrencyModal = true;
            },

            saveCurrency: function () {
                var self = this;
                var data = {
                    code: self.currencyForm.code,
                    name: self.currencyForm.name,
                    symbol: self.currencyForm.symbol,
                    decimal_places: parseInt(self.currencyForm.decimal_places, 10) || 2,
                    exchange_rate: parseFloat(self.currencyForm.exchange_rate) || 1,
                    is_active: self.currencyForm.is_active ? true : false,
                    sort_order: parseInt(self.currencyForm.sort_order, 10) || 0
                };

                if (self.currencyForm.name_ar) data.name_ar = self.currencyForm.name_ar;
                if (self.currencyForm.symbol_ar) data.symbol_ar = self.currencyForm.symbol_ar;

                if (!data.code || !data.name || !data.symbol) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingCurrencyId) {
                    promise = NozuleAPI.put('/admin/currencies/' + self.editingCurrencyId, data);
                } else {
                    promise = NozuleAPI.post('/admin/currencies', data);
                }

                promise.then(function () {
                    self.showCurrencyModal = false;
                    self.loadCurrencies();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingCurrencyId ? 'currency_updated' : 'currency_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_currency'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteCurrency: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_currency'))) return;
                var self = this;

                NozuleAPI.delete('/admin/currencies/' + id).then(function () {
                    self.loadCurrencies();
                    NozuleUtils.toast(NozuleI18n.t('currency_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_currency'), 'error');
                });
            },

            setDefault: function (id) {
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/currencies/' + id + '/default').then(function () {
                    self.loadCurrencies();
                    NozuleUtils.toast(NozuleI18n.t('default_currency_set'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_set_default'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Exchange rates ----

            saveExchangeRate: function () {
                var self = this;
                if (!self.rateForm.rate || !self.rateForm.from_currency || !self.rateForm.to_currency) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.savingRate = true;

                NozuleAPI.post('/admin/exchange-rates', {
                    from_currency: self.rateForm.from_currency,
                    to_currency: self.rateForm.to_currency,
                    rate: parseFloat(self.rateForm.rate)
                }).then(function () {
                    self.rateForm.rate = '';
                    self.loadExchangeRates();
                    self.loadCurrencies();
                    NozuleUtils.toast(NozuleI18n.t('exchange_rate_saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_rate'), 'error');
                }).finally(function () {
                    self.savingRate = false;
                });
            },

            // ---- Converter ----

            convertCurrency: function () {
                var self = this;
                if (!self.convertForm.amount) {
                    NozuleUtils.toast(NozuleI18n.t('enter_amount'), 'error');
                    return;
                }

                NozuleAPI.post('/currencies/convert', {
                    amount: parseFloat(self.convertForm.amount),
                    from: self.convertForm.from,
                    to: self.convertForm.to
                }).then(function (response) {
                    var result = response.data;
                    self.convertResult = self.convertForm.amount + ' ' + self.convertForm.from + ' = ' + result.converted + ' ' + self.convertForm.to;
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_convert'), 'error');
                });
            }
        };
    });
});
