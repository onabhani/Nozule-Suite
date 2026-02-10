/**
 * Venezia Hotel Manager - Admin Rooms
 *
 * Modal-based CRUD for room types and individual rooms.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmRooms', function () {
        return {
            loading: true,
            saving: false,
            activeTab: 'types',
            roomTypes: [],
            rooms: [],

            // Room Type modal state
            showRoomTypeModal: false,
            editingRoomTypeId: null,
            rtForm: {},

            // Room modal state
            showRoomModal: false,
            editingRoomId: null,
            roomForm: {},

            init: function () {
                this.loadData();
            },

            // ---- Default form values ----

            defaultRtForm: function () {
                return {
                    name: '',
                    base_price: '',
                    max_occupancy: 2,
                    description: '',
                    status: 'active'
                };
            },

            defaultRoomForm: function () {
                return {
                    room_number: '',
                    room_type_id: '',
                    floor: '',
                    status: 'available',
                    notes: ''
                };
            },

            // ---- Data loading ----

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
                    VeneziaUtils.toast(err.message || 'Failed to load rooms data', 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Room Type CRUD ----

            openRoomTypeModal: function () {
                this.editingRoomTypeId = null;
                this.rtForm = this.defaultRtForm();
                this.showRoomTypeModal = true;
            },

            editRoomType: function (type) {
                this.editingRoomTypeId = type.id;
                this.rtForm = {
                    name: type.name || '',
                    base_price: type.base_price || '',
                    max_occupancy: type.max_occupancy || 2,
                    description: type.description || '',
                    status: type.status || 'active'
                };
                this.showRoomTypeModal = true;
            },

            slugify: function (text) {
                return text.toString().toLowerCase().trim()
                    .replace(/[\s_]+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/--+/g, '-')
                    .replace(/^-+|-+$/g, '');
            },

            saveRoomType: function () {
                var self = this;
                var maxOcc = parseInt(self.rtForm.max_occupancy, 10) || 2;
                var data = {
                    name: self.rtForm.name,
                    slug: self.slugify(self.rtForm.name),
                    base_price: parseFloat(self.rtForm.base_price) || 0,
                    max_occupancy: maxOcc,
                    base_occupancy: maxOcc,
                    status: self.rtForm.status
                };

                if (self.rtForm.description) {
                    data.description = self.rtForm.description;
                }

                self.saving = true;

                var promise;
                if (self.editingRoomTypeId) {
                    promise = VeneziaAPI.put('/admin/room-types/' + self.editingRoomTypeId, data);
                } else {
                    promise = VeneziaAPI.post('/admin/room-types', data);
                }

                promise.then(function () {
                    self.showRoomTypeModal = false;
                    self.loadData();
                    VeneziaUtils.toast(
                        self.editingRoomTypeId ? 'Room type updated' : 'Room type created',
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to save room type', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRoomType: function (id) {
                if (!confirm('Are you sure you want to delete this room type?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/room-types/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room type deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to delete room type', 'error');
                });
            },

            // ---- Room CRUD ----

            openRoomModal: function () {
                if (this.roomTypes.length === 0) {
                    VeneziaUtils.toast('Please create a room type first', 'error');
                    return;
                }
                this.editingRoomId = null;
                this.roomForm = this.defaultRoomForm();
                this.showRoomModal = true;
            },

            editRoom: function (room) {
                this.editingRoomId = room.id;
                this.roomForm = {
                    room_number: room.room_number || '',
                    room_type_id: room.room_type_id || '',
                    floor: room.floor || '',
                    status: room.status || 'available',
                    notes: room.notes || ''
                };
                this.showRoomModal = true;
            },

            saveRoom: function () {
                var self = this;
                var data = {
                    room_number: self.roomForm.room_number,
                    room_type_id: parseInt(self.roomForm.room_type_id, 10) || 0,
                    status: self.roomForm.status
                };

                if (self.roomForm.floor !== '' && self.roomForm.floor != null) {
                    data.floor = self.roomForm.floor;
                }
                if (self.roomForm.notes) {
                    data.notes = self.roomForm.notes;
                }

                self.saving = true;

                var promise;
                if (self.editingRoomId) {
                    promise = VeneziaAPI.put('/admin/rooms/' + self.editingRoomId, data);
                } else {
                    promise = VeneziaAPI.post('/admin/rooms', data);
                }

                promise.then(function () {
                    self.showRoomModal = false;
                    self.loadData();
                    VeneziaUtils.toast(
                        self.editingRoomId ? 'Room updated' : 'Room created',
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to save room', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRoom: function (id) {
                if (!confirm('Are you sure you want to delete this room?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/rooms/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Room deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to delete room', 'error');
                });
            },

            // ---- Helpers ----

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            }
        };
    });
});
