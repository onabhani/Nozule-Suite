/**
 * Venezia Hotel Manager - Admin Calendar View
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmCalendarView', function () {
        return {
            currentDate: new Date(),
            viewMode: '2week',
            dates: [],
            rooms: [],
            bookings: [],
            loading: true,

            get periodLabel() {
                if (this.dates.length === 0) return '';
                var config = window.VeneziaAdmin || window.VeneziaConfig || {};
                var locale = config.locale || 'en-US';
                var first = new Date(this.dates[0] + 'T00:00:00');
                var last = new Date(this.dates[this.dates.length - 1] + 'T00:00:00');
                return first.toLocaleDateString(locale, { month: 'short', day: 'numeric' }) +
                    ' - ' +
                    last.toLocaleDateString(locale, { month: 'short', day: 'numeric', year: 'numeric' });
            },

            get viewDays() {
                if (this.viewMode === 'week') return 7;
                if (this.viewMode === 'month') return 30;
                return 14;
            },

            init: function () {
                this.buildDates();
                this.loadCalendar();
            },

            buildDates: function () {
                var dates = [];
                var start = new Date(this.currentDate);
                for (var i = 0; i < this.viewDays; i++) {
                    var d = new Date(start);
                    d.setDate(d.getDate() + i);
                    dates.push(d.toISOString().split('T')[0]);
                }
                this.dates = dates;
            },

            loadCalendar: function () {
                var self = this;
                self.buildDates();
                self.loading = true;

                var startDate = self.dates[0];
                var endDate = self.dates[self.dates.length - 1];

                VeneziaAPI.get('/admin/calendar', {
                    start_date: startDate,
                    end_date: endDate
                }).then(function (response) {
                    self.rooms = response.data.rooms || response.data.room_types || [];
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

            isToday: function (dateStr) {
                return dateStr === VeneziaUtils.today();
            },

            formatDayName: function (dateStr) {
                var date = new Date(dateStr + 'T00:00:00');
                return date.toLocaleDateString(undefined, { weekday: 'short' });
            },

            formatDayNum: function (dateStr) {
                var date = new Date(dateStr + 'T00:00:00');
                return date.getDate();
            },

            getBookingForCell: function (roomId, date) {
                return this.bookings.find(function (b) {
                    return (b.room_id == roomId || b.room_type_id == roomId) &&
                        b.check_in <= date &&
                        b.check_out > date &&
                        b.status !== 'cancelled';
                }) || null;
            },

            getBookingLabel: function (roomId, date) {
                var booking = this.getBookingForCell(roomId, date);
                if (!booking) return '';
                return booking.guest_name || booking.booking_number || '';
            },

            getCellClass: function (roomId, date) {
                var booking = this.getBookingForCell(roomId, date);
                if (!booking) return 'bg-green-50';

                if (booking.status === 'checked_in') return 'bg-blue-100';
                if (booking.status === 'confirmed') return 'bg-yellow-100';
                if (booking.status === 'pending') return 'bg-orange-50';
                return 'bg-gray-100';
            },

            onCellClick: function (roomId, date) {
                var booking = this.getBookingForCell(roomId, date);
                if (booking) {
                    var config = window.VeneziaAdmin || window.VeneziaConfig || {};
                    if (config.adminUrl) {
                        window.location.href = config.adminUrl + 'admin.php?page=vhm-bookings&booking_id=' + booking.id;
                    }
                }
            },

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            }
        };
    });
});
