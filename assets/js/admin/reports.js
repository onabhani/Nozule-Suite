/**
 * Venezia Hotel Manager - Admin Reports
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmReports', function () {
        return {
            activeReport: 'occupancy',
            dateFrom: VeneziaUtils.dateOffset(-30),
            dateTo: VeneziaUtils.today(),
            roomTypeId: '',
            groupBy: 'daily',
            loading: false,
            reportData: null,
            error: null,

            reports: [
                { id: 'occupancy', name: 'Occupancy Report' },
                { id: 'revenue', name: 'Revenue Report' },
                { id: 'sources', name: 'Booking Sources' },
                { id: 'guests', name: 'Guest Statistics' },
                { id: 'forecast', name: 'Forecast' },
                { id: 'cancellations', name: 'Cancellations' }
            ],

            init: function () {
                this.loadReport();
            },

            loadReport: function () {
                var self = this;
                self.loading = true;
                self.error = null;

                var params = {
                    start_date: self.dateFrom,
                    end_date: self.dateTo
                };
                if (self.roomTypeId) params.room_type_id = self.roomTypeId;
                if (self.activeReport === 'revenue') params.group_by = self.groupBy;

                VeneziaAPI.get('/admin/reports/' + self.activeReport, params).then(function (response) {
                    self.reportData = response.data;
                }).catch(function (err) {
                    self.error = err.message;
                }).finally(function () {
                    self.loading = false;
                });
            },

            switchReport: function (reportId) {
                this.activeReport = reportId;
                this.reportData = null;
                this.loadReport();
            },

            exportReport: function (format) {
                var self = this;
                format = format || 'csv';
                self.loading = true;

                VeneziaAPI.post('/admin/reports/export', {
                    report: self.activeReport,
                    format: format,
                    start_date: self.dateFrom,
                    end_date: self.dateTo,
                    room_type_id: self.roomTypeId
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
