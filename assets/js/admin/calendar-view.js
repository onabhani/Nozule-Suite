/**
 * Venezia Hotel Manager - Admin Calendar View
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('calendarView', function () {
        return {
            currentDate: new Date(),
            viewDays: 14,
            roomTypes: [],
            bookings: [],
            loading: true,

            get dateRange() {
                var dates = [];
                var start = new Date(this.currentDate);
                for (var i = 0; i < this.viewDays; i++) {
                    var d = new Date(start);
                    d.setDate(d.getDate() + i);
                    dates.push({
                        date: d.toISOString().split('T')[0],
                        dayName: d.toLocaleDateString(undefined, { weekday: 'short' }),
                        dayNum: d.getDate(),
                        isToday: d.toISOString().split('T')[0] === VeneziaUtils.today(),
                        isWeekend: d.getDay() === 0 || d.getDay() === 6
                    });
                }
                return dates;
            },

            get startDate() {
                return this.dateRange[0].date;
            },

            get endDate() {
                return this.dateRange[this.dateRange.length - 1].date;
            },

            init: function () {
                this.loadCalendar();
            },

            loadCalendar: function () {
                var self = this;
                self.loading = true;

                VeneziaAPI.get('/admin/calendar', {
                    start_date: self.startDate,
                    end_date: self.endDate
                }).then(function (response) {
                    self.roomTypes = response.data.room_types || [];
                    self.bookings = response.data.bookings || [];
                }).catch(function (err) {
                    console.error('Calendar error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            prevPeriod: function () {
                this.currentDate.setDate(this.currentDate.getDate() - this.viewDays);
                this.currentDate = new Date(this.currentDate);
                this.loadCalendar();
            },

            nextPeriod: function () {
                this.currentDate.setDate(this.currentDate.getDate() + this.viewDays);
                this.currentDate = new Date(this.currentDate);
                this.loadCalendar();
            },

            goToToday: function () {
                this.currentDate = new Date();
                this.loadCalendar();
            },

            setViewDays: function (days) {
                this.viewDays = days;
                this.loadCalendar();
            },

            getBookingsForRoomDate: function (roomTypeId, date) {
                return this.bookings.filter(function (b) {
                    return b.room_type_id == roomTypeId &&
                        b.check_in <= date &&
                        b.check_out > date &&
                        b.status !== 'cancelled';
                });
            },

            getCellClass: function (roomTypeId, date) {
                var bookings = this.getBookingsForRoomDate(roomTypeId, date);
                if (bookings.length === 0) return 'bg-green-50';

                var statuses = bookings.map(function (b) { return b.status; });
                if (statuses.indexOf('checked_in') > -1) return 'bg-blue-100';
                if (statuses.indexOf('confirmed') > -1) return 'bg-yellow-100';
                if (statuses.indexOf('pending') > -1) return 'bg-orange-50';
                return 'bg-gray-100';
            },

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            }
        };
    });
});
