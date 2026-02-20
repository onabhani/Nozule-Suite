/**
 * Nozule - Admin WhatsApp Messaging
 *
 * WhatsApp template management, message log viewer, and API settings.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlWhatsApp', function () {
        return {
            loading: true,
            loadingLog: false,
            loadingSettings: false,
            saving: false,
            savingSettings: false,
            sendingTest: false,
            testingConnection: false,
            activeTab: 'templates',

            // Templates
            templates: [],
            showTemplateModal: false,
            editingTemplateId: null,
            templateForm: {},

            // Message log
            messageLogs: [],
            logFilters: {
                status: '',
                search: ''
            },
            logCurrentPage: 1,
            logTotalPages: 1,

            // Settings
            settingsForm: {},
            settingsMaskedToken: '',

            // Test message
            showTestModal: false,
            testPhone: '',
            testTemplateId: null,

            init: function () {
                this.templateForm = this.defaultTemplateForm();
                this.settingsForm = this.defaultSettingsForm();
                this.loadTemplates();
            },

            // ---- Default forms ----

            defaultTemplateForm: function () {
                return {
                    name: '',
                    slug: '',
                    trigger_event: '',
                    body: '',
                    body_ar: '',
                    is_active: true
                };
            },

            defaultSettingsForm: function () {
                return {
                    phone_number_id: '',
                    access_token: '',
                    business_id: '',
                    enabled: '0',
                    api_version: 'v21.0'
                };
            },

            // ---- Tab switching ----

            switchTab: function (tab) {
                this.activeTab = tab;
                if (tab === 'templates' && this.templates.length === 0) {
                    this.loadTemplates();
                }
                if (tab === 'log' && this.messageLogs.length === 0) {
                    this.loadMessageLog();
                }
                if (tab === 'settings' && !this.settingsForm.phone_number_id && !this.settingsLoaded) {
                    this.loadSettings();
                }
            },

            // ---- Template loading ----

            loadTemplates: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/whatsapp-templates').then(function (response) {
                    self.templates = response.data.items || response.data || [];
                }).catch(function (err) {
                    console.error('WhatsApp templates load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_templates'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Message log loading ----

            loadMessageLog: function () {
                var self = this;
                self.loadingLog = true;

                var params = { page: self.logCurrentPage, per_page: 30 };
                if (self.logFilters.status) params.status = self.logFilters.status;
                if (self.logFilters.search) params.search = self.logFilters.search;

                NozuleAPI.get('/admin/whatsapp-log', params).then(function (response) {
                    self.messageLogs = response.data.items || response.data || [];
                    if (response.data.pages !== undefined) {
                        self.logTotalPages = response.data.pages || 1;
                    }
                }).catch(function (err) {
                    console.error('WhatsApp log load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_message_log'), 'error');
                }).finally(function () {
                    self.loadingLog = false;
                });
            },

            // ---- Settings loading ----

            settingsLoaded: false,

            loadSettings: function () {
                var self = this;
                self.loadingSettings = true;

                NozuleAPI.get('/admin/whatsapp-settings').then(function (response) {
                    var data = response.data || {};
                    self.settingsForm.phone_number_id = data.phone_number_id || '';
                    self.settingsForm.business_id = data.business_id || '';
                    self.settingsForm.enabled = data.enabled || '0';
                    self.settingsForm.api_version = data.api_version || 'v21.0';
                    self.settingsForm.access_token = '';
                    self.settingsMaskedToken = data.access_token_masked || '';
                    self.settingsLoaded = true;
                }).catch(function (err) {
                    console.error('WhatsApp settings load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_settings'), 'error');
                }).finally(function () {
                    self.loadingSettings = false;
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
                    body: self.templateForm.body,
                    is_active: self.templateForm.is_active ? true : false
                };

                if (self.templateForm.trigger_event) data.trigger_event = self.templateForm.trigger_event;
                if (self.templateForm.body_ar) data.body_ar = self.templateForm.body_ar;

                if (!data.name || !data.slug || !data.body) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingTemplateId) {
                    promise = NozuleAPI.put('/admin/whatsapp-templates/' + self.editingTemplateId, data);
                } else {
                    promise = NozuleAPI.post('/admin/whatsapp-templates', data);
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

                NozuleAPI.delete('/admin/whatsapp-templates/' + id).then(function () {
                    self.loadTemplates();
                    NozuleUtils.toast(NozuleI18n.t('template_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_template'), 'error');
                });
            },

            // ---- Test message ----

            testTemplate: function (templateId) {
                this.testTemplateId = templateId;
                this.testPhone = '';
                this.showTestModal = true;
            },

            confirmTestMessage: function () {
                var self = this;

                if (!self.testPhone) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.sendingTest = true;

                NozuleAPI.post('/admin/whatsapp-templates/' + self.testTemplateId + '/test', {
                    phone: self.testPhone
                }).then(function (response) {
                    self.showTestModal = false;
                    NozuleUtils.toast(response.data.message || NozuleI18n.t('test_message_sent'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_send_test'), 'error');
                }).finally(function () {
                    self.sendingTest = false;
                });
            },

            // ---- Settings ----

            saveSettings: function () {
                var self = this;
                self.savingSettings = true;

                var data = {
                    phone_number_id: self.settingsForm.phone_number_id,
                    business_id: self.settingsForm.business_id,
                    enabled: self.settingsForm.enabled,
                    api_version: self.settingsForm.api_version
                };

                // Only include access_token if it was actually changed.
                if (self.settingsForm.access_token) {
                    data.access_token = self.settingsForm.access_token;
                }

                NozuleAPI.post('/admin/whatsapp-settings', data).then(function () {
                    NozuleUtils.toast(NozuleI18n.t('settings_saved'), 'success');
                    // Reload to get masked token.
                    self.loadSettings();
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_settings'), 'error');
                }).finally(function () {
                    self.savingSettings = false;
                });
            },

            testConnection: function () {
                var self = this;
                self.testingConnection = true;

                // Save current settings first, then attempt a test by sending
                // a settings save request — the server validates credentials.
                var data = {
                    phone_number_id: self.settingsForm.phone_number_id,
                    business_id: self.settingsForm.business_id,
                    enabled: self.settingsForm.enabled,
                    api_version: self.settingsForm.api_version
                };

                if (self.settingsForm.access_token) {
                    data.access_token = self.settingsForm.access_token;
                }

                NozuleAPI.post('/admin/whatsapp-settings', data).then(function () {
                    NozuleUtils.toast(NozuleI18n.t('settings_saved'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('connection_test_failed'), 'error');
                }).finally(function () {
                    self.testingConnection = false;
                });
            },

            // ---- Log pagination ----

            logPrevPage: function () {
                if (this.logCurrentPage > 1) {
                    this.logCurrentPage--;
                    this.loadMessageLog();
                }
            },

            logNextPage: function () {
                if (this.logCurrentPage < this.logTotalPages) {
                    this.logCurrentPage++;
                    this.loadMessageLog();
                }
            },

            // ---- Helpers ----

            statusLabel: function (key) {
                return NozuleI18n.t(key);
            },

            truncateBody: function (body) {
                if (!body) return '';
                return body.length > 80 ? body.substring(0, 80) + '...' : body;
            }
        };
    });
});
