/**
 * Venezia Hotel Manager - Admin Inventory
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmInventory', function () {
        return {
            loading: true,
            roomTypes: [],
            dateRange: [],
            inventoryData: [],
            filters: {
                roomType: '',
                from: VeneziaUtils.today(),
                to: VeneziaUtils.dateOffset(14)
            },

            init: function () {
                this.loadRoomTypes();
                this.loadInventory();
            },

            loadRoomTypes: function () {
                var self = this;
                VeneziaAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.data || [];
                }).catch(function (err) {
                    console.error('Room types load error:', err);
                });
            },

            loadInventory: function () {
                var self = this;
                self.loading = true;

                var params = {
                    start_date: self.filters.from,
                    end_date: self.filters.to
                };
                if (self.filters.roomType) {
                    params.room_type_id = self.filters.roomType;
                }

                VeneziaAPI.get('/admin/inventory', params).then(function (response) {
                    self.inventoryData = response.data.inventory || [];
                    self.dateRange = response.data.dates || self.generateDateRange();
                }).catch(function (err) {
                    console.error('Inventory load error:', err);
                    self.dateRange = self.generateDateRange();
                    self.inventoryData = [];
                }).finally(function () {
                    self.loading = false;
                });
            },

            generateDateRange: function () {
                var dates = [];
                var start = new Date(this.filters.from);
                var end = new Date(this.filters.to);
                var current = new Date(start);
                while (current <= end) {
                    dates.push(current.toISOString().split('T')[0]);
                    current.setDate(current.getDate() + 1);
                }
                return dates;
            },

            formatShortDate: function (dateStr) {
                var date = new Date(dateStr + 'T00:00:00');
                return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            },

            getCellStyle: function (availability, totalRooms) {
                if (availability === undefined || availability === null) return '';
                totalRooms = totalRooms || 1;
                var ratio = availability / totalRooms;
                if (availability <= 0) return 'background-color:#fecaca; cursor:pointer;';
                if (ratio <= 0.3) return 'background-color:#fef3c7; cursor:pointer;';
                return 'background-color:#dcfce7; cursor:pointer;';
            },

            editInventoryCell: function (typeId, date) {
                var newCount = prompt('Set available rooms for ' + date + ':');
                if (newCount === null) return;

                var self = this;
                VeneziaAPI.post('/admin/inventory', {
                    room_type_id: typeId,
                    date: date,
                    available: parseInt(newCount)
                }).then(function () {
                    self.loadInventory();
                    VeneziaUtils.toast('Inventory updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            openBulkUpdateModal: function () {
                var typeId = prompt('Room Type ID (leave empty for all):');
                var startDate = prompt('Start Date (YYYY-MM-DD):', this.filters.from);
                if (!startDate) return;
                var endDate = prompt('End Date (YYYY-MM-DD):', this.filters.to);
                if (!endDate) return;
                var count = prompt('Available rooms count:');
                if (count === null) return;

                var self = this;
                VeneziaAPI.post('/admin/inventory/bulk', {
                    room_type_id: typeId ? parseInt(typeId) : null,
                    start_date: startDate,
                    end_date: endDate,
                    available: parseInt(count)
                }).then(function () {
                    self.loadInventory();
                    VeneziaUtils.toast('Bulk update complete', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            }
        };
    });
});
