/**
 * Nozule - Booking Search Widget
 *
 * Alpine.js component for searching room availability.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('bookingWidget', function () {
        return {
            checkIn: '',
            checkOut: '',
            adults: 2,
            children: 0,
            loading: false,
            error: null,
            results: null,

            get nights() {
                if (!this.checkIn || !this.checkOut) return 0;
                return NozuleUtils.calculateNights(this.checkIn, this.checkOut);
            },

            get canSearch() {
                return this.checkIn && this.checkOut && this.nights > 0;
            },

            get minCheckIn() {
                return NozuleUtils.today();
            },

            get minCheckOut() {
                if (!this.checkIn) return NozuleUtils.dateOffset(1);
                var d = new Date(this.checkIn);
                d.setDate(d.getDate() + 1);
                return d.toISOString().split('T')[0];
            },

            init: function () {
                var self = this;
                this.$watch('checkIn', function (val) {
                    if (val && (!self.checkOut || self.checkOut <= val)) {
                        var next = new Date(val);
                        next.setDate(next.getDate() + 1);
                        self.checkOut = next.toISOString().split('T')[0];
                    }
                });
            },

            search: function () {
                if (!this.canSearch) return;

                var self = this;
                self.loading = true;
                self.error = null;

                NozuleAPI.get('/availability', {
                    check_in: self.checkIn,
                    check_out: self.checkOut,
                    adults: self.adults,
                    children: self.children
                }).then(function (response) {
                    self.results = response.data;
                }).catch(function (err) {
                    self.error = err.message;
                }).finally(function () {
                    self.loading = false;
                });
            },

            selectRoom: function (roomType, ratePlan) {
                Alpine.store('booking').setSelection({
                    checkIn: this.checkIn,
                    checkOut: this.checkOut,
                    nights: this.nights,
                    adults: this.adults,
                    children: this.children,
                    roomType: roomType,
                    ratePlan: ratePlan
                });

                this.$dispatch('room-selected');
            },

            formatPrice: function (amount) {
                return NozuleUtils.formatPrice(amount);
            }
        };
    });
});
