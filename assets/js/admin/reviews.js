/**
 * Nozule - Admin Reviews & Reputation
 *
 * Review solicitation dashboard and settings management.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlReviews', function () {
        return {
            activeTab: 'dashboard',

            // Dashboard
            loadingStats: true,
            loadingRequests: false,
            stats: {
                total: 0,
                queued: 0,
                sent: 0,
                failed: 0,
                clicked: 0,
                click_rate: 0
            },

            // Requests list
            requests: [],
            filters: {
                status: '',
                search: ''
            },
            currentPage: 1,
            totalPages: 1,

            // Settings
            loadingSettings: false,
            savingSettings: false,
            settingsForm: {
                enabled: true,
                delay_hours: 2,
                google_review_url: '',
                tripadvisor_url: '',
                email_subject: '',
                email_subject_ar: '',
                email_body: '',
                email_body_ar: ''
            },

            init: function () {
                this.loadStats();
                this.loadRequests();
            },

            // ---- Tab switching ----

            switchTab: function (tab) {
                this.activeTab = tab;
                if (tab === 'dashboard') {
                    if (this.stats.total === 0) {
                        this.loadStats();
                    }
                    if (this.requests.length === 0) {
                        this.loadRequests();
                    }
                }
                if (tab === 'settings' && !this.settingsLoaded) {
                    this.loadSettings();
                }
            },

            // ---- Stats loading ----

            loadStats: function () {
                var self = this;
                self.loadingStats = true;

                NozuleAPI.get('/admin/reviews/stats').then(function (response) {
                    var data = response.data || response;
                    if (data.data) data = data.data;
                    self.stats = {
                        total: data.total || 0,
                        queued: data.queued || 0,
                        sent: data.sent || 0,
                        failed: data.failed || 0,
                        clicked: data.clicked || 0,
                        click_rate: data.click_rate || 0
                    };
                }).catch(function (err) {
                    console.error('Stats load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_stats'), 'error');
                }).finally(function () {
                    self.loadingStats = false;
                });
            },

            // ---- Requests loading ----

            loadRequests: function () {
                var self = this;
                self.loadingRequests = true;

                var params = { page: self.currentPage, per_page: 20 };
                if (self.filters.status) params.status = self.filters.status;
                if (self.filters.search) params.search = self.filters.search;

                NozuleAPI.get('/admin/reviews/requests', params).then(function (response) {
                    var data = response.data || response;
                    self.requests = data.data || data.items || [];
                    if (data.meta) {
                        self.totalPages = data.meta.pages || 1;
                    } else if (data.pages !== undefined) {
                        self.totalPages = data.pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Requests load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_requests'), 'error');
                }).finally(function () {
                    self.loadingRequests = false;
                });
            },

            // ---- Settings ----

            settingsLoaded: false,

            loadSettings: function () {
                var self = this;
                self.loadingSettings = true;

                NozuleAPI.get('/admin/reviews/settings').then(function (response) {
                    var data = response.data || response;
                    if (data.data) data = data.data;

                    self.settingsForm = {
                        enabled: data.enabled === '1' || data.enabled === true,
                        delay_hours: parseInt(data.delay_hours, 10) || 2,
                        google_review_url: data.google_review_url || '',
                        tripadvisor_url: data.tripadvisor_url || '',
                        email_subject: data.email_subject || '',
                        email_subject_ar: data.email_subject_ar || '',
                        email_body: data.email_body || '',
                        email_body_ar: data.email_body_ar || ''
                    };
                    self.settingsLoaded = true;
                }).catch(function (err) {
                    console.error('Settings load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_settings'), 'error');
                }).finally(function () {
                    self.loadingSettings = false;
                });
            },

            saveSettings: function () {
                var self = this;
                self.savingSettings = true;

                var data = {
                    enabled: self.settingsForm.enabled ? '1' : '0',
                    delay_hours: String(self.settingsForm.delay_hours),
                    google_review_url: self.settingsForm.google_review_url,
                    tripadvisor_url: self.settingsForm.tripadvisor_url,
                    email_subject: self.settingsForm.email_subject,
                    email_subject_ar: self.settingsForm.email_subject_ar,
                    email_body: self.settingsForm.email_body,
                    email_body_ar: self.settingsForm.email_body_ar
                };

                NozuleAPI.post('/admin/reviews/settings', data).then(function () {
                    NozuleUtils.toast(NozuleI18n.t('settings_saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_settings'), 'error');
                }).finally(function () {
                    self.savingSettings = false;
                });
            },

            // ---- Pagination ----

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadRequests();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadRequests();
                }
            },

            // ---- Helpers ----

            statusLabel: function (key) {
                return NozuleI18n.t(key);
            }
        };
    });
});
