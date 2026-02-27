/**
 * Nozule - Admin Housekeeping
 *
 * Task management for housekeeping: list, create, edit, assign, status updates.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlHousekeeping', function () {
        return {
            loading: true,
            saving: false,
            tasks: [],
            stats: {
                dirty: 0,
                clean: 0,
                inspected: 0,
                out_of_order: 0
            },
            filters: {
                status: '',
                priority: '',
                room_id: ''
            },
            selectedRooms: [],
            rooms: [],
            staffMembers: [],
            currentPage: 1,
            totalPages: 1,

            // Task modal state
            showTaskModal: false,
            editingTaskId: null,
            taskForm: {},

            init: function () {
                this.taskForm = this.defaultTaskForm();
                this.loadTasks();
                this.loadStats();
                this.loadRooms();
                this.loadStaff();
            },

            // ---- Default form values ----

            defaultTaskForm: function () {
                return {
                    room_id: '',
                    priority: 'normal',
                    task_type: '',
                    assigned_to: '',
                    notes: ''
                };
            },

            // ---- Data loading ----

            loadTasks: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.currentPage,
                    per_page: 20
                };
                if (self.filters.status) params.status = self.filters.status;
                if (self.filters.priority) params.priority = self.filters.priority;
                if (self.filters.room_id) params.room_id = self.filters.room_id;

                NozuleAPI.get('/admin/housekeeping', params).then(function (response) {
                    self.tasks = response.data.items || response.data || [];
                    if (response.data.pagination) {
                        self.currentPage = response.data.pagination.page || 1;
                        self.totalPages = response.data.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Housekeeping load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_tasks'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadStats: function () {
                var self = this;
                NozuleAPI.get('/admin/housekeeping/stats').then(function (response) {
                    self.stats = response.data || {
                        dirty: 0,
                        clean: 0,
                        inspected: 0,
                        out_of_order: 0
                    };
                }).catch(function (err) {
                    console.error('Housekeeping stats error:', err);
                });
            },

            loadRooms: function () {
                var self = this;
                NozuleAPI.get('/admin/rooms').then(function (response) {
                    self.rooms = response.data.items || response.data || [];
                }).catch(function (err) {
                    console.error('Housekeeping rooms load error:', err);
                });
            },

            loadStaff: function () {
                var self = this;
                NozuleAPI.get('/admin/housekeeping/staff').then(function (response) {
                    self.staffMembers = response.data.items || response.data || [];
                }).catch(function (err) {
                    console.error('Housekeeping staff load error:', err);
                });
            },

            // ---- Task CRUD ----

            openTaskModal: function () {
                this.editingTaskId = null;
                this.taskForm = this.defaultTaskForm();
                this.showTaskModal = true;
            },

            editTask: function (task) {
                this.editingTaskId = task.id;
                this.taskForm = {
                    room_id: task.room_id || '',
                    priority: task.priority || 'normal',
                    task_type: task.task_type || '',
                    assigned_to: task.assigned_to || '',
                    notes: task.notes || ''
                };
                this.showTaskModal = true;
            },

            saveTask: function () {
                var self = this;
                var data = {
                    room_id: self.taskForm.room_id ? parseInt(self.taskForm.room_id, 10) : null,
                    priority: self.taskForm.priority,
                    task_type: self.taskForm.task_type
                };

                if (self.taskForm.assigned_to) {
                    data.assigned_to = parseInt(self.taskForm.assigned_to, 10);
                }
                if (self.taskForm.notes) {
                    data.notes = self.taskForm.notes;
                }

                self.saving = true;

                var promise;
                if (self.editingTaskId) {
                    promise = NozuleAPI.put('/admin/housekeeping/' + self.editingTaskId, data);
                } else {
                    promise = NozuleAPI.post('/admin/housekeeping', data);
                }

                promise.then(function () {
                    self.showTaskModal = false;
                    self.loadTasks();
                    self.loadStats();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingTaskId ? 'task_updated' : 'task_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_task'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteTask: function (taskId) {
                if (!confirm(NozuleI18n.t('confirm_delete_task'))) return;
                var self = this;
                NozuleAPI.delete('/admin/housekeeping/' + taskId).then(function () {
                    self.loadTasks();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('task_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_task'), 'error');
                });
            },

            // ---- Status updates ----

            markClean: function (taskId) {
                var self = this;
                NozuleAPI.put('/admin/housekeeping/' + taskId + '/status', {
                    status: 'clean'
                }).then(function () {
                    self.loadTasks();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('marked_clean'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_update_status'), 'error');
                });
            },

            markInspected: function (taskId) {
                var self = this;
                NozuleAPI.put('/admin/housekeeping/' + taskId + '/status', {
                    status: 'inspected'
                }).then(function () {
                    self.loadTasks();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('marked_inspected'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_update_status'), 'error');
                });
            },

            markSelectedClean: function () {
                var self = this;
                if (self.selectedRooms.length === 0) return;

                var promises = self.selectedRooms.map(function (taskId) {
                    return NozuleAPI.put('/admin/housekeeping/' + taskId + '/status', {
                        status: 'clean'
                    });
                });

                Promise.all(promises).then(function () {
                    self.selectedRooms = [];
                    self.loadTasks();
                    self.loadStats();
                    NozuleUtils.toast(NozuleI18n.t('marked_clean'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_update_status'), 'error');
                });
            },

            // ---- Assignment ----

            assignTask: function (taskId, userId) {
                if (!userId) return;
                var self = this;
                NozuleAPI.put('/admin/housekeeping/' + taskId + '/assign', {
                    assigned_to: userId
                }).then(function () {
                    self.loadTasks();
                    NozuleUtils.toast(NozuleI18n.t('task_assigned'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_assign_task'), 'error');
                });
            },

            // ---- Selection ----

            toggleSelectAll: function (event) {
                if (event.target.checked) {
                    this.selectedRooms = this.tasks.map(function (task) {
                        return task.id;
                    });
                } else {
                    this.selectedRooms = [];
                }
            },

            // ---- Pagination ----

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadTasks();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadTasks();
                }
            },

            // ---- Helpers ----

            statusLabel: function (key) {
                return NozuleI18n.t(key);
            },

            formatDate: function (date) {
                return NozuleUtils.formatDate(date);
            }
        };
    });
});
