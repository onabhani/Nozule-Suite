/**
 * Nozule - Admin Messaging
 *
 * Email template management and email log viewer.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlMessaging', function () {
        return {
            loading: true,
            loadingLog: false,
            saving: false,
            activeTab: 'templates',

            // Templates
            templates: [],
            showTemplateModal: false,
            editingTemplateId: null,
            templateForm: {},

            // Email log
            emailLogs: [],
            logFilters: {
                status: '',
                search: ''
            },
            logCurrentPage: 1,
            logTotalPages: 1,

            init: function () {
                this.templateForm = this.defaultTemplateForm();
                this.loadTemplates();
            },

            // ---- Default forms ----

            defaultTemplateForm: function () {
                return {
                    name: '',
                    slug: '',
                    trigger_event: '',
                    subject: '',
                    subject_ar: '',
                    body: '',
                    body_ar: '',
                    is_active: true
                };
            },

            // ---- Tab switching ----

            switchTab: function (tab) {
                this.activeTab = tab;
                if (tab === 'templates' && this.templates.length === 0) {
                    this.loadTemplates();
                }
                if (tab === 'log' && this.emailLogs.length === 0) {
                    this.loadEmailLog();
                }
            },

            // ---- Template loading ----

            loadTemplates: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/email-templates').then(function (response) {
                    self.templates = response.data.items || response.data || [];
                }).catch(function (err) {
                    console.error('Templates load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_templates'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Email log loading ----

            loadEmailLog: function () {
                var self = this;
                self.loadingLog = true;

                var params = { page: self.logCurrentPage, per_page: 30 };
                if (self.logFilters.status) params.status = self.logFilters.status;
                if (self.logFilters.search) params.search = self.logFilters.search;

                NozuleAPI.get('/admin/email-log', params).then(function (response) {
                    self.emailLogs = response.data.items || response.data || [];
                    if (response.data.pages !== undefined) {
                        self.logTotalPages = response.data.pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Email log load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_email_log'), 'error');
                }).finally(function () {
                    self.loadingLog = false;
                });
            },

            // ---- Template CRUD ----

            openTemplateModal: function () {
                this.editingTemplateId = null;
                this.templateForm = this.defaultTemplateForm();
                this.showTemplateModal = true;
            },

            editTemplate: function (tpl) {
                this.editingTemplateId = tpl.id;
                this.templateForm = {
                    name: tpl.name || '',
                    slug: tpl.slug || '',
                    trigger_event: tpl.trigger_event || '',
                    subject: tpl.subject || '',
                    subject_ar: tpl.subject_ar || '',
                    body: tpl.body || '',
                    body_ar: tpl.body_ar || '',
                    is_active: tpl.is_active !== undefined ? tpl.is_active : true
                };
                this.showTemplateModal = true;
            },

            saveTemplate: function () {
                var self = this;
                var data = {
                    name: self.templateForm.name,
                    slug: self.templateForm.slug,
                    subject: self.templateForm.subject,
                    body: self.templateForm.body,
                    is_active: self.templateForm.is_active ? true : false
                };

                if (self.templateForm.trigger_event) data.trigger_event = self.templateForm.trigger_event;
                if (self.templateForm.subject_ar) data.subject_ar = self.templateForm.subject_ar;
                if (self.templateForm.body_ar) data.body_ar = self.templateForm.body_ar;

                if (!data.name || !data.slug || !data.subject || !data.body) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingTemplateId) {
                    promise = NozuleAPI.put('/admin/email-templates/' + self.editingTemplateId, data);
                } else {
                    promise = NozuleAPI.post('/admin/email-templates', data);
                }

                promise.then(function () {
                    self.showTemplateModal = false;
                    self.loadTemplates();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingTemplateId ? 'template_updated' : 'template_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_template'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteTemplate: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_template'))) return;
                var self = this;

                NozuleAPI.delete('/admin/email-templates/' + id).then(function () {
                    self.loadTemplates();
                    NozuleUtils.toast(NozuleI18n.t('template_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_template'), 'error');
                });
            },

            // ---- Template actions ----

            sendTestEmail: function (templateId) {
                var self = this;
                self.saving = true;

                NozuleAPI.post('/admin/email-templates/' + templateId + '/test').then(function () {
                    NozuleUtils.toast(NozuleI18n.t('test_email_sent'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_send_test'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Log pagination ----

            logPrevPage: function () {
                if (this.logCurrentPage > 1) {
                    this.logCurrentPage--;
                    this.loadEmailLog();
                }
            },

            logNextPage: function () {
                if (this.logCurrentPage < this.logTotalPages) {
                    this.logCurrentPage++;
                    this.loadEmailLog();
                }
            },

            // ---- Helpers ----

            statusLabel: function (key) {
                return NozuleI18n.t(key);
            }
        };
    });
});
