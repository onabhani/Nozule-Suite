/**
 * Nozule - Admin Dynamic Pricing
 *
 * Modal-based CRUD for occupancy rules, day-of-week rules, and event overrides.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlDynamicPricing', function () {
        return {
            loading: true,
            saving: false,
            activeTab: 'occupancy',
            roomTypes: [],

            // Data arrays
            occupancyRules: [],
            dowRules: [],
            eventOverrides: [],

            // Occupancy modal state
            showOccupancyModal: false,
            editingOccupancyId: null,
            occForm: {},

            // DOW modal state
            showDowModal: false,
            editingDowId: null,
            dowForm: {},

            // Event modal state
            showEventModal: false,
            editingEventId: null,
            evtForm: {},

            // Day name mapping
            dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],

            init: function () {
                this.loadData();
                this.loadRoomTypes();
            },

            // ---- Default form values ----

            defaultOccForm: function () {
                return {
                    room_type_id: '',
                    threshold_percent: 70,
                    modifier_type: 'percentage',
                    modifier_value: 0,
                    priority: 0,
                    status: 'active'
                };
            },

            defaultDowForm: function () {
                return {
                    room_type_id: '',
                    day_of_week: 0,
                    modifier_type: 'percentage',
                    modifier_value: 0,
                    status: 'active'
                };
            },

            defaultEvtForm: function () {
                return {
                    name: '',
                    name_ar: '',
                    room_type_id: '',
                    start_date: '',
                    end_date: '',
                    modifier_type: 'percentage',
                    modifier_value: 0,
                    priority: 0,
                    status: 'active'
                };
            },

            // ---- Data loading ----

            loadData: function () {
                var self = this;
                self.loading = true;

                Promise.all([
                    NozuleAPI.get('/admin/dynamic-pricing/occupancy-rules'),
                    NozuleAPI.get('/admin/dynamic-pricing/dow-rules'),
                    NozuleAPI.get('/admin/dynamic-pricing/event-overrides')
                ]).then(function (responses) {
                    self.occupancyRules = responses[0].data || [];
                    self.dowRules = responses[1].data || [];
                    self.eventOverrides = responses[2].data || [];
                }).catch(function (err) {
                    console.error('Dynamic pricing load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadRoomTypes: function () {
                var self = this;
                NozuleAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.data || [];
                }).catch(function () {
                    // Non-critical
                });
            },

            // ---- Occupancy Rules CRUD ----

            openOccupancyModal: function () {
                this.editingOccupancyId = null;
                this.occForm = this.defaultOccForm();
                this.showOccupancyModal = true;
            },

            editOccupancyRule: function (rule) {
                this.editingOccupancyId = rule.id;
                this.occForm = {
                    room_type_id: rule.room_type_id || '',
                    threshold_percent: rule.threshold_percent || 70,
                    modifier_type: rule.modifier_type || 'percentage',
                    modifier_value: rule.modifier_value || 0,
                    priority: rule.priority || 0,
                    status: rule.status || 'active'
                };
                this.showOccupancyModal = true;
            },

            saveOccupancyRule: function () {
                var self = this;
                var data = {
                    room_type_id: self.occForm.room_type_id !== '' ? parseInt(self.occForm.room_type_id, 10) : null,
                    threshold_percent: parseInt(self.occForm.threshold_percent, 10) || 0,
                    modifier_type: self.occForm.modifier_type,
                    modifier_value: parseFloat(self.occForm.modifier_value) || 0,
                    priority: parseInt(self.occForm.priority, 10) || 0,
                    status: self.occForm.status
                };

                self.saving = true;

                var promise;
                if (self.editingOccupancyId) {
                    promise = NozuleAPI.put('/admin/dynamic-pricing/occupancy-rules/' + self.editingOccupancyId, data);
                } else {
                    promise = NozuleAPI.post('/admin/dynamic-pricing/occupancy-rules', data);
                }

                promise.then(function () {
                    self.showOccupancyModal = false;
                    self.loadData();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingOccupancyId ? 'occupancy_rule_updated' : 'occupancy_rule_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_occupancy_rule'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteOccupancyRule: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_occupancy_rule'))) return;
                var self = this;
                NozuleAPI.delete('/admin/dynamic-pricing/occupancy-rules/' + id).then(function () {
                    self.loadData();
                    NozuleUtils.toast(NozuleI18n.t('occupancy_rule_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_occupancy_rule'), 'error');
                });
            },

            // ---- Day-of-Week Rules CRUD ----

            openDowModal: function () {
                this.editingDowId = null;
                this.dowForm = this.defaultDowForm();
                this.showDowModal = true;
            },

            editDowRule: function (rule) {
                this.editingDowId = rule.id;
                this.dowForm = {
                    room_type_id: rule.room_type_id || '',
                    day_of_week: rule.day_of_week != null ? rule.day_of_week : 0,
                    modifier_type: rule.modifier_type || 'percentage',
                    modifier_value: rule.modifier_value || 0,
                    status: rule.status || 'active'
                };
                this.showDowModal = true;
            },

            saveDowRule: function () {
                var self = this;
                var data = {
                    room_type_id: self.dowForm.room_type_id !== '' ? parseInt(self.dowForm.room_type_id, 10) : null,
                    day_of_week: parseInt(self.dowForm.day_of_week, 10),
                    modifier_type: self.dowForm.modifier_type,
                    modifier_value: parseFloat(self.dowForm.modifier_value) || 0,
                    status: self.dowForm.status
                };

                self.saving = true;

                var promise;
                if (self.editingDowId) {
                    promise = NozuleAPI.put('/admin/dynamic-pricing/dow-rules/' + self.editingDowId, data);
                } else {
                    promise = NozuleAPI.post('/admin/dynamic-pricing/dow-rules', data);
                }

                promise.then(function () {
                    self.showDowModal = false;
                    self.loadData();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingDowId ? 'dow_rule_updated' : 'dow_rule_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_dow_rule'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteDowRule: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_dow_rule'))) return;
                var self = this;
                NozuleAPI.delete('/admin/dynamic-pricing/dow-rules/' + id).then(function () {
                    self.loadData();
                    NozuleUtils.toast(NozuleI18n.t('dow_rule_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_dow_rule'), 'error');
                });
            },

            // ---- Event Overrides CRUD ----

            openEventModal: function () {
                this.editingEventId = null;
                this.evtForm = this.defaultEvtForm();
                this.showEventModal = true;
            },

            editEvent: function (evt) {
                this.editingEventId = evt.id;
                this.evtForm = {
                    name: evt.name || '',
                    name_ar: evt.name_ar || '',
                    room_type_id: evt.room_type_id || '',
                    start_date: evt.start_date || '',
                    end_date: evt.end_date || '',
                    modifier_type: evt.modifier_type || 'percentage',
                    modifier_value: evt.modifier_value || 0,
                    priority: evt.priority || 0,
                    status: evt.status || 'active'
                };
                this.showEventModal = true;
            },

            saveEvent: function () {
                var self = this;
                var data = {
                    name: self.evtForm.name,
                    name_ar: self.evtForm.name_ar || '',
                    room_type_id: self.evtForm.room_type_id !== '' ? parseInt(self.evtForm.room_type_id, 10) : null,
                    start_date: self.evtForm.start_date,
                    end_date: self.evtForm.end_date,
                    modifier_type: self.evtForm.modifier_type,
                    modifier_value: parseFloat(self.evtForm.modifier_value) || 0,
                    priority: parseInt(self.evtForm.priority, 10) || 0,
                    status: self.evtForm.status
                };

                self.saving = true;

                var promise;
                if (self.editingEventId) {
                    promise = NozuleAPI.put('/admin/dynamic-pricing/event-overrides/' + self.editingEventId, data);
                } else {
                    promise = NozuleAPI.post('/admin/dynamic-pricing/event-overrides', data);
                }

                promise.then(function () {
                    self.showEventModal = false;
                    self.loadData();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingEventId ? 'event_override_updated' : 'event_override_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_event_override'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteEvent: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_event_override'))) return;
                var self = this;
                NozuleAPI.delete('/admin/dynamic-pricing/event-overrides/' + id).then(function () {
                    self.loadData();
                    NozuleUtils.toast(NozuleI18n.t('event_override_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_event_override'), 'error');
                });
            },

            // ---- Helpers ----

            getDayName: function (dayIndex) {
                return this.dayNames[dayIndex] || '';
            },

            getRoomTypeName: function (roomTypeId) {
                if (!roomTypeId) return NozuleI18n.t('all_room_types');
                var found = null;
                for (var i = 0; i < this.roomTypes.length; i++) {
                    if (this.roomTypes[i].id == roomTypeId) {
                        found = this.roomTypes[i];
                        break;
                    }
                }
                return found ? found.name : NozuleI18n.t('room_type') + ' #' + roomTypeId;
            },

            formatDate: function (date) {
                if (typeof NozuleUtils !== 'undefined' && NozuleUtils.formatDate) {
                    return NozuleUtils.formatDate(date);
                }
                return date || '';
            }
        };
    });
});
