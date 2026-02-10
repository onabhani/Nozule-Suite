/**
 * Venezia Hotel Manager - Admin Reports
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmReports', function () {
        return {
            reportType: 'revenue',
            period: 'month',
            customFrom: VeneziaUtils.dateOffset(-30),
            customTo: VeneziaUtils.today(),
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

                VeneziaAPI.get('/admin/reports/' + self.reportType, params).then(function (response) {
                    var data = response.data || {};
                    self.reportData = data.rows || data.data || [];
                    self.summaryCards = data.summary || [];
                    self.chartTitle = self.getChartTitle();
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to load report', 'error');
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
                        start = end = VeneziaUtils.today();
                        break;
                    case 'week':
                        start = VeneziaUtils.dateOffset(-7);
                        end = VeneziaUtils.today();
                        break;
                    case 'month':
                        start = VeneziaUtils.dateOffset(-30);
                        end = VeneziaUtils.today();
                        break;
                    case 'quarter':
                        start = VeneziaUtils.dateOffset(-90);
                        end = VeneziaUtils.today();
                        break;
                    case 'year':
                        start = VeneziaUtils.dateOffset(-365);
                        end = VeneziaUtils.today();
                        break;
                    case 'custom':
                        start = this.customFrom;
                        end = this.customTo;
                        break;
                    default:
                        start = VeneziaUtils.dateOffset(-30);
                        end = VeneziaUtils.today();
                }

                return { start_date: start, end_date: end };
            },

            getChartTitle: function () {
                var titles = {
                    'revenue': 'Revenue Report',
                    'occupancy': 'Occupancy Report',
                    'sources': 'Booking Sources'
                };
                return titles[this.reportType] || 'Report';
            },

            exportReport: function () {
                var self = this;
                var params = self.getDateParams();
                self.loading = true;

                VeneziaAPI.post('/admin/reports/export', {
                    report: self.reportType,
                    format: 'csv',
                    start_date: params.start_date,
                    end_date: params.end_date
                }).then(function (response) {
                    if (response.data && response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            },

            formatPercent: function (value) {
                return parseFloat(value).toFixed(1) + '%';
            }
        };
    });
});
