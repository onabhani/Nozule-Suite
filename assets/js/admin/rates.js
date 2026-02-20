/**
 * Nozule - Admin Rates & Pricing
 *
 * Modal-based CRUD for rate plans and seasonal rates.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlRates', function () {
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

            // Restrictions (NZL-017)
            restrictions: [],
            restrictionsLoaded: false,
            showRestrictionModal: false,
            editingRestrictionId: null,
            rrForm: {},
            restrictionFilters: { room_type_id: '', restriction_type: '' },

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
                    guest_type: 'all',
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
                    status: 'active'
                };
            },

            // ---- Data loading ----

            loadData: function () {
                var self = this;
                self.loading = true;

                Promise.all([
                    NozuleAPI.get('/admin/rate-plans'),
                    NozuleAPI.get('/admin/seasonal-rates')
                ]).then(function (responses) {
                    self.ratePlans = responses[0].data || [];
                    self.seasonalRates = responses[1].data || [];
                }).catch(function (err) {
                    console.error('Rates load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_rates'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadRoomTypes: function () {
                var self = this;
                NozuleAPI.get('/admin/room-types').then(function (response) {
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
                    guest_type: plan.guest_type || 'all',
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
                    guest_type: self.rpForm.guest_type || 'all',
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
                    promise = NozuleAPI.put('/admin/rate-plans/' + self.editingRatePlanId, data);
                } else {
                    promise = NozuleAPI.post('/admin/rate-plans', data);
                }

                promise.then(function () {
                    self.showRatePlanModal = false;
                    self.loadData();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingRatePlanId ? 'rate_plan_updated' : 'rate_plan_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_rate_plan'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRatePlan: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_rate_plan'))) return;
                var self = this;
                NozuleAPI.delete('/admin/rate-plans/' + id).then(function () {
                    self.loadData();
                    NozuleUtils.toast(NozuleI18n.t('rate_plan_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_rate_plan'), 'error');
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
                    promise = NozuleAPI.put('/admin/seasonal-rates/' + self.editingSeasonalId, data);
                } else {
                    promise = NozuleAPI.post('/admin/seasonal-rates', data);
                }

                promise.then(function () {
                    self.showSeasonalModal = false;
                    self.loadData();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingSeasonalId ? 'seasonal_rate_updated' : 'seasonal_rate_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_seasonal_rate'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteSeasonalRate: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_seasonal_rate'))) return;
                var self = this;
                NozuleAPI.delete('/admin/seasonal-rates/' + id).then(function () {
                    self.loadData();
                    NozuleUtils.toast(NozuleI18n.t('seasonal_rate_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_seasonal_rate'), 'error');
                });
            },

            // ---- Tab switching ----

            switchTab: function (tab) {
                this.activeTab = tab;
                if (tab === 'restrictions' && !this.restrictionsLoaded) {
                    this.loadRestrictions();
                }
            },

            // ---- Restrictions CRUD (NZL-017) ----

            defaultRrForm: function () {
                return {
                    restriction_type: 'min_stay',
                    room_type_id: this.roomTypes.length > 0 ? this.roomTypes[0].id : '',
                    rate_plan_id: '',
                    value: 2,
                    channel: '',
                    date_from: '',
                    date_to: '',
                    daysArray: [],
                    is_active: true
                };
            },

            loadRestrictions: function () {
                var self = this;
                var params = {};
                if (self.restrictionFilters.room_type_id) params.room_type_id = self.restrictionFilters.room_type_id;
                if (self.restrictionFilters.restriction_type) params.restriction_type = self.restrictionFilters.restriction_type;

                NozuleAPI.get('/rate-restrictions', params).then(function (response) {
                    self.restrictions = response.data || [];
                    self.restrictionsLoaded = true;
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_restrictions'), 'error');
                });
            },

            openRestrictionModal: function () {
                this.editingRestrictionId = null;
                this.rrForm = this.defaultRrForm();
                this.showRestrictionModal = true;
            },

            editRestriction: function (r) {
                this.editingRestrictionId = r.id;
                this.rrForm = {
                    restriction_type: r.restriction_type || 'min_stay',
                    room_type_id: r.room_type_id || '',
                    rate_plan_id: r.rate_plan_id || '',
                    value: r.value || '',
                    channel: r.channel || '',
                    date_from: r.date_from || '',
                    date_to: r.date_to || '',
                    daysArray: r.days_of_week ? r.days_of_week.split(',') : [],
                    is_active: r.is_active === 1 || r.is_active === true
                };
                this.showRestrictionModal = true;
            },

            saveRestriction: function () {
                var self = this;
                var data = {
                    restriction_type: self.rrForm.restriction_type,
                    room_type_id: parseInt(self.rrForm.room_type_id, 10),
                    date_from: self.rrForm.date_from,
                    date_to: self.rrForm.date_to,
                    is_active: self.rrForm.is_active ? 1 : 0
                };

                if (self.rrForm.rate_plan_id) data.rate_plan_id = parseInt(self.rrForm.rate_plan_id, 10);
                if (self.rrForm.value) data.value = parseInt(self.rrForm.value, 10);
                if (self.rrForm.channel) data.channel = self.rrForm.channel;
                if (self.rrForm.daysArray && self.rrForm.daysArray.length > 0) {
                    data.days_of_week = self.rrForm.daysArray.join(',');
                }

                if (!data.room_type_id || !data.date_from || !data.date_to) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;
                var promise;
                if (self.editingRestrictionId) {
                    promise = NozuleAPI.put('/rate-restrictions/' + self.editingRestrictionId, data);
                } else {
                    promise = NozuleAPI.post('/rate-restrictions', data);
                }

                promise.then(function () {
                    self.showRestrictionModal = false;
                    self.loadRestrictions();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingRestrictionId ? 'restriction_updated' : 'restriction_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_restriction'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteRestriction: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_restriction'))) return;
                var self = this;
                NozuleAPI.delete('/rate-restrictions/' + id).then(function () {
                    self.loadRestrictions();
                    NozuleUtils.toast(NozuleI18n.t('restriction_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_restriction'), 'error');
                });
            },

            restrictionTypeLabel: function (type) {
                var labels = {
                    'min_stay': NozuleI18n.t('restriction_min_stay'),
                    'max_stay': NozuleI18n.t('restriction_max_stay'),
                    'cta': NozuleI18n.t('restriction_cta'),
                    'ctd': NozuleI18n.t('restriction_ctd'),
                    'stop_sell': NozuleI18n.t('restriction_stop_sell')
                };
                return labels[type] || type;
            },

            getRoomTypeName: function (id) {
                for (var i = 0; i < this.roomTypes.length; i++) {
                    if (this.roomTypes[i].id == id) return this.roomTypes[i].name;
                }
                return '#' + id;
            },

            // ---- Helpers ----

            formatDate: function (date) {
                if (typeof NozuleUtils !== 'undefined' && NozuleUtils.formatDate) {
                    return NozuleUtils.formatDate(date);
                }
                return date || '';
            }
        };
    });
});
