/**
 * Venezia Hotel Manager - Booking Form Component
 *
 * Alpine.js component for the guest details and booking confirmation form.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('bookingForm', function () {
        return {
            step: 'details', // details, review, processing, confirmed
            guest: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                nationality: '',
                special_requests: '',
                arrival_time: ''
            },
            errors: {},
            loading: false,
            booking: null,

            get selection() {
                return Alpine.store('booking').selection;
            },

            get hasSelection() {
                return this.selection !== null;
            },

            init: function () {
                if (!this.hasSelection) {
                    this.step = 'details';
                }
            },

            validateDetails: function () {
                this.errors = {};

                if (!this.guest.first_name.trim()) {
                    this.errors.first_name = VeneziaI18n.t('first_name') + ' is required';
                }
                if (!this.guest.last_name.trim()) {
                    this.errors.last_name = VeneziaI18n.t('last_name') + ' is required';
                }
                if (!this.guest.email.trim() || !this.guest.email.includes('@')) {
                    this.errors.email = VeneziaI18n.t('email') + ' is required';
                }
                if (!this.guest.phone.trim()) {
                    this.errors.phone = VeneziaI18n.t('phone') + ' is required';
                }

                return Object.keys(this.errors).length === 0;
            },

            goToReview: function () {
                if (this.validateDetails()) {
                    this.step = 'review';
                }
            },

            goBack: function () {
                if (this.step === 'review') {
                    this.step = 'details';
                }
            },

            submitBooking: function () {
                if (!this.hasSelection) return;

                var self = this;
                self.step = 'processing';
                self.loading = true;

                var data = {
                    room_type_id: self.selection.roomType.id,
                    rate_plan_id: self.selection.ratePlan ? self.selection.ratePlan.id : null,
                    check_in: self.selection.checkIn,
                    check_out: self.selection.checkOut,
                    adults: self.selection.adults,
                    children: self.selection.children,
                    guest: {
                        first_name: self.guest.first_name,
                        last_name: self.guest.last_name,
                        email: self.guest.email,
                        phone: self.guest.phone,
                        nationality: self.guest.nationality
                    },
                    special_requests: self.guest.special_requests,
                    arrival_time: self.guest.arrival_time,
                    source: 'direct'
                };

                VeneziaAPI.post('/bookings', data).then(function (response) {
                    self.booking = response.data;
                    Alpine.store('booking').setConfirmation(response.data);
                    Alpine.store('booking').selection = null;
                    self.step = 'confirmed';
                }).catch(function (err) {
                    self.step = 'review';
                    self.errors.submit = err.message;
                }).finally(function () {
                    self.loading = false;
                });
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
