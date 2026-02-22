/**
 * Nozule - Admin Loyalty Program (NZL-036)
 *
 * Alpine.js component for loyalty management:
 * members, tiers, rewards, dashboard stats.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlLoyalty', function () {
        return {
            // ---- Tab state ----
            activeTab: 'members',
            loading: true,
            saving: false,

            // ---- Members ----
            members: [],
            memberPage: 1,
            memberTotalPages: 1,
            memberFilters: {
                search: '',
                tier_id: ''
            },

            // ---- Tiers ----
            tiers: [],
            tiersLoaded: false,

            // ---- Rewards ----
            rewards: [],
            rewardsLoaded: false,

            // ---- Dashboard stats ----
            stats: {
                total_members: 0,
                points_issued: 0,
                rewards_redeemed: 0,
                active_this_month: 0
            },
            statsLoaded: false,

            // ---- Member detail modal ----
            showMemberModal: false,
            loadingMember: false,
            selectedMember: null,

            // ---- Enroll modal ----
            showEnrollModal: false,
            guestSearch: '',
            guestSearchResults: [],
            searchingGuests: false,
            enrollGuestId: null,

            // ---- Adjust points modal ----
            showAdjustModal: false,
            adjustForm: {
                points: '',
                description: ''
            },

            // ---- Redeem modal ----
            showRedeemModal: false,
            redeemRewardId: null,

            // ---- Tier modal ----
            showTierModal: false,
            tierForm: {},

            // ---- Reward modal ----
            showRewardModal: false,
            rewardForm: {},

            // ================================================================
            // INIT
            // ================================================================

            init: function () {
                var self = this;
                self.tierForm = self.defaultTierForm();
                self.rewardForm = self.defaultRewardForm();

                // Load tiers first (needed for member filter dropdown)
                self.loadTiers(function () {
                    self.loadMembers();
                });
            },

            // ================================================================
            // DEFAULT FORMS
            // ================================================================

            defaultTierForm: function () {
                return {
                    id: null,
                    name: '',
                    name_ar: '',
                    min_points: 0,
                    discount_percent: 0,
                    benefits: '',
                    benefits_ar: '',
                    color: '#CD7F32',
                    sort_order: 0
                };
            },

            defaultRewardForm: function () {
                return {
                    id: null,
                    name: '',
                    name_ar: '',
                    points_cost: '',
                    type: 'discount',
                    value: '',
                    description: '',
                    description_ar: '',
                    is_active: true
                };
            },

            // ================================================================
            // TAB SWITCHING
            // ================================================================

            switchTab: function (tab) {
                var self = this;
                self.activeTab = tab;

                if (tab === 'members' && self.members.length === 0) {
                    self.loadMembers();
                } else if (tab === 'tiers' && !self.tiersLoaded) {
                    self.loadTiers();
                } else if (tab === 'rewards' && !self.rewardsLoaded) {
                    self.loadRewards();
                } else if (tab === 'dashboard' && !self.statsLoaded) {
                    self.loadStats();
                }
            },

            // ================================================================
            // DATA LOADING
            // ================================================================

            loadMembers: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.memberPage,
                    per_page: 20
                };
                if (self.memberFilters.search) params.search = self.memberFilters.search;
                if (self.memberFilters.tier_id) params.tier_id = self.memberFilters.tier_id;

                NozuleAPI.get('/admin/loyalty/members', params).then(function (response) {
                    var data = response.data || {};
                    self.members = data.items || [];
                    if (data.pagination) {
                        self.memberTotalPages = data.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Loyalty members load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_members'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadTiers: function (callback) {
                var self = this;
                if (self.activeTab === 'tiers') self.loading = true;

                NozuleAPI.get('/admin/loyalty/tiers').then(function (response) {
                    self.tiers = response.data || [];
                    self.tiersLoaded = true;
                }).catch(function (err) {
                    console.error('Loyalty tiers load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_tiers'), 'error');
                }).finally(function () {
                    if (self.activeTab === 'tiers') self.loading = false;
                    if (typeof callback === 'function') callback();
                });
            },

            loadRewards: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/loyalty/rewards').then(function (response) {
                    self.rewards = response.data || [];
                    self.rewardsLoaded = true;
                }).catch(function (err) {
                    console.error('Loyalty rewards load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_rewards'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadStats: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/loyalty/stats').then(function (response) {
                    self.stats = response.data || self.stats;
                    self.statsLoaded = true;
                }).catch(function (err) {
                    console.error('Loyalty stats load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_stats'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ================================================================
            // MEMBER OPERATIONS
            // ================================================================

            viewMember: function (id) {
                var self = this;
                self.selectedMember = null;
                self.loadingMember = true;
                self.showMemberModal = true;

                NozuleAPI.get('/admin/loyalty/members/' + id).then(function (response) {
                    self.selectedMember = response.data;
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_member'), 'error');
                    self.showMemberModal = false;
                }).finally(function () {
                    self.loadingMember = false;
                });
            },

            openEnrollModal: function () {
                this.guestSearch = '';
                this.guestSearchResults = [];
                this.enrollGuestId = null;
                this.showEnrollModal = true;
            },

            searchGuests: function () {
                var self = this;
                if (!self.guestSearch || self.guestSearch.length < 2) {
                    self.guestSearchResults = [];
                    return;
                }

                self.searchingGuests = true;

                NozuleAPI.get('/guests', { search: self.guestSearch, per_page: 10 }).then(function (response) {
                    self.guestSearchResults = response.data || response || [];
                    // Handle both wrapped and unwrapped response
                    if (response.data && response.data.items) {
                        self.guestSearchResults = response.data.items;
                    }
                }).catch(function (err) {
                    console.error('Guest search error:', err);
                    self.guestSearchResults = [];
                }).finally(function () {
                    self.searchingGuests = false;
                });
            },

            selectGuestForEnroll: function (guest) {
                this.enrollGuestId = guest.id;
            },

            enrollGuest: function () {
                var self = this;
                if (!self.enrollGuestId) return;

                self.saving = true;

                NozuleAPI.post('/admin/loyalty/members', {
                    guest_id: self.enrollGuestId
                }).then(function (response) {
                    self.showEnrollModal = false;
                    self.loadMembers();
                    NozuleUtils.toast(response.message || NozuleI18n.t('member_created'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_member'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Adjust Points ----

            openAdjustModal: function () {
                this.adjustForm = { points: '', description: '' };
                this.showAdjustModal = true;
            },

            submitAdjust: function () {
                var self = this;
                if (!self.adjustForm.points || !self.selectedMember) return;

                self.saving = true;

                NozuleAPI.post('/admin/loyalty/members/' + self.selectedMember.id + '/adjust', {
                    points: parseInt(self.adjustForm.points, 10),
                    description: self.adjustForm.description
                }).then(function (response) {
                    self.showAdjustModal = false;
                    NozuleUtils.toast(response.message || NozuleI18n.t('points_awarded'), 'success');
                    // Refresh member detail
                    self.viewMember(self.selectedMember.id);
                    self.loadMembers();
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_award_points'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Redeem Reward ----

            openRedeemModal: function () {
                var self = this;
                self.redeemRewardId = null;

                // Ensure rewards are loaded
                if (!self.rewardsLoaded) {
                    NozuleAPI.get('/admin/loyalty/rewards').then(function (response) {
                        self.rewards = response.data || [];
                        self.rewardsLoaded = true;
                        self.showRedeemModal = true;
                    }).catch(function (err) {
                        NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_rewards'), 'error');
                    });
                } else {
                    self.showRedeemModal = true;
                }
            },

            submitRedeem: function () {
                var self = this;
                if (!self.redeemRewardId || !self.selectedMember) return;

                self.saving = true;

                NozuleAPI.post('/admin/loyalty/members/' + self.selectedMember.id + '/redeem/' + self.redeemRewardId).then(function (response) {
                    self.showRedeemModal = false;
                    NozuleUtils.toast(response.message || NozuleI18n.t('reward_redeemed'), 'success');
                    // Refresh member detail
                    self.viewMember(self.selectedMember.id);
                    self.loadMembers();
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_redeem_reward'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ================================================================
            // TIER CRUD
            // ================================================================

            openTierModal: function () {
                this.tierForm = this.defaultTierForm();
                this.showTierModal = true;
            },

            editTier: function (tier) {
                this.tierForm = {
                    id: tier.id,
                    name: tier.name || '',
                    name_ar: tier.name_ar || '',
                    min_points: tier.min_points || 0,
                    discount_percent: tier.discount_percent || 0,
                    benefits: tier.benefits || '',
                    benefits_ar: tier.benefits_ar || '',
                    color: tier.color || '#CD7F32',
                    sort_order: tier.sort_order || 0
                };
                this.showTierModal = true;
            },

            saveTier: function () {
                var self = this;

                if (!self.tierForm.name) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var data = {
                    name: self.tierForm.name,
                    name_ar: self.tierForm.name_ar,
                    min_points: parseInt(self.tierForm.min_points, 10) || 0,
                    discount_percent: parseFloat(self.tierForm.discount_percent) || 0,
                    benefits: self.tierForm.benefits,
                    benefits_ar: self.tierForm.benefits_ar,
                    color: self.tierForm.color,
                    sort_order: parseInt(self.tierForm.sort_order, 10) || 0
                };

                if (self.tierForm.id) {
                    data.id = self.tierForm.id;
                }

                NozuleAPI.post('/admin/loyalty/tiers', data).then(function (response) {
                    self.showTierModal = false;
                    self.loadTiers();
                    NozuleUtils.toast(response.message || NozuleI18n.t('saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteTier: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete'))) return;
                var self = this;

                NozuleAPI.delete('/admin/loyalty/tiers/' + id).then(function (response) {
                    self.loadTiers();
                    NozuleUtils.toast(response.message || NozuleI18n.t('deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
                });
            },

            // ================================================================
            // REWARD CRUD
            // ================================================================

            openRewardModal: function () {
                this.rewardForm = this.defaultRewardForm();
                this.showRewardModal = true;
            },

            editReward: function (reward) {
                this.rewardForm = {
                    id: reward.id,
                    name: reward.name || '',
                    name_ar: reward.name_ar || '',
                    points_cost: reward.points_cost || '',
                    type: reward.type || 'discount',
                    value: reward.value || '',
                    description: reward.description || '',
                    description_ar: reward.description_ar || '',
                    is_active: reward.is_active !== undefined ? reward.is_active : true
                };
                this.showRewardModal = true;
            },

            saveReward: function () {
                var self = this;

                if (!self.rewardForm.name || !self.rewardForm.points_cost) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var data = {
                    name: self.rewardForm.name,
                    name_ar: self.rewardForm.name_ar,
                    points_cost: parseInt(self.rewardForm.points_cost, 10) || 0,
                    type: self.rewardForm.type,
                    value: self.rewardForm.value,
                    description: self.rewardForm.description,
                    description_ar: self.rewardForm.description_ar,
                    is_active: self.rewardForm.is_active ? true : false
                };

                if (self.rewardForm.id) {
                    data.id = self.rewardForm.id;
                }

                NozuleAPI.post('/admin/loyalty/rewards', data).then(function (response) {
                    self.showRewardModal = false;
                    self.loadRewards();
                    NozuleUtils.toast(response.message || NozuleI18n.t('saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteReward: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete'))) return;
                var self = this;

                NozuleAPI.delete('/admin/loyalty/rewards/' + id).then(function (response) {
                    self.loadRewards();
                    NozuleUtils.toast(response.message || NozuleI18n.t('deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
                });
            },

            // ================================================================
            // PAGINATION
            // ================================================================

            prevMemberPage: function () {
                if (this.memberPage > 1) {
                    this.memberPage--;
                    this.loadMembers();
                }
            },

            nextMemberPage: function () {
                if (this.memberPage < this.memberTotalPages) {
                    this.memberPage++;
                    this.loadMembers();
                }
            },

            // ================================================================
            // HELPERS
            // ================================================================

            formatDate: function (dateStr) {
                if (!dateStr) return '—';
                var d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                return d.toLocaleDateString();
            },

            txTypeLabel: function (type) {
                var labels = {
                    earn: NozuleI18n.t('tx_earn') || 'Earn',
                    redeem: NozuleI18n.t('tx_redeem') || 'Redeem',
                    adjust: NozuleI18n.t('tx_adjust') || 'Adjust'
                };
                return labels[type] || type;
            },

            rewardTypeLabel: function (type) {
                var labels = {
                    discount: NozuleI18n.t('reward_discount') || 'Discount',
                    free_night: NozuleI18n.t('reward_free_night') || 'Free Night',
                    upgrade: NozuleI18n.t('reward_upgrade') || 'Room Upgrade',
                    amenity: NozuleI18n.t('reward_amenity') || 'Amenity'
                };
                return labels[type] || type;
            }
        };
    });
});
