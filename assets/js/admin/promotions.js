/**
 * Nozule - Admin Promotions
 *
 * Promo code management: list, create, edit, delete.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlPromotions', function () {
        return {
            loading: true,
            saving: false,
            promoCodes: [],
            filters: {
                status: '',
                search: ''
            },
            currentPage: 1,
            totalPages: 1,

            // Modal state
            showModal: false,
            editingId: null,
            form: {},

            init: function () {
                this.form = this.defaultForm();
                this.loadPromoCodes();
            },

            // ---- Default form ----

            defaultForm: function () {
                return {
                    code: '',
                    name: '',
                    name_ar: '',
                    description: '',
                    description_ar: '',
                    discount_type: 'percentage',
                    discount_value: '',
                    currency_code: 'SYP',
                    min_nights: '',
                    min_amount: '',
                    max_discount: '',
                    max_uses: '',
                    per_guest_limit: '',
                    valid_from: '',
                    valid_to: '',
                    is_active: true
                };
            },

            // ---- Data loading ----

            loadPromoCodes: function () {
                var self = this;
                self.loading = true;

                var params = { page: self.currentPage, per_page: 20 };
                if (self.filters.status) params.status = self.filters.status;
                if (self.filters.search) params.search = self.filters.search;

                NozuleAPI.get('/admin/promo-codes', params).then(function (response) {
                    self.promoCodes = response.data.items || response.data || [];
                    if (response.data.total !== undefined) {
                        self.totalPages = response.data.pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Promo codes load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_promo_codes'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- CRUD ----

            openModal: function () {
                this.editingId = null;
                this.form = this.defaultForm();
                this.showModal = true;
            },

            editPromo: function (promo) {
                this.editingId = promo.id;
                this.form = {
                    code: promo.code || '',
                    name: promo.name || '',
                    name_ar: promo.name_ar || '',
                    description: promo.description || '',
                    description_ar: promo.description_ar || '',
                    discount_type: promo.discount_type || 'percentage',
                    discount_value: promo.discount_value || '',
                    currency_code: promo.currency_code || 'SYP',
                    min_nights: promo.min_nights || '',
                    min_amount: promo.min_amount || '',
                    max_discount: promo.max_discount || '',
                    max_uses: promo.max_uses || '',
                    per_guest_limit: promo.per_guest_limit || '',
                    valid_from: promo.valid_from || '',
                    valid_to: promo.valid_to || '',
                    is_active: promo.is_active !== undefined ? promo.is_active : true
                };
                this.showModal = true;
            },

            savePromo: function () {
                var self = this;
                var data = {
                    code: self.form.code,
                    name: self.form.name,
                    discount_type: self.form.discount_type,
                    discount_value: parseFloat(self.form.discount_value) || 0,
                    is_active: self.form.is_active ? true : false
                };

                // Optional fields
                if (self.form.name_ar) data.name_ar = self.form.name_ar;
                if (self.form.description) data.description = self.form.description;
                if (self.form.description_ar) data.description_ar = self.form.description_ar;
                if (self.form.currency_code) data.currency_code = self.form.currency_code;
                if (self.form.min_nights) data.min_nights = parseInt(self.form.min_nights, 10);
                if (self.form.min_amount) data.min_amount = parseFloat(self.form.min_amount);
                if (self.form.max_discount) data.max_discount = parseFloat(self.form.max_discount);
                if (self.form.max_uses) data.max_uses = parseInt(self.form.max_uses, 10);
                if (self.form.per_guest_limit) data.per_guest_limit = parseInt(self.form.per_guest_limit, 10);
                if (self.form.valid_from) data.valid_from = self.form.valid_from;
                if (self.form.valid_to) data.valid_to = self.form.valid_to;

                if (!data.code || !data.name || !data.discount_value) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingId) {
                    promise = NozuleAPI.put('/admin/promo-codes/' + self.editingId, data);
                } else {
                    promise = NozuleAPI.post('/admin/promo-codes', data);
                }

                promise.then(function () {
                    self.showModal = false;
                    self.loadPromoCodes();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingId ? 'promo_updated' : 'promo_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_promo'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deletePromo: function (id) {
                if (!confirm(NozuleI18n.t('confirm_delete_promo'))) return;
                var self = this;

                NozuleAPI.delete('/admin/promo-codes/' + id).then(function () {
                    self.loadPromoCodes();
                    NozuleUtils.toast(NozuleI18n.t('promo_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_promo'), 'error');
                });
            },

            // ---- Pagination ----

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadPromoCodes();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadPromoCodes();
                }
            },

            // ---- Helpers ----

            formatPrice: function (amount) {
                return NozuleUtils.formatPrice(amount);
            }
        };
    });
});
