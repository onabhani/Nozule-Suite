/**
 * Nozule - Admin Dashboard
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlDashboard', function () {
        return {
            stats: null,
            arrivals: [],
            departures: [],
            inHouse: [],
            loading: true,
            activeTab: 'arrivals',
            eventSource: null,

            init: function () {
                this.loadData();
                this.initSSE();
            },

            loadData: function () {
                var self = this;
                self.loading = true;

                Promise.all([
                    NozuleAPI.get('/admin/dashboard/stats'),
                    NozuleAPI.get('/admin/dashboard/arrivals'),
                    NozuleAPI.get('/admin/dashboard/departures'),
                    NozuleAPI.get('/admin/dashboard/in-house')
                ]).then(function (responses) {
                    self.stats = responses[0].data;
                    self.arrivals = responses[1].data || [];
                    self.departures = responses[2].data || [];
                    self.inHouse = responses[3].data || [];
                }).catch(function (err) {
                    console.error('Dashboard load error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            initSSE: function () {
                var self = this;
                var config = window.NozuleAdmin || window.NozuleConfig || {};
                if (!config.apiBase) return;

                try {
                    self.eventSource = new EventSource(
                        config.apiBase + '/admin/events/stream?_wpnonce=' + config.nonce
                    );

                    self.eventSource.addEventListener('new_booking', function (e) {
                        var data = JSON.parse(e.data);
                        self.loadData();
                        Alpine.store('notifications').add({
                            id: NozuleUtils.uniqueId(),
                            message: 'New booking: ' + data.booking_number,
                            type: 'info',
                            persistent: true
                        });
                    });

                    self.eventSource.addEventListener('booking_cancelled', function (e) {
                        self.loadData();
                    });

                    self.eventSource.addEventListener('check_in', function (e) {
                        self.loadData();
                    });

                    self.eventSource.addEventListener('check_out', function (e) {
                        self.loadData();
                    });

                    self.eventSource.onerror = function () {
                        self.eventSource.close();
                        // Reconnect after 30 seconds
                        setTimeout(function () {
                            self.initSSE();
                        }, 30000);
                    };
                } catch (e) {
                    // SSE not supported, fall back to polling
                    setInterval(function () { self.loadData(); }, 60000);
                }
            },

            confirmBooking: function (id) {
                var self = this;
                NozuleAPI.post('/admin/bookings/' + id + '/confirm').then(function () {
                    self.loadData();
                    NozuleUtils.toast('Booking confirmed', 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message, 'error');
                });
            },

            checkIn: function (id) {
                var self = this;
                NozuleAPI.post('/admin/bookings/' + id + '/check-in').then(function () {
                    self.loadData();
                    NozuleUtils.toast('Guest checked in', 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message, 'error');
                });
            },

            checkOut: function (id) {
                var self = this;
                NozuleAPI.post('/admin/bookings/' + id + '/check-out').then(function () {
                    self.loadData();
                    NozuleUtils.toast('Guest checked out', 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message, 'error');
                });
            },

            formatPrice: function (amount) {
                return NozuleUtils.formatPrice(amount);
            },

            formatDate: function (date) {
                return NozuleUtils.formatDate(date);
            },

            destroy: function () {
                if (this.eventSource) {
                    this.eventSource.close();
                }
            }
        };
    });
});
