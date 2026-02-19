/**
 * Nozule - Admin Reports
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlReports', function () {
        return {
            reportType: 'revenue',
            period: 'month',
            customFrom: NozuleUtils.dateOffset(-30),
            customTo: NozuleUtils.today(),
            loading: false,
            summaryCards: [],
            chartTitle: 'Revenue Report',
            reportData: [],

            init: function () {
                this.loadReport();
            },

            loadReport: function () {
                var self = this;
                self.loading = true;

                var params = self.getDateParams();

                NozuleAPI.get('/admin/reports/' + self.reportType, params).then(function (response) {
                    var data = response.data || {};
                    self.reportData = data.rows || data.data || [];
                    self.summaryCards = data.summary || [];
                    self.chartTitle = self.getChartTitle();
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || 'Failed to load report', 'error');
                    self.reportData = [];
                    self.summaryCards = [];
                }).finally(function () {
                    self.loading = false;
                });
            },

            getDateParams: function () {
                var start, end;

                switch (this.period) {
                    case 'today':
                        start = end = NozuleUtils.today();
                        break;
                    case 'week':
                        start = NozuleUtils.dateOffset(-7);
                        end = NozuleUtils.today();
                        break;
                    case 'month':
                        start = NozuleUtils.dateOffset(-30);
                        end = NozuleUtils.today();
                        break;
                    case 'quarter':
                        start = NozuleUtils.dateOffset(-90);
                        end = NozuleUtils.today();
                        break;
                    case 'year':
                        start = NozuleUtils.dateOffset(-365);
                        end = NozuleUtils.today();
                        break;
                    case 'custom':
                        start = this.customFrom;
                        end = this.customTo;
                        break;
                    default:
                        start = NozuleUtils.dateOffset(-30);
                        end = NozuleUtils.today();
                }

                return { start_date: start, end_date: end };
            },

            getChartTitle: function () {
                var config = window.NozuleAdmin || window.NozuleConfig || {};
                var i18n = config.i18n || {};
                var titles = {
                    'revenue': i18n.revenue_report || 'Revenue Report',
                    'occupancy': i18n.occupancy_report || 'Occupancy Report',
                    'sources': i18n.booking_sources || 'Booking Sources'
                };
                return titles[this.reportType] || (i18n.report || 'Report');
            },

            exportReport: function () {
                var self = this;
                var params = self.getDateParams();
                self.loading = true;

                NozuleAPI.post('/admin/reports/export', {
                    report: self.reportType,
                    format: 'csv',
                    start_date: params.start_date,
                    end_date: params.end_date
                }).then(function (response) {
                    if (response.data && response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }
                }).catch(function (err) {
                    NozuleUtils.toast(err.message, 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            formatPrice: function (amount) {
                return NozuleUtils.formatPrice(amount);
            },

            formatPercent: function (value) {
                return parseFloat(value).toFixed(1) + '%';
            }
        };
    });
});
