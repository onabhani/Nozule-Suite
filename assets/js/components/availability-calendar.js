/**
 * Venezia Hotel Manager - Availability Calendar Component
 *
 * Visual calendar showing room availability.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('availabilityCalendar', function () {
        return {
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),
            availability: {},
            loading: false,
            selectedCheckIn: null,
            selectedCheckOut: null,

            get monthName() {
                var date = new Date(this.currentYear, this.currentMonth, 1);
                return date.toLocaleDateString(VeneziaI18n.getLocale(), {
                    month: 'long',
                    year: 'numeric'
                });
            },

            get daysInMonth() {
                return new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
            },

            get firstDayOfWeek() {
                return new Date(this.currentYear, this.currentMonth, 1).getDay();
            },

            get calendarDays() {
                var days = [];
                var total = this.daysInMonth;
                var firstDay = this.firstDayOfWeek;

                // Empty slots before first day
                for (var i = 0; i < firstDay; i++) {
                    days.push({ day: null, date: null });
                }

                // Actual days
                for (var d = 1; d <= total; d++) {
                    var dateStr = this.currentYear + '-' +
                        String(this.currentMonth + 1).padStart(2, '0') + '-' +
                        String(d).padStart(2, '0');
                    days.push({
                        day: d,
                        date: dateStr,
                        available: this.availability[dateStr] || 0,
                        isPast: dateStr < VeneziaUtils.today(),
                        isSelected: dateStr === this.selectedCheckIn || dateStr === this.selectedCheckOut,
                        isInRange: this.isInSelectedRange(dateStr)
                    });
                }

                return days;
            },

            init: function () {
                this.loadAvailability();
            },

            prevMonth: function () {
                if (this.currentMonth === 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                } else {
                    this.currentMonth--;
                }
                this.loadAvailability();
            },

            nextMonth: function () {
                if (this.currentMonth === 11) {
                    this.currentMonth = 0;
                    this.currentYear++;
                } else {
                    this.currentMonth++;
                }
                this.loadAvailability();
            },

            loadAvailability: function () {
                var self = this;
                var startDate = self.currentYear + '-' +
                    String(self.currentMonth + 1).padStart(2, '0') + '-01';
                var endDate = self.currentYear + '-' +
                    String(self.currentMonth + 1).padStart(2, '0') + '-' +
                    String(self.daysInMonth).padStart(2, '0');

                self.loading = true;
                VeneziaAPI.get('/availability', {
                    check_in: startDate,
                    check_out: endDate
                }).then(function (response) {
                    if (response.data && response.data.calendar) {
                        self.availability = response.data.calendar;
                    }
                }).catch(function () {
                    // Silently fail
                }).finally(function () {
                    self.loading = false;
                });
            },

            selectDate: function (dateStr) {
                if (!dateStr || dateStr < VeneziaUtils.today()) return;

                if (!this.selectedCheckIn || this.selectedCheckOut) {
                    this.selectedCheckIn = dateStr;
                    this.selectedCheckOut = null;
                } else if (dateStr > this.selectedCheckIn) {
                    this.selectedCheckOut = dateStr;
                    this.$dispatch('dates-selected', {
                        checkIn: this.selectedCheckIn,
                        checkOut: this.selectedCheckOut
                    });
                } else {
                    this.selectedCheckIn = dateStr;
                    this.selectedCheckOut = null;
                }
            },

            isInSelectedRange: function (dateStr) {
                if (!this.selectedCheckIn || !this.selectedCheckOut) return false;
                return dateStr > this.selectedCheckIn && dateStr < this.selectedCheckOut;
            }
        };
    });
});
