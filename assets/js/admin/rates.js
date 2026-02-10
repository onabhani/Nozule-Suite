/**
 * Venezia Hotel Manager - Admin Rates & Pricing
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmRates', function () {
        return {
            loading: true,
            activeTab: 'rate_plans',
            ratePlans: [],
            seasonalRates: [],

            init: function () {
                this.loadData();
            },

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
                }).finally(function () {
                    self.loading = false;
                });
            },

            openCreateModal: function (type) {
                if (type === 'rate_plan') {
                    var name = prompt('Rate Plan Name:');
                    if (!name) return;
                    var modType = prompt('Modifier Type (percentage, fixed):');
                    if (!modType) return;
                    var modValue = prompt('Modifier Value:');
                    if (modValue === null) return;

                    var self = this;
                    VeneziaAPI.post('/admin/rate-plans', {
                        name: name,
                        type: modType,
                        modifier_value: parseFloat(modValue),
                        status: 'active'
                    }).then(function () {
                        self.loadData();
                        VeneziaUtils.toast('Rate plan created', 'success');
                    }).catch(function (err) {
                        VeneziaUtils.toast(err.message, 'error');
                    });
                } else if (type === 'seasonal_rate') {
                    var name = prompt('Season Name:');
                    if (!name) return;
                    var startDate = prompt('Start Date (YYYY-MM-DD):');
                    if (!startDate) return;
                    var endDate = prompt('End Date (YYYY-MM-DD):');
                    if (!endDate) return;
                    var modifier = prompt('Modifier Value (e.g. 1.25 for 25% increase):');
                    if (modifier === null) return;

                    var self = this;
                    VeneziaAPI.post('/admin/seasonal-rates', {
                        name: name,
                        start_date: startDate,
                        end_date: endDate,
                        modifier_value: parseFloat(modifier),
                        priority: 1,
                        status: 'active'
                    }).then(function () {
                        self.loadData();
                        VeneziaUtils.toast('Seasonal rate created', 'success');
                    }).catch(function (err) {
                        VeneziaUtils.toast(err.message, 'error');
                    });
                }
            },

            editRatePlan: function (id) {
                var plan = this.ratePlans.find(function (p) { return p.id === id; });
                if (!plan) return;
                var name = prompt('Rate Plan Name:', plan.name);
                if (name === null) return;

                var self = this;
                VeneziaAPI.put('/admin/rate-plans/' + id, {
                    name: name
                }).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Rate plan updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            deleteRatePlan: function (id) {
                if (!confirm('Delete this rate plan?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/rate-plans/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Rate plan deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            editSeasonalRate: function (id) {
                var rate = this.seasonalRates.find(function (r) { return r.id === id; });
                if (!rate) return;
                var name = prompt('Season Name:', rate.name);
                if (name === null) return;

                var self = this;
                VeneziaAPI.put('/admin/seasonal-rates/' + id, {
                    name: name
                }).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Seasonal rate updated', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            deleteSeasonalRate: function (id) {
                if (!confirm('Delete this seasonal rate?')) return;
                var self = this;
                VeneziaAPI.delete('/admin/seasonal-rates/' + id).then(function () {
                    self.loadData();
                    VeneziaUtils.toast('Seasonal rate deleted', 'success');
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message, 'error');
                });
            },

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            }
        };
    });
});
