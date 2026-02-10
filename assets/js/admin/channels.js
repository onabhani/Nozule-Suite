/**
 * Venezia Hotel Manager - Admin Channels
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmChannels', function () {
        return {
            loading: true,
            channels: [],
            availableChannels: [],
            syncing: null,
            showCreateModal: false,
            editingChannelId: null,
            roomTypes: [],
            channelForm: {
                channel_name: '',
                room_type_id: '',
                external_room_id: '',
                sync_availability: true,
                sync_rates: true,
                sync_reservations: true,
                status: 'active'
            },

            init: function () {
                this.loadChannels();
            },

            defaultChannelForm: function () {
                return {
                    channel_name: '',
                    room_type_id: '',
                    external_room_id: '',
                    sync_availability: true,
                    sync_rates: true,
                    sync_reservations: true,
                    status: 'active'
                };
            },

            loadChannels: function () {
                var self = this;
                self.loading = true;

                VeneziaAPI.get('/admin/channels').then(function (response) {
                    // listChannels returns { available_channels, mappings, total, pages }
                    self.channels = (response.mappings || []).map(function (m) {
                        return {
                            id: m.id,
                            name: m.channel_name || 'Unknown',
                            type: m.channel_name || '',
                            status: m.status || 'inactive',
                            room_type_id: m.room_type_id,
                            external_room_id: m.external_room_id || '',
                            last_sync: m.last_sync_at || null,
                            booking_count: 0
                        };
                    });
                    self.availableChannels = response.available_channels || [];
                }).catch(function (err) {
                    console.error('Channels load error:', err);
                    VeneziaUtils.toast(err.message || 'Failed to load channels', 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadRoomTypes: function () {
                var self = this;
                VeneziaAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.room_types || response || [];
                }).catch(function (err) {
                    console.error('Room types load error:', err);
                    VeneziaUtils.toast(err.message || 'Failed to load room types', 'error');
                });
            },

            openCreateModal: function () {
                this.editingChannelId = null;
                this.channelForm = this.defaultChannelForm();
                this.showCreateModal = true;
                this.loadRoomTypes();
            },

            editChannel: function (id) {
                var channel = this.channels.find(function (c) { return c.id === id; });
                if (!channel) return;

                this.editingChannelId = id;
                this.channelForm = {
                    channel_name: channel.type || channel.name || '',
                    room_type_id: channel.room_type_id || '',
                    external_room_id: channel.external_room_id || '',
                    sync_availability: channel.sync_availability !== undefined ? channel.sync_availability : true,
                    sync_rates: channel.sync_rates !== undefined ? channel.sync_rates : true,
                    sync_reservations: channel.sync_reservations !== undefined ? channel.sync_reservations : true,
                    status: channel.status || 'active'
                };
                this.showCreateModal = true;
                this.loadRoomTypes();
            },

            saveChannel: function () {
                var self = this;
                var payload = {
                    channel_name: self.channelForm.channel_name,
                    room_type_id: parseInt(self.channelForm.room_type_id, 10),
                    external_room_id: self.channelForm.external_room_id,
                    sync_availability: self.channelForm.sync_availability,
                    sync_rates: self.channelForm.sync_rates,
                    sync_reservations: self.channelForm.sync_reservations,
                    status: self.channelForm.status
                };

                var request;
                if (self.editingChannelId) {
                    request = VeneziaAPI.put('/admin/channels/' + self.editingChannelId, payload);
                } else {
                    request = VeneziaAPI.post('/admin/channels', payload);
                }

                request.then(function () {
                    self.showCreateModal = false;
                    self.loadChannels();
                    VeneziaUtils.toast(
                        VeneziaI18n.t(self.editingChannelId ? 'channel_updated' : 'channel_created'),
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(
                        err.message || VeneziaI18n.t('failed_save_channel'),
                        'error'
                    );
                });
            },

            syncChannel: function (id) {
                var self = this;
                self.syncing = id;

                VeneziaAPI.post('/admin/channels/' + id + '/sync').then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast(VeneziaI18n.t('channel_synced'), 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('sync_failed'), 'error');
                }).finally(function () {
                    self.syncing = null;
                });
            },

            deleteChannel: function (id) {
                if (!confirm(VeneziaI18n.t('confirm_delete_channel'))) return;
                var self = this;
                VeneziaAPI.delete('/admin/channels/' + id).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast(VeneziaI18n.t('channel_deleted'), 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_delete_channel'), 'error');
                });
            }
        };
    });
});
