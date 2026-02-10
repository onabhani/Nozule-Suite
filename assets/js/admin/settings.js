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
                    VeneziaUtils.toast('Settings saved successfully', 'success');
                    setTimeout(function () { self.saved = false; }, 3000);
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to save settings', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            }
        };
    });
});
