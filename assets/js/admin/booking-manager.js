/**
 * Venezia Hotel Manager - Admin Booking Manager
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmBookingManager', function () {
        return {
            bookings: [],
            currentPage: 1,
            totalPages: 1,
            filters: {
                status: '',
                from: '',
                to: '',
                search: ''
            },
            loading: true,
            selectedBooking: null,
            showDetail: false,
            showPaymentForm: false,
            paymentData: { amount: '', method: 'cash', notes: '' },
            bookingLogs: [],
            showCreateModal: false,
            roomTypes: [],
            bookingForm: {},

            init: function () {
                this.loadBookings();
            },

            loadBookings: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.currentPage,
                    per_page: 20
                };

                if (self.filters.status) params.status = self.filters.status;
                if (self.filters.from) params.date_from = self.filters.from;
                if (self.filters.to) params.date_to = self.filters.to;
                if (self.filters.search) params.search = self.filters.search;

                VeneziaAPI.get('/admin/bookings', params).then(function (response) {
                    self.bookings = response.data.items || response.data || [];
                    if (response.data.pagination) {
                        self.currentPage = response.data.pagination.page || 1;
                        self.totalPages = response.data.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            defaultBookingForm: function () {
                return {
                    guest_first_name: '',
                    guest_last_name: '',
                    guest_email: '',
                    guest_phone: '',
                    room_type_id: '',
                    check_in: '',
                    check_out: '',
                    adults: 1,
                    children: 0,
                    notes: '',
                    status: 'pending'
                };
            },

            openCreateModal: function () {
                this.bookingForm = this.defaultBookingForm();
                this.showCreateModal = true;
                if (this.roomTypes.length === 0) {
                    this.loadRoomTypes();
                }
            },

            loadRoomTypes: function () {
                var self = this;
                VeneziaAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.data.items || response.data || [];
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            saveBooking: function () {
                var self = this;
                VeneziaAPI.post('/admin/bookings', self.bookingForm).then(function () {
                    self.showCreateModal = false;
                    self.loadBookings();
                    VeneziaUtils.toast('Booking created successfully', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadBookings();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadBookings();
                }
            },

            viewBooking: function (id) {
                var self = this;
                self.showDetail = true;

                VeneziaAPI.get('/admin/bookings/' + id).then(function (response) {
                    self.selectedBooking = response.data;
                });

                VeneziaAPI.get('/admin/bookings/' + id + '/logs').then(function (response) {
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

            checkIn: function (id) {
                var self = this;
                VeneziaAPI.post('/admin/bookings/' + id + '/check-in').then(function () {
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
