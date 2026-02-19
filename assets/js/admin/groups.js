/**
 * Nozule - Admin Groups
 *
 * Group booking management: list, create, edit, detail with room assignments.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlGroups', function () {
        return {
            loading: true,
            saving: false,
            groups: [],
            filters: {
                status: '',
                search: '',
                from: '',
                to: ''
            },
            currentPage: 1,
            totalPages: 1,

            // Group modal state
            showGroupModal: false,
            editingGroupId: null,
            groupForm: {},

            // Group detail state
            showDetailPanel: false,
            loadingDetail: false,
            selectedGroup: null,
            roomingList: [],

            // Room assignment
            showAddRoomForm: false,
            roomingForm: {},
            savingRoom: false,
            roomTypes: [],
            availableRooms: [],

            init: function () {
                this.loadGroups();
                this.loadRoomTypes();
            },

            // ---- Default form values ----

            defaultGroupForm: function () {
                return {
                    group_name: '',
                    group_name_ar: '',
                    agency_name: '',
                    agency_name_ar: '',
                    contact_person: '',
                    contact_phone: '',
                    contact_email: '',
                    check_in: '',
                    check_out: '',
                    currency: 'SYP',
                    payment_terms: '',
                    notes: ''
                };
            },

            defaultRoomingForm: function () {
                return {
                    room_type_id: '',
                    guest_name: '',
                    rate_per_night: ''
                };
            },

            // ---- Data loading ----

            loadGroups: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.currentPage,
                    per_page: 20
                };
                if (self.filters.status) params.status = self.filters.status;
                if (self.filters.search) params.search = self.filters.search;
                if (self.filters.from) params.date_from = self.filters.from;
                if (self.filters.to) params.date_to = self.filters.to;

                NozuleAPI.get('/admin/groups', params).then(function (response) {
                    self.groups = response.data.items || response.data || [];
                    if (response.data.pagination) {
                        self.currentPage = response.data.pagination.page || 1;
                        self.totalPages = response.data.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Groups load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_groups'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadRoomTypes: function () {
                var self = this;
                NozuleAPI.get('/admin/room-types').then(function (response) {
                    self.roomTypes = response.data || [];
                }).catch(function (err) {
                    console.error('Room types load error:', err);
                });
            },

            // ---- Group CRUD ----

            openGroupModal: function () {
                this.editingGroupId = null;
                this.groupForm = this.defaultGroupForm();
                this.showGroupModal = true;
            },

            editGroup: function (group) {
                this.editingGroupId = group.id;
                this.groupForm = {
                    group_name: group.group_name || '',
                    group_name_ar: group.group_name_ar || '',
                    agency_name: group.agency_name || '',
                    agency_name_ar: group.agency_name_ar || '',
                    contact_person: group.contact_person || '',
                    contact_phone: group.contact_phone || '',
                    contact_email: group.contact_email || '',
                    check_in: group.check_in || '',
                    check_out: group.check_out || '',
                    currency: group.currency || 'SYP',
                    payment_terms: group.payment_terms || '',
                    notes: group.notes || ''
                };
                this.showGroupModal = true;
            },

            editGroupFromDetail: function () {
                if (this.selectedGroup) {
                    this.showDetailPanel = false;
                    this.editGroup(this.selectedGroup);
                }
            },

            saveGroup: function () {
                var self = this;
                var data = {
                    group_name: self.groupForm.group_name,
                    check_in: self.groupForm.check_in,
                    check_out: self.groupForm.check_out,
                    currency: self.groupForm.currency
                };

                // Include optional fields only if filled
                if (self.groupForm.group_name_ar) data.group_name_ar = self.groupForm.group_name_ar;
                if (self.groupForm.agency_name) data.agency_name = self.groupForm.agency_name;
                if (self.groupForm.agency_name_ar) data.agency_name_ar = self.groupForm.agency_name_ar;
                if (self.groupForm.contact_person) data.contact_person = self.groupForm.contact_person;
                if (self.groupForm.contact_phone) data.contact_phone = self.groupForm.contact_phone;
                if (self.groupForm.contact_email) data.contact_email = self.groupForm.contact_email;
                if (self.groupForm.payment_terms) data.payment_terms = self.groupForm.payment_terms;
                if (self.groupForm.notes) data.notes = self.groupForm.notes;

                if (!data.group_name || !data.check_in || !data.check_out) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingGroupId) {
                    promise = NozuleAPI.put('/admin/groups/' + self.editingGroupId, data);
                } else {
                    promise = NozuleAPI.post('/admin/groups', data);
                }

                promise.then(function () {
                    self.showGroupModal = false;
                    self.loadGroups();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingGroupId ? 'group_updated' : 'group_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_group'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Group detail ----

            viewGroup: function (groupId) {
                var self = this;
                self.loadingDetail = true;
                self.showDetailPanel = true;
                self.selectedGroup = null;
                self.roomingList = [];
                self.showAddRoomForm = false;
                self.roomingForm = self.defaultRoomingForm();

                NozuleAPI.get('/admin/groups/' + groupId).then(function (response) {
                    self.selectedGroup = response.data || null;
                    self.roomingList = response.data.rooms || [];
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_group'), 'error');
                    self.showDetailPanel = false;
                }).finally(function () {
                    self.loadingDetail = false;
                });
            },

            closeDetail: function () {
                this.showDetailPanel = false;
                this.selectedGroup = null;
                this.roomingList = [];
                this.showAddRoomForm = false;
            },

            // ---- Group actions ----

            confirmGroup: function (groupId) {
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/groups/' + groupId + '/confirm').then(function () {
                    self.loadGroups();
                    if (self.selectedGroup && self.selectedGroup.id === groupId) {
                        self.viewGroup(groupId);
                    }
                    NozuleUtils.toast(NozuleI18n.t('group_confirmed'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_confirm_group'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            checkInGroup: function (groupId) {
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/groups/' + groupId + '/check-in').then(function () {
                    self.loadGroups();
                    if (self.selectedGroup && self.selectedGroup.id === groupId) {
                        self.viewGroup(groupId);
                    }
                    NozuleUtils.toast(NozuleI18n.t('group_checked_in'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_check_in_group'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            checkOutGroup: function (groupId) {
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/groups/' + groupId + '/check-out').then(function () {
                    self.loadGroups();
                    if (self.selectedGroup && self.selectedGroup.id === groupId) {
                        self.viewGroup(groupId);
                    }
                    NozuleUtils.toast(NozuleI18n.t('group_checked_out'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_check_out_group'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            cancelGroup: function (groupId) {
                if (!confirm(NozuleI18n.t('confirm_cancel_group'))) return;
                var self = this;
                self.saving = true;

                NozuleAPI.delete('/admin/groups/' + groupId).then(function () {
                    self.loadGroups();
                    self.closeDetail();
                    NozuleUtils.toast(NozuleI18n.t('group_cancelled'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_cancel_group'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Room assignments ----

            addRoomToGroup: function () {
                var self = this;
                if (!self.selectedGroup) return;

                var data = {
                    room_type_id: parseInt(self.roomingForm.room_type_id, 10) || 0,
                    rate_per_night: parseFloat(self.roomingForm.rate_per_night) || 0
                };

                if (self.roomingForm.guest_name) {
                    data.guest_name = self.roomingForm.guest_name;
                }

                if (!data.room_type_id) {
                    NozuleUtils.toast(NozuleI18n.t('select_room_type'), 'error');
                    return;
                }

                self.savingRoom = true;

                NozuleAPI.post('/admin/groups/' + self.selectedGroup.id + '/rooms', data).then(function () {
                    self.roomingForm = self.defaultRoomingForm();
                    self.showAddRoomForm = false;
                    self.viewGroup(self.selectedGroup.id);
                    NozuleUtils.toast(NozuleI18n.t('room_added_to_group'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_add_room'), 'error');
                }).finally(function () {
                    self.savingRoom = false;
                });
            },

            removeRoomFromGroup: function (entryId) {
                if (!confirm(NozuleI18n.t('confirm_remove_room'))) return;
                var self = this;

                NozuleAPI.delete('/admin/groups/rooms/' + entryId).then(function () {
                    if (self.selectedGroup) {
                        self.viewGroup(self.selectedGroup.id);
                    }
                    NozuleUtils.toast(NozuleI18n.t('room_removed_from_group'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_remove_room'), 'error');
                });
            },

            assignRoomToEntry: function (entryId, roomId) {
                if (!roomId) return;
                var self = this;

                NozuleAPI.put('/admin/groups/rooms/' + entryId + '/assign', {
                    room_id: parseInt(roomId, 10)
                }).then(function () {
                    if (self.selectedGroup) {
                        self.viewGroup(self.selectedGroup.id);
                    }
                    NozuleUtils.toast(NozuleI18n.t('room_assigned'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_assign_room'), 'error');
                });
            },

            getAvailableRooms: function (roomTypeId) {
                if (!roomTypeId) return [];
                return (this.availableRooms || []).filter(function (room) {
                    return room.room_type_id === roomTypeId && room.status === 'available';
                });
            },

            loadAvailableRooms: function () {
                var self = this;
                NozuleAPI.get('/admin/rooms', { status: 'available' }).then(function (response) {
                    self.availableRooms = response.data || [];
                }).catch(function (err) {
                    console.error('Available rooms load error:', err);
                });
            },

            // ---- Pagination ----

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadGroups();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadGroups();
                }
            },

            // ---- Helpers ----

            statusLabel: function (status) {
                return NozuleI18n.t(status);
            },

            formatPrice: function (amount) {
                return NozuleUtils.formatPrice(amount);
            },

            formatDate: function (date) {
                return NozuleUtils.formatDate(date);
            }
        };
    });
});
