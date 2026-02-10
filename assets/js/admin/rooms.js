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
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_load_rooms'), 'error');
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
                var slug = text.toString().toLowerCase().trim()
                    .replace(/[\s_]+/g, '-')
                    .replace(/[^\w\-]+/g, '')
                    .replace(/--+/g, '-')
                    .replace(/^-+|-+$/g, '');
                // Fallback for non-Latin names (e.g. Arabic)
                if (!slug) {
                    slug = 'room-type-' + Date.now();
                }
                return slug;
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
                        VeneziaI18n.t(self.editingRoomTypeId ? 'room_type_updated' : 'room_type_created'),
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_save_room_type'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRoomType: function (id) {
                if (!confirm(VeneziaI18n.t('confirm_delete_room_type'))) return;
                var self = this;
                VeneziaAPI.delete('/admin/room-types/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast(VeneziaI18n.t('room_type_deleted'), 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_delete_room_type'), 'error');
                });
            },

            // ---- Room CRUD ----

            openRoomModal: function () {
                if (this.roomTypes.length === 0) {
                    VeneziaUtils.toast(VeneziaI18n.t('select_room_type_first'), 'error');
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
                var roomTypeId = parseInt(self.roomForm.room_type_id, 10);
                if (!roomTypeId || roomTypeId < 1) {
                    VeneziaUtils.toast(VeneziaI18n.t('select_room_type'), 'error');
                    return;
                }
                var data = {
                    room_number: self.roomForm.room_number,
                    room_type_id: roomTypeId,
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
                        VeneziaI18n.t(self.editingRoomId ? 'room_updated' : 'room_created'),
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_save_room'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRoom: function (id) {
                if (!confirm(VeneziaI18n.t('confirm_delete_room'))) return;
                var self = this;
                VeneziaAPI.delete('/admin/rooms/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast(VeneziaI18n.t('room_deleted'), 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_delete_room'), 'error');
                });
            },

            // ---- Helpers ----

            statusLabel: function (status) {
                return VeneziaI18n.t(status);
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            }
        };
    });
});
