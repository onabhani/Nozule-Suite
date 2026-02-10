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

            init: function () {
                this.loadChannels();
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

            openCreateModal: function () {
                var channelName = prompt('Channel Name (e.g. booking_com, expedia):');
                if (!channelName) return;
                var roomTypeId = prompt('Room Type ID:');
                if (!roomTypeId) return;
                var externalRoomId = prompt('External Room/Property ID:');
                if (!externalRoomId) return;

                var self = this;
                VeneziaAPI.post('/admin/channels', {
                    channel_name: channelName,
                    room_type_id: parseInt(roomTypeId, 10),
                    external_room_id: externalRoomId,
                    sync_availability: true,
                    sync_rates: true,
                    sync_reservations: true,
                    status: 'active'
                }).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel mapping created', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to create channel', 'error');
                });
            },

            syncChannel: function (id) {
                var self = this;
                self.syncing = id;

                VeneziaAPI.post('/admin/channels/' + id + '/sync').then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel synced successfully', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Sync failed', 'error');
                }).finally(function () {
                    self.syncing = null;
                });
            },

            editChannel: function (id) {
                var channel = this.channels.find(function (c) { return c.id === id; });
                if (!channel) return;
                var status = prompt('Status (active, inactive):', channel.status);
                if (status === null) return;

                var self = this;
                VeneziaAPI.put('/admin/channels/' + id, {
                    status: status
                }).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Update failed', 'error');
                });
            },

            deleteChannel: function (id) {
                if (!confirm('Delete this channel connection?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/channels/' + id).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Delete failed', 'error');
                });
            }
        };
    });
});
