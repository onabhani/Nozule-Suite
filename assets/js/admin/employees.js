/**
 * Nozule - Admin Employees (NZL-042)
 *
 * Staff management: list, create, edit, deactivate, assign capabilities.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlEmployees', function () {
        return {
            loading: true,
            saving: false,
            employees: [],
            allCapabilities: [],
            loadError: '',

            showModal: false,
            editingId: null,
            form: {},

            isArabic: (window.NozuleAdmin && NozuleAdmin.locale || '').indexOf('ar') === 0,
            currentUserId: window.NozuleAdmin ? NozuleAdmin.userId : 0,

            init: function () {
                this.form = this.defaultForm();
                this.loadCapabilities();
                this.loadEmployees();
            },

            defaultForm: function () {
                return {
                    display_name: '',
                    email: '',
                    username: '',
                    password: '',
                    role: 'nzl_reception',
                    capabilities: []
                };
            },

            // ---- Role preset capabilities ----

            rolePresets: {
                nzl_manager: [
                    'nzl_admin', 'nzl_staff', 'nzl_manage_rooms', 'nzl_manage_rates',
                    'nzl_manage_inventory', 'nzl_manage_bookings', 'nzl_manage_guests',
                    'nzl_view_reports', 'nzl_view_calendar', 'nzl_manage_channels',
                    'nzl_manage_settings', 'nzl_manage_employees',
                    'nzl_manage_housekeeping', 'nzl_manage_billing',
                    'nzl_manage_pos', 'nzl_manage_messaging'
                ],
                nzl_reception: [
                    'nzl_staff', 'nzl_manage_bookings', 'nzl_manage_guests',
                    'nzl_view_calendar', 'nzl_manage_billing'
                ],
                nzl_housekeeper: [
                    'nzl_staff', 'nzl_manage_housekeeping', 'nzl_view_calendar'
                ],
                nzl_finance: [
                    'nzl_staff', 'nzl_manage_billing', 'nzl_view_reports',
                    'nzl_manage_rates', 'nzl_manage_pos'
                ],
                nzl_concierge: [
                    'nzl_staff', 'nzl_manage_guests', 'nzl_manage_bookings',
                    'nzl_view_calendar', 'nzl_manage_messaging'
                ]
            },

            applyRolePreset: function () {
                var preset = this.rolePresets[this.form.role];
                this.form.capabilities = preset ? preset.slice() : ['nzl_staff'];
            },

            // ---- Self-edit check ----

            isSelf: function () {
                return this.editingId && this.editingId === this.currentUserId;
            },

            // ---- Data loading ----

            loadEmployees: function () {
                var self = this;
                self.loading = true;
                self.loadError = '';

                NozuleAPI.get('/admin/employees').then(function (response) {
                    self.employees = response.data || [];
                }).catch(function (err) {
                    var msg = err.message || NozuleI18n.t('failed_load_employees');
                    self.loadError = msg;
                    console.error('[Nozule] Failed to load employees:', msg, err);
                    NozuleUtils.toast(msg, 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadCapabilities: function () {
                var self = this;

                NozuleAPI.get('/admin/employees/capabilities').then(function (response) {
                    self.allCapabilities = response.data || [];
                }).catch(function () {
                    // Fallback — capabilities will be empty.
                });
            },

            // ---- Modal ----

            openModal: function (emp) {
                if (emp) {
                    this.editingId = emp.id;
                    this.form = {
                        display_name: emp.display_name || '',
                        email: emp.email || '',
                        username: emp.username || '',
                        password: '',
                        role: emp.role || 'nzl_reception',
                        capabilities: emp.capabilities ? emp.capabilities.slice() : []
                    };
                } else {
                    this.editingId = null;
                    this.form = this.defaultForm();
                    this.applyRolePreset();
                }
                this.showModal = true;
            },

            // ---- CRUD ----

            save: function () {
                var self = this;
                var data = {
                    display_name: self.form.display_name,
                    email: self.form.email
                };

                // Only send role/capabilities when NOT editing self.
                if (!self.isSelf()) {
                    data.role = self.form.role;
                    data.capabilities = self.form.capabilities;
                }

                if (self.form.password) {
                    data.password = self.form.password;
                }

                if (!self.editingId) {
                    data.username = self.form.username;
                    data.password = self.form.password;
                    data.role = self.form.role;
                    data.capabilities = self.form.capabilities;

                    if (!data.display_name || !data.username || !data.email || !data.password) {
                        NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                        return;
                    }
                } else {
                    if (!data.display_name || !data.email) {
                        NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                        return;
                    }
                }

                self.saving = true;

                var promise;
                if (self.editingId) {
                    promise = NozuleAPI.put('/admin/employees/' + self.editingId, data);
                } else {
                    promise = NozuleAPI.post('/admin/employees', data);
                }

                promise.then(function () {
                    self.showModal = false;
                    self.loadEmployees();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingId ? 'employee_updated' : 'employee_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_employee'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deactivateEmployee: function (id) {
                if (!confirm(NozuleI18n.t('confirm_deactivate_employee'))) return;
                var self = this;

                NozuleAPI.delete('/admin/employees/' + id).then(function () {
                    self.loadEmployees();
                    NozuleUtils.toast(NozuleI18n.t('employee_deactivated'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_deactivate_employee'), 'error');
                });
            },

            // ---- Helpers ----

            roleLabel: function (role) {
                var labels = {
                    'nzl_manager': NozuleI18n.t('role_manager'),
                    'nzl_reception': NozuleI18n.t('role_reception'),
                    'nzl_housekeeper': NozuleI18n.t('role_housekeeper') || 'Housekeeper',
                    'nzl_finance': NozuleI18n.t('role_finance') || 'Finance',
                    'nzl_concierge': NozuleI18n.t('role_concierge') || 'Concierge'
                };
                return labels[role] || role;
            },

            formatDate: function (dateStr) {
                if (!dateStr) return '—';
                var d = new Date(dateStr);
                return d.toLocaleDateString();
            }
        };
    });
});
