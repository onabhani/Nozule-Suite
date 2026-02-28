/**
 * Nozule - Admin Settings
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlSettings', function () {
        return {
            loading: true,
            activeTab: 'general',
            saving: false,
            saved: false,
            applyingProfile: false,
            testingConnection: false,
            connectionResult: null,
            countryProfile: null,
            settings: {
                general: {
                    hotel_name: '',
                    hotel_email: '',
                    hotel_phone: '',
                    hotel_address: '',
                    timezone: '',
                    check_in_time: '14:00',
                    check_out_time: '12:00',
                    operating_country: ''
                },
                currency: {
                    default: 'USD',
                    symbol: '$',
                    position: 'before'
                },
                booking: {
                    min_stay: 1,
                    max_stay: 30,
                    advance_days: 365,
                    auto_confirm: false
                },
                notifications: {
                    admin_new_booking: true,
                    admin_cancellation: true,
                    guest_confirmation: true,
                    guest_reminder: true,
                    reminder_days: 1
                },
                policies: {
                    free_cancellation_hours: 24,
                    cancellation_text: '',
                    terms: ''
                },
                integrations: {
                    enabled: false,
                    provider: 'none',
                    odoo_url: '',
                    odoo_database: '',
                    odoo_username: '',
                    odoo_api_key: '',
                    webhook_url: '',
                    webhook_secret: '',
                    sync_bookings: true,
                    sync_contacts: true,
                    sync_invoices: true
                },
                features: {
                    multi_property: false
                }
            },

            init: function () {
                this.loadSettings();
            },

            resolveCountryProfile: function () {
                var code = this.settings.general.operating_country;
                this.countryProfile = code ? (this.countryProfiles[code] || null) : null;
            },

            loadSettings: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/settings').then(function (response) {
                    if (response.data) {
                        var data = response.data;
                        if (data.general) self.settings.general = Object.assign(self.settings.general, data.general);
                        if (data.currency) self.settings.currency = Object.assign(self.settings.currency, data.currency);
                        if (data.booking) self.settings.booking = Object.assign(self.settings.booking, data.booking);
                        if (data.notifications) self.settings.notifications = Object.assign(self.settings.notifications, data.notifications);
                        if (data.policies) self.settings.policies = Object.assign(self.settings.policies, data.policies);
                        if (data.integrations) self.settings.integrations = Object.assign(self.settings.integrations, data.integrations);
                        if (data.features) self.settings.features = Object.assign(self.settings.features, data.features);

                        // Ensure booleans are actual booleans after loading.
                        var intg = self.settings.integrations;
                        intg.enabled = intg.enabled === '1' || intg.enabled === true;
                        intg.sync_bookings = intg.sync_bookings === '1' || intg.sync_bookings === true;
                        intg.sync_contacts = intg.sync_contacts === '1' || intg.sync_contacts === true;
                        intg.sync_invoices = intg.sync_invoices === '1' || intg.sync_invoices === true;

                        var feat = self.settings.features;
                        feat.multi_property = feat.multi_property === '1' || feat.multi_property === true;

                        self.resolveCountryProfile();
                    }
                }).catch(function (err) {
                    console.error('Settings load error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            saveSettings: function () {
                var self = this;
                self.saving = true;
                self.saved = false;

                NozuleAPI.post('/admin/settings', self.settings).then(function () {
                    self.saved = true;
                    NozuleUtils.toast(NozuleI18n.__('settings_saved'), 'success');
                    setTimeout(function () { self.saved = false; }, 3000);
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.__('failed_save_settings'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            countryProfiles: {
                'SY': {
                    label: 'Syria', label_ar: 'سوريا',
                    currency: { code: 'SYP', symbol: 'ل.س', position: 'after' },
                    timezone: 'Asia/Damascus',
                    features: { guest_type_pricing: true, zatca: false, shomos: false }
                },
                'SA': {
                    label: 'Saudi Arabia', label_ar: 'المملكة العربية السعودية',
                    currency: { code: 'SAR', symbol: '﷼', position: 'after' },
                    timezone: 'Asia/Riyadh',
                    features: { guest_type_pricing: false, zatca: true, shomos: true }
                }
            },

            currencySymbols: {
                'SYP': 'ل.س',
                'SAR': '﷼', 'AED': 'د.إ', 'QAR': 'ر.ق', 'KWD': 'د.ك',
                'BHD': 'د.ب', 'OMR': 'ر.ع', 'EGP': 'ج.م', 'JOD': 'د.أ',
                'USD': '$', 'EUR': '€', 'GBP': '£', 'TRY': '₺',
                'MAD': 'د.م', 'INR': '₹', 'CNY': '¥', 'JPY': '¥',
                'CHF': 'CHF', 'CAD': 'C$', 'AUD': 'A$', 'BRL': 'R$'
            },

            onCurrencyChange: function () {
                var code = this.settings.currency.default;
                if (this.currencySymbols[code]) {
                    this.settings.currency.symbol = this.currencySymbols[code];
                }
            },

            onCountryChange: function () {
                this.resolveCountryProfile();
                var profile = this.countryProfile;
                if (!profile) return;

                // Auto-fill currency and timezone from country profile.
                this.settings.currency.default = profile.currency.code;
                this.settings.currency.symbol = profile.currency.symbol;
                this.settings.currency.position = profile.currency.position;
                this.settings.general.timezone = profile.timezone;
            },

            applyCountryDefaults: function () {
                var self = this;
                var code = self.settings.general.operating_country;
                if (!code) return;

                if (!confirm(NozuleI18n.__('confirm_apply_country_defaults'))) return;

                self.applyingProfile = true;

                // First save settings, then ask server to seed taxes.
                NozuleAPI.post('/admin/settings', self.settings).then(function () {
                    return NozuleAPI.post('/admin/settings/apply-country-profile', { country: code });
                }).then(function (response) {
                    NozuleUtils.toast(response.data && response.data.message ? response.data.message : NozuleI18n.__('settings_saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.__('failed_save_settings'), 'error');
                }).finally(function () {
                    self.applyingProfile = false;
                });
            },

            testConnection: function () {
                var self = this;
                var provider = self.settings.integrations.provider;

                if (provider === 'none') {
                    NozuleUtils.toast(NozuleI18n.__('select_provider_first'), 'warning');
                    return;
                }

                // Save settings first so the server has the latest credentials.
                self.testingConnection = true;
                self.connectionResult = null;

                NozuleAPI.post('/admin/settings', self.settings).then(function () {
                    return NozuleAPI.post('/admin/integrations/test', { provider: provider });
                }).then(function (response) {
                    self.connectionResult = response.data || response;
                    if (self.connectionResult.success) {
                        NozuleUtils.toast(self.connectionResult.message, 'success');
                    } else {
                        NozuleUtils.toast(self.connectionResult.message, 'error');
                    }
                }).catch(function (err) {
                    self.connectionResult = { success: false, message: err.message };
                    NozuleUtils.toast(err.message || NozuleI18n.__('connection_test_failed'), 'error');
                }).finally(function () {
                    self.testingConnection = false;
                });
            }
        };
    });
});
