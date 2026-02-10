/**
 * Venezia Hotel Manager - Admin Rooms
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmRooms', function () {
        return {
            loading: true,
            activeTab: 'types',
            roomTypes: [],
            rooms: [],

            init: function () {
                this.loadData();
            },

            loadData: function () {
                var self = this;
                self.loading = true;

                Promise.all([
                    VeneziaAPI.get('/admin/room-types'),
                    VeneziaAPI.get('/admin/rooms')
                ]).then(function (responses) {
                    self.roomTypes = responses[0].data || [];
                    self.rooms = responses[1].data || [];
                }).catch(function (err) {
                    console.error('Rooms load error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            openCreateModal: function (type) {
                if (type === 'room_type') {
                    var name = prompt('Room Type Name:');
                    if (!name) return;
                    var basePrice = prompt('Base Price per Night:');
                    if (!basePrice) return;
                    var maxOccupancy = prompt('Max Occupancy:');

                    var self = this;
                    VeneziaAPI.post('/admin/room-types', {
                        name: name,
                        base_price: parseFloat(basePrice),
                        max_occupancy: parseInt(maxOccupancy) || 2,
                        status: 'active'
                    }).then(function () {
                        self.loadData();
                        VeneziaUtils.toast('Room type created', 'success');
                    }).catch(function (err) {
                        VeneziaUtils.toast(err.message, 'error');
                    });
                } else if (type === 'room') {
                    if (this.roomTypes.length === 0) {
                        VeneziaUtils.toast('Please create a room type first', 'error');
                        return;
                    }
                    var roomNumber = prompt('Room Number:');
                    if (!roomNumber) return;
                    var typeNames = this.roomTypes.map(function (t) { return t.id + ': ' + t.name; }).join('\n');
                    var typeId = prompt('Room Type ID:\n' + typeNames);
                    if (!typeId) return;
                    var floor = prompt('Floor Number (optional):');

                    var self = this;
                    VeneziaAPI.post('/admin/rooms', {
                        room_number: roomNumber,
                        room_type_id: parseInt(typeId),
                        floor: floor || null,
                        status: 'available'
                    }).then(function () {
                        self.loadData();
                        VeneziaUtils.toast('Room created', 'success');
                    }).catch(function (err) {
                        VeneziaUtils.toast(err.message, 'error');
                    });
                }
            },

            editRoomType: function (id) {
                var type = this.roomTypes.find(function (t) { return t.id === id; });
                if (!type) return;
                var name = prompt('Room Type Name:', type.name);
                if (name === null) return;
                var basePrice = prompt('Base Price:', type.base_price);
                if (basePrice === null) return;

                var self = this;
                VeneziaAPI.put('/admin/room-types/' + id, {
                    name: name,
                    base_price: parseFloat(basePrice)
                }).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room type updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            deleteRoomType: function (id) {
                if (!confirm('Are you sure you want to delete this room type?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/room-types/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room type deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            editRoom: function (id) {
                var room = this.rooms.find(function (r) { return r.id === id; });
                if (!room) return;
                var roomNumber = prompt('Room Number:', room.room_number);
                if (roomNumber === null) return;
                var status = prompt('Status (available, occupied, maintenance, out_of_order):', room.status);
                if (status === null) return;

                var self = this;
                VeneziaAPI.put('/admin/rooms/' + id, {
                    room_number: roomNumber,
                    status: status
                }).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            deleteRoom: function (id) {
                if (!confirm('Are you sure you want to delete this room?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/rooms/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            }
        };
    });
});
