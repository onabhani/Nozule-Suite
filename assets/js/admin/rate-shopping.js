/**
 * Nozule - Admin Rate Shopping (NZL-039)
 *
 * Competitive rate shopping: monitor competitor pricing on OTAs,
 * track rate parity, record rates, and manage alerts.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlRateShopping', function () {
        return {
            activeTab: 'dashboard',

            // ── Stats ──────────────────────────────────────────────
            loadingStats: true,
            stats: {
                total_competitors: 0,
                active_competitors: 0,
                unresolved_alerts: 0,
                avg_rate_diff: 0,
                last_shop_date: null
            },

            // ── Dashboard / Parity ─────────────────────────────────
            loadingParity: false,
            parityReport: [],
            parityFilters: {
                date_from: '',
                date_to: ''
            },

            // ── Competitors ────────────────────────────────────────
            loadingCompetitors: false,
            competitors: [],
            competitorsLoaded: false,
            showCompetitorModal: false,
            editingCompetitorId: null,
            savingCompetitor: false,
            competitorForm: {},

            // ── Room types (for dropdown) ──────────────────────────
            roomTypes: [],

            // ── Record Rates ───────────────────────────────────────
            entryMode: 'single',
            savingRate: false,
            rateForm: {},
            bulkDate: '',
            bulkCurrency: 'SAR',
            bulkRates: {},

            // ── Recent Results ─────────────────────────────────────
            loadingResults: false,
            recentResults: [],
            resultsLoaded: false,

            // ── Alerts ─────────────────────────────────────────────
            loadingAlerts: false,
            alerts: [],
            alertsLoaded: false,
            alertFilters: {
                status: '',
                competitor_id: ''
            },
            alertPage: 1,
            alertTotalPages: 1,

            // ═══════════════════════════════════════════════════════
            // Init
            // ═══════════════════════════════════════════════════════

            init: function () {
                var self = this;
                self.competitorForm = self.defaultCompetitorForm();
                self.rateForm = self.defaultRateForm();

                // Set default date range: today to 7 days ahead.
                var today = new Date();
                self.parityFilters.date_from = self.toDateString(today);
                var nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
                self.parityFilters.date_to = self.toDateString(nextWeek);
                self.bulkDate = self.toDateString(today);

                // Load initial data.
                self.loadStats();
                self.loadCompetitors();
                self.loadRoomTypes();
            },

            // ═══════════════════════════════════════════════════════
            // Tab Switching
            // ═══════════════════════════════════════════════════════

            switchTab: function (tab) {
                this.activeTab = tab;

                if (tab === 'dashboard') {
                    if (this.parityReport.length === 0) {
                        this.loadParityReport();
                    }
                }
                if (tab === 'competitors' && !this.competitorsLoaded) {
                    this.loadCompetitors();
                }
                if (tab === 'record') {
                    if (!this.competitorsLoaded) {
                        this.loadCompetitors();
                    }
                    if (!this.resultsLoaded) {
                        this.loadRecentResults();
                    }
                }
                if (tab === 'alerts' && !this.alertsLoaded) {
                    this.loadAlerts();
                }
            },

            // ═══════════════════════════════════════════════════════
            // Stats
            // ═══════════════════════════════════════════════════════

            loadStats: function () {
                var self = this;
                self.loadingStats = true;

                NozuleAPI.get('/admin/rate-shopping/stats').then(function (response) {
                    var data = response.data || response;
                    if (data.data) data = data.data;
                    self.stats = {
                        total_competitors: data.total_competitors || 0,
                        active_competitors: data.active_competitors || 0,
                        unresolved_alerts: data.unresolved_alerts || 0,
                        avg_rate_diff: data.avg_rate_diff || 0,
                        last_shop_date: data.last_shop_date || null
                    };
                }).catch(function (err) {
                    console.error('Stats load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_stats'), 'error');
                }).finally(function () {
                    self.loadingStats = false;
                    // Auto-load parity report after stats.
                    self.loadParityReport();
                });
            },

            // ═══════════════════════════════════════════════════════
            // Parity Report
            // ═══════════════════════════════════════════════════════

            loadParityReport: function () {
                var self = this;
                self.loadingParity = true;

                var params = {};
                if (self.parityFilters.date_from) params.date_from = self.parityFilters.date_from;
                if (self.parityFilters.date_to) params.date_to = self.parityFilters.date_to;

                NozuleAPI.get('/admin/rate-shopping/parity', params).then(function (response) {
                    var data = response.data || response;
                    self.parityReport = data.data || data || [];
                }).catch(function (err) {
                    console.error('Parity report load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_parity'), 'error');
                }).finally(function () {
                    self.loadingParity = false;
                });
            },

            // ═══════════════════════════════════════════════════════
            // Competitors
            // ═══════════════════════════════════════════════════════

            loadCompetitors: function () {
                var self = this;
                self.loadingCompetitors = true;

                NozuleAPI.get('/admin/rate-shopping/competitors').then(function (response) {
                    var data = response.data || response;
                    self.competitors = data.data || data || [];
                    self.competitorsLoaded = true;
                }).catch(function (err) {
                    console.error('Competitors load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_competitors'), 'error');
                }).finally(function () {
                    self.loadingCompetitors = false;
                });
            },

            defaultCompetitorForm: function () {
                return {
                    name: '',
                    name_ar: '',
                    source: '',
                    room_type_match: '',
                    notes: '',
                    is_active: true
                };
            },

            openCompetitorModal: function () {
                this.editingCompetitorId = null;
                this.competitorForm = this.defaultCompetitorForm();
                this.showCompetitorModal = true;
            },

            editCompetitor: function (comp) {
                this.editingCompetitorId = comp.id;
                this.competitorForm = {
                    name: comp.name || '',
                    name_ar: comp.name_ar || '',
                    source: comp.source || '',
                    room_type_match: comp.room_type_match || '',
                    notes: comp.notes || '',
                    is_active: comp.is_active !== undefined ? !!comp.is_active : true
                };
                this.showCompetitorModal = true;
            },

            saveCompetitor: function () {
                var self = this;
                var data = {
                    name: self.competitorForm.name,
                    name_ar: self.competitorForm.name_ar,
                    source: self.competitorForm.source,
                    room_type_match: self.competitorForm.room_type_match ? parseInt(self.competitorForm.room_type_match, 10) : null,
                    notes: self.competitorForm.notes,
                    is_active: self.competitorForm.is_active ? 1 : 0
                };

                if (!data.name || !data.source) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                if (self.editingCompetitorId) {
                    data.id = self.editingCompetitorId;
                }

                self.savingCompetitor = true;

                NozuleAPI.post('/admin/rate-shopping/competitors', data).then(function () {
                    self.showCompetitorModal = false;
                    self.loadCompetitors();
                    self.loadStats();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingCompetitorId ? 'competitor_updated' : 'competitor_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_competitor'), 'error');
                }).finally(function () {
                    self.savingCompetitor = false;
                });
            },

            deleteCompetitor: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_competitor'))) return;
                var self = this;

                NozuleAPI.delete('/admin/rate-shopping/competitors/' + id).then(function () {
                    self.loadCompetitors();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('competitor_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_competitor'), 'error');
                });
            },

            // ═══════════════════════════════════════════════════════
            // Rate Recording
            // ═══════════════════════════════════════════════════════

            defaultRateForm: function () {
                return {
                    competitor_id: '',
                    check_date: '',
                    rate: '',
                    currency: 'SAR'
                };
            },

            submitSingleRate: function () {
                var self = this;
                var data = {
                    competitor_id: parseInt(self.rateForm.competitor_id, 10) || 0,
                    check_date: self.rateForm.check_date,
                    rate: parseFloat(self.rateForm.rate) || 0,
                    currency: self.rateForm.currency,
                    source: 'manual'
                };

                if (!data.competitor_id || !data.check_date || !data.rate) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.savingRate = true;

                NozuleAPI.post('/admin/rate-shopping/results', data).then(function () {
                    self.rateForm = self.defaultRateForm();
                    self.loadRecentResults();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('rate_recorded'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_record_rate'), 'error');
                }).finally(function () {
                    self.savingRate = false;
                });
            },

            submitBulkRates: function () {
                var self = this;

                if (!self.bulkDate) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                var entries = [];
                var compIds = Object.keys(self.bulkRates);
                for (var i = 0; i < compIds.length; i++) {
                    var compId = compIds[i];
                    var rateVal = parseFloat(self.bulkRates[compId]);
                    if (rateVal && rateVal > 0) {
                        entries.push({
                            competitor_id: parseInt(compId, 10),
                            check_date: self.bulkDate,
                            rate: rateVal,
                            currency: self.bulkCurrency,
                            source: 'manual'
                        });
                    }
                }

                if (entries.length === 0) {
                    NozuleUtils.toast(NozuleI18n.t('no_rates_to_submit'), 'error');
                    return;
                }

                self.savingRate = true;

                NozuleAPI.post('/admin/rate-shopping/results', { entries: entries }).then(function (response) {
                    self.bulkRates = {};
                    self.loadRecentResults();
                    self.loadStats();
                    var data = response.data || response;
                    NozuleUtils.toast(data.message || NozuleI18n.t('rates_recorded'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_record_rates'), 'error');
                }).finally(function () {
                    self.savingRate = false;
                });
            },

            loadRecentResults: function () {
                var self = this;
                self.loadingResults = true;

                NozuleAPI.get('/admin/rate-shopping/results').then(function (response) {
                    var data = response.data || response;
                    self.recentResults = data.data || data || [];
                    self.resultsLoaded = true;
                }).catch(function (err) {
                    console.error('Results load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_results'), 'error');
                }).finally(function () {
                    self.loadingResults = false;
                });
            },

            // ═══════════════════════════════════════════════════════
            // Alerts
            // ═══════════════════════════════════════════════════════

            loadAlerts: function () {
                var self = this;
                self.loadingAlerts = true;

                var params = { page: self.alertPage, per_page: 20 };
                if (self.alertFilters.status) params.status = self.alertFilters.status;
                if (self.alertFilters.competitor_id) params.competitor_id = parseInt(self.alertFilters.competitor_id, 10);

                NozuleAPI.get('/admin/rate-shopping/alerts', params).then(function (response) {
                    var data = response.data || response;
                    if (data.data && data.data.items) {
                        self.alerts = data.data.items;
                        self.alertTotalPages = data.data.pagination ? data.data.pagination.total_pages : 1;
                    } else {
                        self.alerts = data.data || data.items || [];
                        self.alertTotalPages = data.pages || 1;
                    }
                    self.alertsLoaded = true;
                }).catch(function (err) {
                    console.error('Alerts load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_alerts'), 'error');
                }).finally(function () {
                    self.loadingAlerts = false;
                });
            },

            resolveAlert: function (id) {
                var self = this;

                NozuleAPI.put('/admin/rate-shopping/alerts/' + id + '/resolve', {}).then(function () {
                    self.loadAlerts();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('alert_resolved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_resolve_alert'), 'error');
                });
            },

            prevAlertPage: function () {
                if (this.alertPage > 1) {
                    this.alertPage--;
                    this.loadAlerts();
                }
            },

            nextAlertPage: function () {
                if (this.alertPage < this.alertTotalPages) {
                    this.alertPage++;
                    this.loadAlerts();
                }
            },

            // ═══════════════════════════════════════════════════════
            // Room Types (for competitor mapping)
            // ═══════════════════════════════════════════════════════

            loadRoomTypes: function () {
                var self = this;

                NozuleAPI.get('/admin/room-types').then(function (response) {
                    var data = response.data || response;
                    self.roomTypes = data.data || data.items || data || [];
                }).catch(function (err) {
                    console.error('Room types load error:', err);
                });
            },

            // ═══════════════════════════════════════════════════════
            // Helpers
            // ═══════════════════════════════════════════════════════

            /**
             * Get active competitors only.
             */
            activeCompetitors: function () {
                return this.competitors.filter(function (comp) {
                    return comp.is_active;
                });
            },

            /**
             * Look up competitor name by ID.
             */
            competitorName: function (id) {
                for (var i = 0; i < this.competitors.length; i++) {
                    if (this.competitors[i].id == id) {
                        return this.competitors[i].name;
                    }
                }
                return '#' + id;
            },

            /**
             * Look up room type name by ID.
             */
            roomTypeName: function (id) {
                if (!id) return '';
                for (var i = 0; i < this.roomTypes.length; i++) {
                    if (this.roomTypes[i].id == id) {
                        return this.roomTypes[i].name;
                    }
                }
                return '#' + id;
            },

            /**
             * OTA source human label.
             */
            sourceLabel: function (source) {
                var labels = {
                    'booking_com': 'Booking.com',
                    'expedia': 'Expedia',
                    'agoda': 'Agoda',
                    'google_hotels': 'Google Hotels',
                    'other': NozuleI18n.t('other')
                };
                return labels[source] || source || '';
            },

            /**
             * Parity status human label.
             */
            parityStatusLabel: function (status) {
                var labels = {
                    'parity': NozuleI18n.t('parity'),
                    'undercut': NozuleI18n.t('undercut'),
                    'overpriced': NozuleI18n.t('overpriced'),
                    'unknown': NozuleI18n.t('unknown')
                };
                return labels[status] || status || '';
            },

            /**
             * Alert type human label.
             */
            alertTypeLabel: function (type) {
                var labels = {
                    'undercut': NozuleI18n.t('undercut'),
                    'overpriced': NozuleI18n.t('overpriced')
                };
                return labels[type] || type || '';
            },

            /**
             * Compare our rate vs theirs — returns comparison object.
             */
            rateComparison: function (ourRate, theirRate) {
                var diff = 0;
                var pctDiff = 0;
                var status = 'unknown';

                if (ourRate && theirRate) {
                    diff = Math.round((theirRate - ourRate) * 100) / 100;
                    pctDiff = ourRate > 0
                        ? Math.round((diff / ourRate) * 10000) / 100
                        : 0;

                    if (Math.abs(pctDiff) <= 5) {
                        status = 'parity';
                    } else if (diff < 0) {
                        status = 'undercut';
                    } else {
                        status = 'overpriced';
                    }
                }

                return {
                    diff: diff,
                    pctDiff: pctDiff,
                    status: status
                };
            },

            /**
             * Format price for display.
             */
            formatPrice: function (amount) {
                if (amount === null || amount === undefined) return '—';
                return NozuleUtils.formatPrice(amount);
            },

            /**
             * Format date/time for display.
             */
            formatDate: function (dateStr) {
                if (!dateStr) return '—';
                // Return just the date portion if it contains time.
                if (dateStr.indexOf(' ') !== -1) {
                    return dateStr.substring(0, 16);
                }
                return dateStr;
            },

            /**
             * Convert a Date object to YYYY-MM-DD string.
             */
            toDateString: function (date) {
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1);
                var day = String(date.getDate());
                if (month.length < 2) month = '0' + month;
                if (day.length < 2) day = '0' + day;
                return year + '-' + month + '-' + day;
            }
        };
    });
});
