/**
 * Nozule - Admin Channel Sync
 *
 * Alpine.js component for the OTA Channel Sync admin page.
 * Manages connections, rate mappings, and sync log display.
 */
document.addEventListener('alpine:init', function () {

	Alpine.data('nzlChannelSync', function () {
		return {
			loading: true,
			activeTab: 'connections',

			// Connections
			connections: [],
			connectionForm: {
				hotel_id: '',
				username: '',
				password: '',
				api_endpoint: '',
				use_sandbox: false,
				is_active: false
			},
			savingConnection: false,
			testing: false,
			syncing: false,

			// Rate Mappings
			selectedChannel: '',
			rateMappings: [],
			roomTypes: [],
			ratePlans: [],
			showMappingModal: false,
			savingMapping: false,
			mappingForm: {
				local_room_type_id: '',
				local_rate_plan_id: 0,
				channel_room_id: '',
				channel_rate_id: ''
			},

			// Sync Log
			syncLogs: [],
			logPage: 1,
			logPages: 1,
			logTotal: 0,
			logFilters: {
				channel: '',
				direction: '',
				status: ''
			},

			init: function () {
				this.loadConnections();
			},

			// ---- Connections ----

			loadConnections: function () {
				var self = this;
				self.loading = true;

				NozuleAPI.get('/admin/channels/connections').then(function (response) {
					self.connections = response.connections || [];

					// Pre-fill the form if a Booking.com connection exists.
					var bcom = self.getConnection('booking_com');
					if (bcom) {
						self.connectionForm.hotel_id = bcom.hotel_id || '';
						self.connectionForm.is_active = !!bcom.is_active;
						// Credentials are not returned from API, so leave blank.
					}
				}).catch(function (err) {
					console.error('Connections load error:', err);
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_load'), 'error');
				}).finally(function () {
					self.loading = false;
				});
			},

			getConnection: function (channelName) {
				for (var i = 0; i < this.connections.length; i++) {
					if (this.connections[i].channel_name === channelName) {
						return this.connections[i];
					}
				}
				return null;
			},

			saveConnectionForm: function (channelName) {
				var self = this;
				self.savingConnection = true;

				var conn = self.getConnection(channelName);
				var payload = {
					channel_name: channelName,
					hotel_id: self.connectionForm.hotel_id,
					username: self.connectionForm.username,
					password: self.connectionForm.password,
					api_endpoint: self.connectionForm.api_endpoint,
					use_sandbox: self.connectionForm.use_sandbox,
					is_active: self.connectionForm.is_active ? 1 : 0
				};

				if (conn) {
					payload.id = conn.id;
				}

				NozuleAPI.post('/admin/channels/connections', payload).then(function (response) {
					self.loadConnections();
					NozuleUtils.toast(response.message || NozuleI18n.t('connection_saved'), 'success');
					// Clear password after save.
					self.connectionForm.password = '';
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
				}).finally(function () {
					self.savingConnection = false;
				});
			},

			deleteConnectionBtn: function (channelName) {
				var conn = this.getConnection(channelName);
				if (!conn) return;
				if (!confirm(NozuleI18n.t('confirm_delete_connection') || 'Are you sure you want to delete this connection?')) return;

				var self = this;
				NozuleAPI.delete('/admin/channels/connections/' + conn.id).then(function (response) {
					self.loadConnections();
					self.connectionForm = {
						hotel_id: '',
						username: '',
						password: '',
						api_endpoint: '',
						use_sandbox: false,
						is_active: false
					};
					NozuleUtils.toast(response.message || NozuleI18n.t('connection_deleted'), 'success');
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
				});
			},

			testConnectionBtn: function (channelName) {
				var conn = this.getConnection(channelName);
				if (!conn) return;

				var self = this;
				self.testing = true;

				NozuleAPI.post('/admin/channels/connections/' + conn.id + '/test').then(function (response) {
					if (response.success) {
						NozuleUtils.toast(response.message || NozuleI18n.t('connection_test_success'), 'success');
					} else {
						NozuleUtils.toast(response.message || NozuleI18n.t('connection_test_failed'), 'error');
					}
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('connection_test_failed'), 'error');
				}).finally(function () {
					self.testing = false;
				});
			},

			triggerSync: function (channelName) {
				var self = this;
				self.syncing = true;

				NozuleAPI.post('/admin/channels/sync/' + channelName).then(function (response) {
					self.loadConnections();
					NozuleUtils.toast(response.message || NozuleI18n.t('sync_completed'), 'success');
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('sync_failed'), 'error');
				}).finally(function () {
					self.syncing = false;
				});
			},

			// ---- Rate Mappings ----

			switchToRateMapping: function () {
				this.activeTab = 'rate_mapping';
				this.loadRoomTypes();
				this.loadRatePlans();
				if (this.selectedChannel) {
					this.loadRateMappings();
				}
			},

			loadRoomTypes: function () {
				var self = this;
				NozuleAPI.get('/admin/room-types').then(function (response) {
					self.roomTypes = response.room_types || response.data || response || [];
				}).catch(function (err) {
					console.error('Room types load error:', err);
				});
			},

			loadRatePlans: function () {
				var self = this;
				NozuleAPI.get('/admin/rate-plans').then(function (response) {
					self.ratePlans = response.rate_plans || response.data || response || [];
				}).catch(function (err) {
					console.error('Rate plans load error:', err);
				});
			},

			loadRateMappings: function () {
				if (!this.selectedChannel) {
					this.rateMappings = [];
					return;
				}

				var self = this;
				NozuleAPI.get('/admin/channels/rate-mappings/' + self.selectedChannel).then(function (response) {
					self.rateMappings = response.mappings || [];
				}).catch(function (err) {
					console.error('Rate mappings load error:', err);
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_load'), 'error');
				});
			},

			getRoomTypeName: function (id) {
				for (var i = 0; i < this.roomTypes.length; i++) {
					if (this.roomTypes[i].id == id) {
						return this.roomTypes[i].name;
					}
				}
				return '#' + id;
			},

			getRatePlanName: function (id) {
				if (!id || id === 0 || id === '0') {
					return NozuleI18n.t('base_rate') || 'Base Rate';
				}
				for (var i = 0; i < this.ratePlans.length; i++) {
					if (this.ratePlans[i].id == id) {
						return this.ratePlans[i].name;
					}
				}
				return '#' + id;
			},

			openMappingModal: function () {
				this.mappingForm = {
					local_room_type_id: '',
					local_rate_plan_id: 0,
					channel_room_id: '',
					channel_rate_id: ''
				};
				this.showMappingModal = true;
			},

			saveMappingForm: function () {
				var self = this;

				if (!self.mappingForm.local_room_type_id) {
					NozuleUtils.toast(NozuleI18n.t('select_room_type') || 'Please select a room type.', 'error');
					return;
				}

				if (!self.mappingForm.channel_room_id) {
					NozuleUtils.toast(NozuleI18n.t('enter_channel_room_id') || 'Please enter the channel room ID.', 'error');
					return;
				}

				self.savingMapping = true;

				var payload = {
					channel_name: self.selectedChannel,
					local_room_type_id: parseInt(self.mappingForm.local_room_type_id, 10),
					local_rate_plan_id: parseInt(self.mappingForm.local_rate_plan_id, 10) || 0,
					channel_room_id: self.mappingForm.channel_room_id,
					channel_rate_id: self.mappingForm.channel_rate_id,
					is_active: 1
				};

				NozuleAPI.post('/admin/channels/rate-mappings', payload).then(function (response) {
					self.showMappingModal = false;
					self.loadRateMappings();
					NozuleUtils.toast(response.message || NozuleI18n.t('mapping_saved'), 'success');
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
				}).finally(function () {
					self.savingMapping = false;
				});
			},

			updateMappingField: function (id, field, value) {
				var payload = { id: id };
				payload[field] = value;

				// Include the required channel_name and local_room_type_id.
				var mapping = null;
				for (var i = 0; i < this.rateMappings.length; i++) {
					if (this.rateMappings[i].id === id) {
						mapping = this.rateMappings[i];
						break;
					}
				}
				if (mapping) {
					payload.channel_name = mapping.channel_name || this.selectedChannel;
					payload.local_room_type_id = mapping.local_room_type_id;
				}

				NozuleAPI.post('/admin/channels/rate-mappings', payload).then(function () {
					// Silently updated.
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
				});
			},

			deleteMapping: function (id) {
				if (!confirm(NozuleI18n.t('confirm_delete_mapping') || 'Are you sure you want to delete this mapping?')) return;

				var self = this;
				NozuleAPI.delete('/admin/channels/rate-mappings/' + id).then(function (response) {
					self.loadRateMappings();
					NozuleUtils.toast(response.message || NozuleI18n.t('mapping_deleted'), 'success');
				}).catch(function (err) {
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
				});
			},

			// ---- Sync Log ----

			switchToSyncLog: function () {
				this.activeTab = 'sync_log';
				this.loadSyncLog();
			},

			loadSyncLog: function () {
				var self = this;
				var params = [
					'page=' + self.logPage,
					'per_page=20'
				];

				if (self.logFilters.channel) {
					params.push('channel=' + encodeURIComponent(self.logFilters.channel));
				}
				if (self.logFilters.direction) {
					params.push('direction=' + encodeURIComponent(self.logFilters.direction));
				}
				if (self.logFilters.status) {
					params.push('status=' + encodeURIComponent(self.logFilters.status));
				}

				NozuleAPI.get('/admin/channels/sync-log?' + params.join('&')).then(function (response) {
					self.syncLogs = response.items || [];
					self.logTotal = response.total || 0;
					self.logPages = response.pages || 1;
				}).catch(function (err) {
					console.error('Sync log load error:', err);
					NozuleUtils.toast(err.message || NozuleI18n.t('failed_load'), 'error');
				});
			},

			resetLogFilters: function () {
				this.logFilters = {
					channel: '',
					direction: '',
					status: ''
				};
				this.logPage = 1;
				this.loadSyncLog();
			},

			goToLogPage: function (page) {
				this.logPage = page;
				this.loadSyncLog();
			}
		};
	});
});
