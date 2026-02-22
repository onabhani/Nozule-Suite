/**
 * Nozule - Admin Demand Forecasting
 *
 * AI-powered demand forecasting component with occupancy predictions,
 * rate suggestions, and confidence indicators.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlForecasting', function () {
        return {
            loading: true,
            generating: false,
            forecasts: [],
            roomTypes: [],
            selectedRoomType: '',
            dateFrom: NozuleUtils.dateOffset(1),
            dateTo: NozuleUtils.dateOffset(30),
            summary: {
                avg_occupancy: 0,
                avg_suggested_rate: 0,
                avg_confidence: 0,
                forecast_count: 0,
                date_from: '',
                date_to: ''
            },

            // Factors modal
            showFactorsModal: false,
            selectedFactors: {},

            // Day names
            dayLabels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],

            init: function () {
                this.loadRoomTypes();
                this.loadForecasts();
                this.loadSummary();
            },

            // ---- Data Loading ----

            loadRoomTypes: function () {
                var self = this;
                NozuleAPI.get('/admin/forecasting/room-types').then(function (response) {
                    self.roomTypes = response.data || [];
                }).catch(function () {
                    // Non-critical
                });
            },

            loadForecasts: function () {
                var self = this;
                self.loading = true;

                var params = {
                    date_from: self.dateFrom,
                    date_to: self.dateTo
                };

                if (self.selectedRoomType) {
                    params.room_type_id = self.selectedRoomType;
                }

                NozuleAPI.get('/admin/forecasting/data', params).then(function (response) {
                    self.forecasts = response.data || [];
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                    self.forecasts = [];
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadSummary: function () {
                var self = this;
                var params = {};

                if (self.selectedRoomType) {
                    params.room_type_id = self.selectedRoomType;
                }

                NozuleAPI.get('/admin/forecasting/summary', params).then(function (response) {
                    self.summary = response.data || {
                        avg_occupancy: 0,
                        avg_suggested_rate: 0,
                        avg_confidence: 0,
                        forecast_count: 0,
                        date_from: '',
                        date_to: ''
                    };
                }).catch(function () {
                    // Non-critical
                });
            },

            // ---- Actions ----

            generateForecast: function () {
                var self = this;
                self.generating = true;

                NozuleAPI.post('/admin/forecasting/generate', {}).then(function (response) {
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('forecasts_generated'),
                        'success'
                    );
                    self.loadForecasts();
                    self.loadSummary();
                }).catch(function (err) {
                    NozuleUtils.toast(
                        err.message || NozuleI18n.t('failed_generate_forecasts'),
                        'error'
                    );
                }).finally(function () {
                    self.generating = false;
                });
            },

            showFactors: function (forecast) {
                this.selectedFactors = forecast.factors || {};
                this.showFactorsModal = true;
            },

            // ---- Formatting Helpers ----

            formatPercent: function (value) {
                return parseFloat(value || 0).toFixed(1) + '%';
            },

            formatConfidencePercent: function (value) {
                return (parseFloat(value || 0) * 100).toFixed(0) + '%';
            },

            formatPrice: function (amount) {
                if (typeof NozuleUtils !== 'undefined' && NozuleUtils.formatPrice) {
                    return NozuleUtils.formatPrice(amount);
                }
                return parseFloat(amount || 0).toFixed(2);
            },

            formatDate: function (date) {
                if (typeof NozuleUtils !== 'undefined' && NozuleUtils.formatDate) {
                    return NozuleUtils.formatDate(date);
                }
                return date || '';
            },

            getDayName: function (dateStr) {
                if (!dateStr) return '';
                var d = new Date(dateStr + 'T00:00:00');
                return this.dayLabels[d.getDay()] || '';
            },

            getRoomTypeName: function (roomTypeId) {
                if (!roomTypeId) return NozuleI18n.t('all_room_types');
                var found = null;
                for (var i = 0; i < this.roomTypes.length; i++) {
                    if (this.roomTypes[i].id == roomTypeId) {
                        found = this.roomTypes[i];
                        break;
                    }
                }
                return found ? found.name : NozuleI18n.t('room_type') + ' #' + roomTypeId;
            },

            // ---- Occupancy Color Coding ----

            getOccupancyColor: function (occupancy) {
                var pct = parseFloat(occupancy || 0);
                if (pct >= 70) return '#16a34a'; // green
                if (pct >= 40) return '#ca8a04'; // yellow
                return '#dc2626'; // red
            },

            // ---- Rate Change ----

            getRateChange: function (forecast) {
                var current = parseFloat(forecast.current_rate || 0);
                var suggested = parseFloat(forecast.suggested_rate || 0);
                if (current <= 0) return 0;
                return ((suggested - current) / current) * 100;
            },

            getRateChangeText: function (forecast) {
                var change = this.getRateChange(forecast);
                var sign = change >= 0 ? '+' : '';
                return sign + change.toFixed(1) + '%';
            },

            // ---- Confidence ----

            getConfidenceLabel: function (confidence) {
                var c = parseFloat(confidence || 0);
                var config = window.NozuleAdmin || window.NozuleConfig || {};
                var i18n = config.i18n || {};
                if (c >= 0.7) return i18n.confidence_high || 'High';
                if (c >= 0.4) return i18n.confidence_medium || 'Medium';
                return i18n.confidence_low || 'Low';
            },

            getConfidenceBadgeClass: function (confidence) {
                var c = parseFloat(confidence || 0);
                if (c >= 0.7) return 'nzl-badge-confirmed';  // green
                if (c >= 0.4) return 'nzl-badge-pending';     // yellow
                return 'nzl-badge-cancelled';                  // red
            },

            // ---- Factor Display ----

            formatFactorName: function (key) {
                var labels = {
                    'dow_factor': 'Day-of-Week Factor',
                    'trend_factor': 'Monthly Trend Factor',
                    'wma_base': 'Weighted Moving Avg',
                    'avg_occupancy': 'Historical Avg Occupancy',
                    'rate_factor': 'Rate Multiplier',
                    'horizon_days': 'Forecast Horizon (days)',
                    'data_points': 'Historical Data Points',
                    'note': 'Note'
                };
                return labels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, function (c) {
                    return c.toUpperCase();
                });
            }
        };
    });
});
