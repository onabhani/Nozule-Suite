/**
 * Venezia Hotel Manager - Admin Dashboard
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmDashboard', function () {
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
                    VeneziaAPI.get('/admin/dashboard/stats'),
                    VeneziaAPI.get('/admin/dashboard/arrivals'),
                    VeneziaAPI.get('/admin/dashboard/departures'),
                    VeneziaAPI.get('/admin/dashboard/in-house')
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
                var config = window.VeneziaAdmin || window.VeneziaConfig || {};
                if (!config.apiBase) return;

                try {
                    self.eventSource = new EventSource(
                        config.apiBase + '/admin/events/stream?_wpnonce=' + config.nonce
                    );

                    self.eventSource.addEventListener('new_booking', function (e) {
                        var data = JSON.parse(e.data);
                        self.loadData();
                        Alpine.store('notifications').add({
                            id: VeneziaUtils.uniqueId(),
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
                VeneziaAPI.post('/admin/bookings/' + id + '/confirm').then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Booking confirmed', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            checkIn: function (id) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/check-in').then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Guest checked in', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            checkOut: function (id) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/check-out').then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Guest checked out', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            },

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            },

            destroy: function () {
                if (this.eventSource) {
                    this.eventSource.close();
                }
            }
        };
    });
});
