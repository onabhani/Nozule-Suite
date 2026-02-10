/**
 * Venezia Hotel Manager - Admin Booking Manager
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('bookingManager', function () {
        return {
            bookings: [],
            pagination: { total: 0, page: 1, per_page: 20, total_pages: 0 },
            filters: {
                status: '',
                source: '',
                date_from: '',
                date_to: '',
                search: ''
            },
            loading: true,
            selectedBooking: null,
            showDetail: false,
            showPaymentForm: false,
            paymentData: { amount: '', method: 'cash', notes: '' },
            bookingLogs: [],

            init: function () {
                this.loadBookings();
            },

            loadBookings: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.pagination.page,
                    per_page: self.pagination.per_page
                };

                // Add active filters
                Object.keys(self.filters).forEach(function (key) {
                    if (self.filters[key]) {
                        params[key] = self.filters[key];
                    }
                });

                VeneziaAPI.get('/admin/bookings', params).then(function (response) {
                    self.bookings = response.data.items || [];
                    self.pagination = response.data.pagination || self.pagination;
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            applyFilters: function () {
                this.pagination.page = 1;
                this.loadBookings();
            },

            clearFilters: function () {
                this.filters = { status: '', source: '', date_from: '', date_to: '', search: '' };
                this.applyFilters();
            },

            goToPage: function (page) {
                this.pagination.page = page;
                this.loadBookings();
            },

            viewBooking: function (booking) {
                var self = this;
                self.selectedBooking = booking;
                self.showDetail = true;

                // Load logs
                VeneziaAPI.get('/admin/bookings/' + booking.id + '/logs').then(function (response) {
                    self.bookingLogs = response.data || [];
                });
            },

            closeDetail: function () {
                this.showDetail = false;
                this.selectedBooking = null;
                this.bookingLogs = [];
            },

            confirmBooking: function (id) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/confirm').then(function () {
                    self.loadBookings();
                    if (self.selectedBooking && self.selectedBooking.id === id) {
                        self.viewBooking({ id: id });
                    }
                    VeneziaUtils.toast('Booking confirmed', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            cancelBooking: function (id) {
                var reason = prompt('Please enter cancellation reason:');
                if (!reason) return;

                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/cancel', {
                    reason: reason
                }).then(function () {
                    self.loadBookings();
                    self.closeDetail();
                    VeneziaUtils.toast('Booking cancelled', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            checkIn: function (id, roomId) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/check-in', {
                    room_id: roomId || null
                }).then(function () {
                    self.loadBookings();
                    VeneziaUtils.toast('Guest checked in', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            checkOut: function (id) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/check-out').then(function () {
                    self.loadBookings();
                    VeneziaUtils.toast('Guest checked out', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            addPayment: function (id) {
                if (!this.paymentData.amount) return;

                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/payments', self.paymentData).then(function () {
                    self.showPaymentForm = false;
                    self.paymentData = { amount: '', method: 'cash', notes: '' };
                    self.loadBookings();
                    VeneziaUtils.toast('Payment recorded', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            getStatusClass: function (status) {
                var classes = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'confirmed': 'bg-green-100 text-green-800',
                    'checked_in': 'bg-blue-100 text-blue-800',
                    'checked_out': 'bg-gray-100 text-gray-800',
                    'cancelled': 'bg-red-100 text-red-800',
                    'no_show': 'bg-orange-100 text-orange-800',
                    'refunded': 'bg-purple-100 text-purple-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            },

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            }
        };
    });
});
