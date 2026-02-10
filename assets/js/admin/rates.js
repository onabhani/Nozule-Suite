/**
 * Venezia Hotel Manager - Admin Rates & Pricing
 *
 * Modal-based CRUD for rate plans and seasonal rates.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmRates', function () {
        return {
            loading: true,
            saving: false,
            activeTab: 'rate_plans',
            ratePlans: [],
            seasonalRates: [],
            roomTypes: [],

            // Rate Plan modal state
            showRatePlanModal: false,
            editingRatePlanId: null,
            rpForm: {},

            // Seasonal Rate modal state
            showSeasonalModal: false,
            editingSeasonalId: null,
            srForm: {},

            init: function () {
                this.loadData();
                this.loadRoomTypes();
            },

            // ---- Default form values ----

            defaultRpForm: function () {
                return {
                    name: '',
                    code: '',
                    modifier_type: 'percentage',
                    modifier_value: 0,
                    min_stay: '',
                    max_stay: '',
                    priority: 0,
                    status: 'active',
                    description: ''
                };
            },

            defaultSrForm: function () {
                return {
                    name: '',
                    room_type_id: '',
                    start_date: '',
                    end_date: '',
                    modifier_type: 'percentage',
                    modifier_value: 0,
                    priority: 0,
                    min_stay: '',
                    status: 'active'
                };
            },

            // ---- Data loading ----

            loadData: function () {
                var self = this;
                self.loading = true;

                Promise.all([
                    VeneziaAPI.get('/admin/rate-plans'),
                    VeneziaAPI.get('/admin/seasonal-rates')
                ]).then(function (responses) {
                    self.ratePlans = responses[0].data || [];
                    self.seasonalRates = responses[1].data || [];
                }).catch(function (err) {
                    console.error('Rates load error:', err);
                    VeneziaUtils.toast(err.message || 'Failed to load rates data', 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadRoomTypes: function () {
                var self = this;
                VeneziaAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.data || [];
                }).catch(function () {
                    // Non-critical, seasonal rate form will still work with manual ID
                });
            },

            // ---- Rate Plan CRUD ----

            openRatePlanModal: function () {
                this.editingRatePlanId = null;
                this.rpForm = this.defaultRpForm();
                this.showRatePlanModal = true;
            },

            editRatePlan: function (plan) {
                this.editingRatePlanId = plan.id;
                this.rpForm = {
                    name: plan.name || '',
                    code: plan.code || '',
                    modifier_type: plan.modifier_type || 'percentage',
                    modifier_value: plan.modifier_value || 0,
                    min_stay: plan.min_stay || '',
                    max_stay: plan.max_stay || '',
                    priority: plan.priority || 0,
                    status: plan.status || 'active',
                    description: plan.description || ''
                };
                this.showRatePlanModal = true;
            },

            saveRatePlan: function () {
                var self = this;
                var data = {
                    name: self.rpForm.name,
                    code: self.rpForm.code,
                    modifier_type: self.rpForm.modifier_type,
                    modifier_value: parseFloat(self.rpForm.modifier_value) || 0,
                    status: self.rpForm.status
                };

                // Include optional fields only if filled
                if (self.rpForm.min_stay !== '' && self.rpForm.min_stay != null) {
                    data.min_stay = parseInt(self.rpForm.min_stay, 10);
                }
                if (self.rpForm.max_stay !== '' && self.rpForm.max_stay != null) {
                    data.max_stay = parseInt(self.rpForm.max_stay, 10);
                }
                if (self.rpForm.priority) {
                    data.priority = parseInt(self.rpForm.priority, 10);
                }
                if (self.rpForm.description) {
                    data.description = self.rpForm.description;
                }

                self.saving = true;

                var promise;
                if (self.editingRatePlanId) {
                    promise = VeneziaAPI.put('/rate-plans/' + self.editingRatePlanId, data);
                } else {
                    promise = VeneziaAPI.post('/admin/rate-plans', data);
                }

                promise.then(function () {
                    self.showRatePlanModal = false;
                    self.loadData();
                    VeneziaUtils.toast(
                        self.editingRatePlanId ? 'Rate plan updated' : 'Rate plan created',
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to save rate plan', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRatePlan: function (id) {
                if (!confirm('Delete this rate plan?')) return;
                var self = this;
                VeneziaAPI.delete('/rate-plans/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Rate plan deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to delete rate plan', 'error');
                });
            },

            // ---- Seasonal Rate CRUD ----

            openSeasonalModal: function () {
                this.editingSeasonalId = null;
                this.srForm = this.defaultSrForm();
                this.showSeasonalModal = true;
            },

            editSeasonalRate: function (rate) {
                this.editingSeasonalId = rate.id;
                this.srForm = {
                    name: rate.name || '',
                    room_type_id: rate.room_type_id || '',
                    start_date: rate.start_date || '',
                    end_date: rate.end_date || '',
                    modifier_type: rate.modifier_type || 'percentage',
                    modifier_value: rate.modifier_value || 0,
                    priority: rate.priority || 0,
                    min_stay: rate.min_stay || '',
                    status: rate.status || 'active'
                };
                this.showSeasonalModal = true;
            },

            saveSeasonalRate: function () {
                var self = this;
                var data = {
                    name: self.srForm.name,
                    room_type_id: parseInt(self.srForm.room_type_id, 10) || 0,
                    start_date: self.srForm.start_date,
                    end_date: self.srForm.end_date,
                    modifier_type: self.srForm.modifier_type,
                    modifier_value: parseFloat(self.srForm.modifier_value) || 0,
                    status: self.srForm.status
                };

                if (self.srForm.priority) {
                    data.priority = parseInt(self.srForm.priority, 10);
                }
                if (self.srForm.min_stay !== '' && self.srForm.min_stay != null) {
                    data.min_stay = parseInt(self.srForm.min_stay, 10);
                }

                self.saving = true;

                var promise;
                if (self.editingSeasonalId) {
                    promise = VeneziaAPI.put('/seasonal-rates/' + self.editingSeasonalId, data);
                } else {
                    promise = VeneziaAPI.post('/admin/seasonal-rates', data);
                }

                promise.then(function () {
                    self.showSeasonalModal = false;
                    self.loadData();
                    VeneziaUtils.toast(
                        self.editingSeasonalId ? 'Seasonal rate updated' : 'Seasonal rate created',
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to save seasonal rate', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteSeasonalRate: function (id) {
                if (!confirm('Delete this seasonal rate?')) return;
                var self = this;
                VeneziaAPI.delete('/seasonal-rates/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Seasonal rate deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || 'Failed to delete seasonal rate', 'error');
                });
            },

            // ---- Helpers ----

            formatDate: function (date) {
                if (typeof VeneziaUtils !== 'undefined' && VeneziaUtils.formatDate) {
                    return VeneziaUtils.formatDate(date);
                }
                return date || '';
            }
        };
    });
});
