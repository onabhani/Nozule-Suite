/**
 * Nozule - Admin Branding (NZL-041)
 *
 * White-label brand management: list, create, edit, delete, set default.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlBranding', function () {
        return {
            loading: true,
            saving: false,
            brands: [],

            // Modal state
            showBrandModal: false,
            editingBrandId: null,
            brandForm: {},

            init: function () {
                this.brandForm = this.defaultBrandForm();
                this.loadBrands();
            },

            // ---- Default form ----

            defaultBrandForm: function () {
                return {
                    name: '',
                    name_ar: '',
                    logo_url: '',
                    favicon_url: '',
                    primary_color: '#1e40af',
                    secondary_color: '#3b82f6',
                    accent_color: '#f59e0b',
                    text_color: '#1e293b',
                    custom_css: '',
                    email_header_html: '',
                    email_footer_html: '',
                    is_active: true
                };
            },

            // ---- Data loading ----

            loadBrands: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/branding/brands').then(function (response) {
                    self.brands = response.data || [];
                }).catch(function (err) {
                    console.error('Brands load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_brands'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Modal ----

            openBrandModal: function (brand) {
                if (brand) {
                    this.editingBrandId = brand.id;
                    this.brandForm = {
                        name: brand.name || '',
                        name_ar: brand.name_ar || '',
                        logo_url: brand.logo_url || '',
                        favicon_url: brand.favicon_url || '',
                        primary_color: brand.primary_color || '#1e40af',
                        secondary_color: brand.secondary_color || '#3b82f6',
                        accent_color: brand.accent_color || '#f59e0b',
                        text_color: brand.text_color || '#1e293b',
                        custom_css: brand.custom_css || '',
                        email_header_html: brand.email_header_html || '',
                        email_footer_html: brand.email_footer_html || '',
                        is_active: brand.is_active !== undefined ? brand.is_active : true
                    };
                } else {
                    this.editingBrandId = null;
                    this.brandForm = this.defaultBrandForm();
                }
                this.showBrandModal = true;
            },

            // ---- CRUD ----

            saveBrand: function () {
                var self = this;
                var data = {
                    name: self.brandForm.name,
                    primary_color: self.brandForm.primary_color,
                    secondary_color: self.brandForm.secondary_color,
                    accent_color: self.brandForm.accent_color,
                    text_color: self.brandForm.text_color,
                    is_active: self.brandForm.is_active ? true : false
                };

                // Optional fields
                if (self.brandForm.name_ar) data.name_ar = self.brandForm.name_ar;
                if (self.brandForm.logo_url) data.logo_url = self.brandForm.logo_url;
                if (self.brandForm.favicon_url) data.favicon_url = self.brandForm.favicon_url;
                if (self.brandForm.custom_css) data.custom_css = self.brandForm.custom_css;
                if (self.brandForm.email_header_html) data.email_header_html = self.brandForm.email_header_html;
                if (self.brandForm.email_footer_html) data.email_footer_html = self.brandForm.email_footer_html;

                if (!data.name) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingBrandId) {
                    promise = NozuleAPI.put('/admin/branding/brands/' + self.editingBrandId, data);
                } else {
                    promise = NozuleAPI.post('/admin/branding/brands', data);
                }

                promise.then(function () {
                    self.showBrandModal = false;
                    self.loadBrands();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingBrandId ? 'brand_updated' : 'brand_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_brand'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteBrand: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_brand'))) return;
                var self = this;

                NozuleAPI.delete('/admin/branding/brands/' + id).then(function () {
                    self.loadBrands();
                    NozuleUtils.toast(NozuleI18n.t('brand_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_brand'), 'error');
                });
            },

            setDefault: function (id) {
                var self = this;

                NozuleAPI.post('/admin/branding/brands/' + id + '/set-default', {}).then(function () {
                    self.loadBrands();
                    NozuleUtils.toast(NozuleI18n.t('brand_set_default'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_set_default_brand'), 'error');
                });
            }
        };
    });
});
