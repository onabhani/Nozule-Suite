/**
 * Venezia Hotel Manager - Admin Settings
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmSettings', function () {
        return {
            loading: true,
            activeTab: 'general',
            saving: false,
            saved: false,
            testingConnection: false,
            connectionResult: null,
            settings: {
                general: {
                    hotel_name: '',
                    hotel_email: '',
                    hotel_phone: '',
                    hotel_address: '',
                    timezone: '',
                    check_in_time: '14:00',
                    check_out_time: '12:00'
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
                }
            },

            init: function () {
                this.loadSettings();
            },

            loadSettings: function () {
                var self = this;
                self.loading = true;

                VeneziaAPI.get('/admin/settings').then(function (response) {
                    if (response.data) {
                        var data = response.data;
                        if (data.general) self.settings.general = Object.assign(self.settings.general, data.general);
                        if (data.currency) self.settings.currency = Object.assign(self.settings.currency, data.currency);
                        if (data.booking) self.settings.booking = Object.assign(self.settings.booking, data.booking);
                        if (data.notifications) self.settings.notifications = Object.assign(self.settings.notifications, data.notifications);
                        if (data.policies) self.settings.policies = Object.assign(self.settings.policies, data.policies);
                        if (data.integrations) self.settings.integrations = Object.assign(self.settings.integrations, data.integrations);

                        // Ensure booleans are actual booleans after loading.
                        var intg = self.settings.integrations;
                        intg.enabled = intg.enabled === '1' || intg.enabled === true;
                        intg.sync_bookings = intg.sync_bookings === '1' || intg.sync_bookings === true;
                        intg.sync_contacts = intg.sync_contacts === '1' || intg.sync_contacts === true;
                        intg.sync_invoices = intg.sync_invoices === '1' || intg.sync_invoices === true;
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

                VeneziaAPI.post('/admin/settings', self.settings).then(function () {
                    self.saved = true;
                    VeneziaUtils.toast(VeneziaI18n.__('settings_saved'), 'success');
                    setTimeout(function () { self.saved = false; }, 3000);
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.__('failed_save_settings'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            testConnection: function () {
                var self = this;
                var provider = self.settings.integrations.provider;

                if (provider === 'none') {
                    VeneziaUtils.toast(VeneziaI18n.__('select_provider_first'), 'warning');
                    return;
                }

                // Save settings first so the server has the latest credentials.
                self.testingConnection = true;
                self.connectionResult = null;

                VeneziaAPI.post('/admin/settings', self.settings).then(function () {
                    return VeneziaAPI.post('/admin/integrations/test', { provider: provider });
                }).then(function (response) {
                    self.connectionResult = response.data || response;
                    if (self.connectionResult.success) {
                        VeneziaUtils.toast(self.connectionResult.message, 'success');
                    } else {
                        VeneziaUtils.toast(self.connectionResult.message, 'error');
                    }
                }).catch(function (err) {
                    self.connectionResult = { success: false, message: err.message };
                    VeneziaUtils.toast(err.message || VeneziaI18n.__('connection_test_failed'), 'error');
                }).finally(function () {
                    self.testingConnection = false;
                });
            }
        };
    });
});
