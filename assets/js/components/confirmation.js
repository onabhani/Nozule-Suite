/**
 * Venezia Hotel Manager - Booking Confirmation Component
 *
 * Displays booking confirmation details and allows lookup.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('bookingConfirmation', function () {
        return {
            bookingNumber: '',
            email: '',
            booking: null,
            loading: false,
            error: null,
            showCancelForm: false,
            cancelReason: '',

            init: function () {
                // Check if there's a confirmation in the store
                var confirmation = Alpine.store('booking').confirmation;
                if (confirmation) {
                    this.booking = confirmation;
                }

                // Check URL params
                var params = VeneziaUtils.getUrlParams();
                if (params.booking) {
                    this.bookingNumber = params.booking;
                    if (params.email) {
                        this.email = params.email;
                        this.lookupBooking();
                    }
                }
            },

            lookupBooking: function () {
                if (!this.bookingNumber || !this.email) return;

                var self = this;
                self.loading = true;
                self.error = null;

                VeneziaAPI.get('/bookings/' + encodeURIComponent(self.bookingNumber), {
                    email: self.email
                }).then(function (response) {
                    self.booking = response.data;
                }).catch(function (err) {
                    self.error = err.message;
                    self.booking = null;
                }).finally(function () {
                    self.loading = false;
                });
            },

            cancelBooking: function () {
                if (!this.booking || !this.cancelReason) return;

                var self = this;
                self.loading = true;

                VeneziaAPI.post('/bookings/' + encodeURIComponent(self.booking.booking_number) + '/cancel', {
                    email: self.email || self.booking.guest_email,
                    reason: self.cancelReason
                }).then(function (response) {
                    self.booking = response.data;
                    self.showCancelForm = false;
                    self.cancelReason = '';
                    VeneziaUtils.toast(VeneziaI18n.t('booking_cancelled'), 'success');
                }).catch(function (err) {
                    self.error = err.message;
                }).finally(function () {
                    self.loading = false;
                });
            },

            getStatusClass: function (status) {
                var classes = {
                    'pending': 'bg-yellow-100 text-yellow-800',
                    'confirmed': 'bg-green-100 text-green-800',
                    'checked_in': 'bg-blue-100 text-blue-800',
                    'checked_out': 'bg-gray-100 text-gray-800',
                    'cancelled': 'bg-red-100 text-red-800',
                    'no_show': 'bg-red-100 text-red-800'
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
