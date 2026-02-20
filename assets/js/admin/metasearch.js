/**
 * Nozule - Admin Metasearch / Google Hotel Ads (NZL-016)
 *
 * Settings, price feed management, and CPC campaign configuration.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlMetasearch', function () {
        return {
            loading: true,
            saving: false,
            activeTab: 'settings',

            // Settings form
            settingsForm: {
                enabled: false,
                hotel_id: '',
                partner_key: '',
                hotel_name: '',
                hotel_name_ar: '',
                landing_page_url: '',
                hotel_address: '',
                hotel_city: '',
                hotel_country: 'SY',
                currency: 'SYP',
                free_booking_links: false,
                cpc_enabled: false,
                cpc_budget: 0,
                cpc_bid_type: 'manual'
            },

            // Feed
            feedUrl: '',
            testingFeed: false,
            previewingFeed: false,
            feedTestResult: null,
            feedPreviewXml: null,

            init: function () {
                this.loadSettings();
            },

            // ── Tab switching ──────────────────────────────
            switchTab: function (tab) {
                this.activeTab = tab;
            },

            // ── Settings ───────────────────────────────────

            loadSettings: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/metasearch/settings').then(function (response) {
                    var data = response.data || response;
                    if (data.data) data = data.data;

                    self.settingsForm = {
                        enabled: data.enabled === '1' || data.enabled === true,
                        hotel_id: data.hotel_id || '',
                        partner_key: data.partner_key || '',
                        hotel_name: data.hotel_name || '',
                        hotel_name_ar: data.hotel_name_ar || '',
                        landing_page_url: data.landing_page_url || '',
                        hotel_address: data.hotel_address || '',
                        hotel_city: data.hotel_city || '',
                        hotel_country: data.hotel_country || 'SY',
                        currency: data.currency || 'SYP',
                        free_booking_links: data.free_booking_links === '1' || data.free_booking_links === true,
                        cpc_enabled: data.cpc_enabled === '1' || data.cpc_enabled === true,
                        cpc_budget: parseFloat(data.cpc_budget) || 0,
                        cpc_bid_type: data.cpc_bid_type || 'manual'
                    };

                    self.feedUrl = data.feed_url || '';
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_settings'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            saveSettings: function () {
                var self = this;
                self.saving = true;

                var data = {
                    enabled: self.settingsForm.enabled ? '1' : '0',
                    hotel_id: self.settingsForm.hotel_id,
                    partner_key: self.settingsForm.partner_key,
                    hotel_name: self.settingsForm.hotel_name,
                    hotel_name_ar: self.settingsForm.hotel_name_ar,
                    landing_page_url: self.settingsForm.landing_page_url,
                    hotel_address: self.settingsForm.hotel_address,
                    hotel_city: self.settingsForm.hotel_city,
                    hotel_country: self.settingsForm.hotel_country,
                    currency: self.settingsForm.currency,
                    free_booking_links: self.settingsForm.free_booking_links ? '1' : '0',
                    cpc_enabled: self.settingsForm.cpc_enabled ? '1' : '0',
                    cpc_budget: self.settingsForm.cpc_budget,
                    cpc_bid_type: self.settingsForm.cpc_bid_type
                };

                NozuleAPI.post('/admin/metasearch/settings', data).then(function (response) {
                    var d = response.data || response;
                    if (d.feed_url) self.feedUrl = d.feed_url;
                    NozuleUtils.toast(NozuleI18n.t('settings_saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_settings'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ── Feed ───────────────────────────────────────

            testFeed: function () {
                var self = this;
                self.testingFeed = true;
                self.feedTestResult = null;

                NozuleAPI.post('/admin/metasearch/test-feed').then(function (response) {
                    var data = response.data || response;
                    self.feedTestResult = data.data || data;
                    NozuleUtils.toast(NozuleI18n.t('feed_test_success'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('feed_test_failed'), 'error');
                }).finally(function () {
                    self.testingFeed = false;
                });
            },

            previewFeed: function () {
                var self = this;
                self.previewingFeed = true;
                self.feedPreviewXml = null;

                NozuleAPI.get('/admin/metasearch/feed-preview').then(function (response) {
                    var data = response.data || response;
                    self.feedPreviewXml = data.xml || data.data || '';
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('feed_preview_failed'), 'error');
                }).finally(function () {
                    self.previewingFeed = false;
                });
            },

            copyFeedUrl: function () {
                if (!this.feedUrl) return;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(this.feedUrl).then(function () {
                        NozuleUtils.toast(NozuleI18n.t('copied'), 'success');
                    });
                } else {
                    // Fallback
                    var input = document.createElement('input');
                    input.value = this.feedUrl;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    NozuleUtils.toast(NozuleI18n.t('copied'), 'success');
                }
            }
        };
    });
});
