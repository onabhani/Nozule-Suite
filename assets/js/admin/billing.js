/**
 * Nozule - Admin Billing
 *
 * Folio management and tax configuration with tabbed interface.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlBilling', function () {
        return {
            loading: true,
            saving: false,
            activeTab: 'folios',

            // Folios
            folios: [],
            folioFilters: {
                status: '',
                search: ''
            },
            folioCurrentPage: 1,
            folioTotalPages: 1,

            // Folio detail
            showFolioPanel: false,
            loadingFolioDetail: false,
            selectedFolio: null,
            folioItems: [],
            itemForm: {},
            savingItem: false,

            // Taxes
            taxes: [],
            showTaxModal: false,
            editingTaxId: null,
            taxForm: {},

            init: function () {
                this.itemForm = this.defaultItemForm();
                this.taxForm = this.defaultTaxForm();
                this.loadFolios();
            },

            // ---- Default form values ----

            defaultItemForm: function () {
                return {
                    category: 'room_charge',
                    description: '',
                    quantity: 1,
                    unit_price: ''
                };
            },

            defaultTaxForm: function () {
                return {
                    name: '',
                    name_ar: '',
                    rate: '',
                    type: 'percentage',
                    applies_to: 'all',
                    sort_order: 0,
                    is_active: true
                };
            },

            // ---- Folio loading ----

            loadFolios: function () {
                var self = this;
                self.loading = true;

                var params = { page: self.folioCurrentPage };
                if (self.folioFilters.status) params.status = self.folioFilters.status;
                if (self.folioFilters.search) params.search = self.folioFilters.search;

                NozuleAPI.get('/admin/folios', params).then(function (response) {
                    var data = response.data || {};
                    self.folios = data.items || data || [];
                    self.folioCurrentPage = data.current_page || data.page || 1;
                    self.folioTotalPages = data.total_pages || data.last_page || 1;
                }).catch(function (err) {
                    console.error('Folios load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_folios'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Tax loading ----

            loadTaxes: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/taxes').then(function (response) {
                    self.taxes = response.data.items || response.data || [];
                }).catch(function (err) {
                    console.error('Taxes load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_taxes'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Folio pagination ----

            folioPrevPage: function () {
                if (this.folioCurrentPage > 1) {
                    this.folioCurrentPage--;
                    this.loadFolios();
                }
            },

            folioNextPage: function () {
                if (this.folioCurrentPage < this.folioTotalPages) {
                    this.folioCurrentPage++;
                    this.loadFolios();
                }
            },

            // ---- Folio detail ----

            viewFolio: function (folioId) {
                var self = this;
                self.loadingFolioDetail = true;
                self.showFolioPanel = true;
                self.selectedFolio = null;
                self.folioItems = [];
                self.itemForm = self.defaultItemForm();

                NozuleAPI.get('/admin/folios/' + folioId).then(function (response) {
                    self.selectedFolio = response.data || null;
                    self.folioItems = response.data.items || [];
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_folio'), 'error');
                    self.showFolioPanel = false;
                }).finally(function () {
                    self.loadingFolioDetail = false;
                });
            },

            closeFolioPanel: function () {
                this.showFolioPanel = false;
                this.selectedFolio = null;
                this.folioItems = [];
            },

            // ---- Folio actions ----

            closeFolio: function (folioId) {
                if (!confirm(NozuleI18n.t('confirm_close_folio'))) return;
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/folios/' + folioId + '/close').then(function () {
                    self.loadFolios();
                    self.closeFolioPanel();
                    NozuleUtils.toast(NozuleI18n.t('folio_closed'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_close_folio'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            voidFolio: function (folioId) {
                if (!confirm(NozuleI18n.t('confirm_void_folio'))) return;
                var self = this;
                self.saving = true;

                NozuleAPI.put('/admin/folios/' + folioId + '/void').then(function () {
                    self.loadFolios();
                    self.closeFolioPanel();
                    NozuleUtils.toast(NozuleI18n.t('folio_voided'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_void_folio'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Folio line items ----

            addLineItem: function () {
                var self = this;
                if (!self.selectedFolio) return;

                var data = {
                    category: self.itemForm.category,
                    description: self.itemForm.description,
                    quantity: parseInt(self.itemForm.quantity, 10) || 1,
                    unit_price: parseFloat(self.itemForm.unit_price) || 0
                };

                if (!data.description || !data.unit_price) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.savingItem = true;

                NozuleAPI.post('/admin/folios/' + self.selectedFolio.id + '/items', data).then(function () {
                    self.itemForm = self.defaultItemForm();
                    self.viewFolio(self.selectedFolio.id);
                    NozuleUtils.toast(NozuleI18n.t('item_added'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_add_item'), 'error');
                }).finally(function () {
                    self.savingItem = false;
                });
            },

            removeLineItem: function (itemId) {
                if (!confirm(NozuleI18n.t('confirm_remove_item'))) return;
                var self = this;

                NozuleAPI.delete('/admin/folios/items/' + itemId).then(function () {
                    if (self.selectedFolio) {
                        self.viewFolio(self.selectedFolio.id);
                    }
                    NozuleUtils.toast(NozuleI18n.t('item_removed'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_remove_item'), 'error');
                });
            },

            // ---- Tax CRUD ----

            openTaxModal: function () {
                this.editingTaxId = null;
                this.taxForm = this.defaultTaxForm();
                this.showTaxModal = true;
            },

            editTax: function (tax) {
                this.editingTaxId = tax.id;
                this.taxForm = {
                    name: tax.name || '',
                    name_ar: tax.name_ar || '',
                    rate: tax.rate || '',
                    type: tax.type || 'percentage',
                    applies_to: tax.applies_to || 'all',
                    sort_order: tax.sort_order || 0,
                    is_active: tax.is_active !== undefined ? tax.is_active : true
                };
                this.showTaxModal = true;
            },

            saveTax: function () {
                var self = this;
                var data = {
                    name: self.taxForm.name,
                    rate: parseFloat(self.taxForm.rate) || 0,
                    type: self.taxForm.type,
                    applies_to: self.taxForm.applies_to,
                    sort_order: parseInt(self.taxForm.sort_order, 10) || 0,
                    is_active: self.taxForm.is_active ? true : false
                };

                if (self.taxForm.name_ar) {
                    data.name_ar = self.taxForm.name_ar;
                }

                if (!data.name || !data.rate) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingTaxId) {
                    promise = NozuleAPI.put('/admin/taxes/' + self.editingTaxId, data);
                } else {
                    promise = NozuleAPI.post('/admin/taxes', data);
                }

                promise.then(function () {
                    self.showTaxModal = false;
                    self.loadTaxes();
                    NozuleUtils.toast(
                        NozuleI18n.t(self.editingTaxId ? 'tax_updated' : 'tax_created'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_tax'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            deleteTax: function (taxId) {
                if (!confirm(NozuleI18n.t('confirm_delete_tax'))) return;
                var self = this;
                NozuleAPI.delete('/admin/taxes/' + taxId).then(function () {
                    self.loadTaxes();
                    NozuleUtils.toast(NozuleI18n.t('tax_deleted'), 'success');
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_tax'), 'error');
                });
            },

            toggleTaxActive: function (tax) {
                var self = this;
                var newState = !tax.is_active;

                NozuleAPI.put('/admin/taxes/' + tax.id, {
                    is_active: newState
                }).then(function () {
                    tax.is_active = newState;
                    NozuleUtils.toast(
                        NozuleI18n.t(newState ? 'tax_activated' : 'tax_deactivated'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_update_tax'), 'error');
                });
            },

            // ---- Helpers ----

            statusLabel: function (key) {
                return NozuleI18n.t(key);
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
