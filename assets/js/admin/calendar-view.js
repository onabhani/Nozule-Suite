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
            roomTypeMap: {},
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
                this.loadAll();
            },

            loadAll: function () {
                var self = this;
                self.loading = true;

                var startDate = self.dates[0];
                var endDate = self.dates[self.dates.length - 1];

                // Load room types, rooms, and calendar data in parallel
                var roomTypesP = VeneziaAPI.get('/admin/room-types').then(function (response) {
                    var types = response.data || [];
                    var map = {};
                    types.forEach(function (rt) {
                        map[rt.id] = rt.name;
                    });
                    self.roomTypeMap = map;
                    return types;
                }).catch(function () {
                    return [];
                });

                var roomsP = VeneziaAPI.get('/admin/rooms').then(function (response) {
                    return response.data || [];
                }).catch(function () {
                    return [];
                });

                var calendarP = VeneziaAPI.get('/admin/calendar', {
                    start: startDate,
                    end: endDate
                }).then(function (response) {
                    return response.data || [];
                }).catch(function (err) {
                    console.error('Calendar error:', err);
                    return [];
                });

                Promise.all([roomTypesP, roomsP, calendarP]).then(function (results) {
                    var roomTypes = results[0];
                    var roomsData = results[1];
                    var events = results[2];

                    // If we have individual rooms, use them; otherwise fall back to room types
                    if (roomsData.length > 0) {
                        self.rooms = roomsData.map(function (r) {
                            return {
                                id: r.id,
                                room_number: r.room_number || r.name || ('Room ' + r.id),
                                room_type_name: self.roomTypeMap[r.room_type_id] || ''
                            };
                        });
                    } else if (roomTypes.length > 0) {
                        // Fall back to room types as "rooms"
                        self.rooms = roomTypes.map(function (rt) {
                            return {
                                id: rt.id,
                                room_number: rt.name,
                                room_type_name: ''
                            };
                        });
                    } else {
                        self.rooms = [];
                    }

                    // Process bookings
                    self.bookings = events.map(function (e) {
                        return {
                            id: e.id,
                            booking_number: e.booking_number,
                            guest_name: e.guest_name || e.booking_number || '',
                            room_id: e.room_id,
                            room_type_id: e.room_type_id,
                            check_in: e.start || e.check_in,
                            check_out: e.end || e.check_out,
                            status: e.status
                        };
                    });
                }).finally(function () {
                    self.loading = false;
                });
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
                this.buildDates();
                this.loadAll();
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
                var config = window.VeneziaAdmin || window.VeneziaConfig || {};
                var locale = config.locale || undefined;
                var date = new Date(dateStr + 'T00:00:00');
                return date.toLocaleDateString(locale, { weekday: 'short' });
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
