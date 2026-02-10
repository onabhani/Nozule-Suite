/**
 * Venezia Hotel Manager - Admin Channels
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmChannels', function () {
        return {
            loading: true,
            channels: [],
            syncing: null,

            init: function () {
                this.loadChannels();
            },

            loadChannels: function () {
                var self = this;
                self.loading = true;

                VeneziaAPI.get('/admin/channels').then(function (response) {
                    self.channels = response.data || [];
                }).catch(function (err) {
                    console.error('Channels load error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            openCreateModal: function () {
                var name = prompt('Channel Name:');
                if (!name) return;
                var type = prompt('Channel Type (booking_com, expedia, direct):');
                if (!type) return;

                var self = this;
                VeneziaAPI.post('/admin/channels', {
                    name: name,
                    type: type,
                    status: 'active'
                }).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel created', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            syncChannel: function (id) {
                var self = this;
                self.syncing = id;

                VeneziaAPI.post('/admin/channels/' + id + '/sync').then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel synced successfully', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                }).finally(function () {
                    self.syncing = null;
                });
            },

            editChannel: function (id) {
                var channel = this.channels.find(function (c) { return c.id === id; });
                if (!channel) return;
                var name = prompt('Channel Name:', channel.name);
                if (name === null) return;
                var status = prompt('Status (active, inactive):', channel.status);
                if (status === null) return;

                var self = this;
                VeneziaAPI.put('/admin/channels/' + id, {
                    name: name,
                    status: status
                }).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            deleteChannel: function (id) {
                if (!confirm('Delete this channel connection?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/channels/' + id).then(function () {
                    self.loadChannels();
                    VeneziaUtils.toast('Channel deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            }
        };
    });
});
