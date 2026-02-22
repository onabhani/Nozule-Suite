/**
 * Nozule - Admin POS (Point of Sale)
 *
 * Manages outlets, menu items, orders, cart, and folio posting.
 * Uses var + function() style only (no arrow functions, no const/let).
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlPOS', function () {
        return {
            // ── Tab state ─────────────────────────────────────────────
            activeTab: 'orders',

            // ── Stats ─────────────────────────────────────────────────
            stats: {
                total_orders: 0,
                total_revenue: 0
            },

            // ── Outlets ───────────────────────────────────────────────
            outlets: [],
            loadingOutlets: false,
            showOutletModal: false,
            outletForm: {},

            // ── Items ─────────────────────────────────────────────────
            items: [],
            loadingItems: false,
            itemFilterOutlet: '',
            showItemModal: false,
            itemForm: {},

            // ── Orders ────────────────────────────────────────────────
            orders: [],
            loadingOrders: false,
            ordersPage: 1,
            ordersTotalPages: 1,
            orderFilters: {
                outlet_id: '',
                status: '',
                date_from: ''
            },

            // ── Order Detail ──────────────────────────────────────────
            showOrderDetail: false,
            loadingOrderDetail: false,
            orderDetail: null,

            // ── New Order ─────────────────────────────────────────────
            showNewOrderModal: false,
            newOrderForm: {},
            newOrderOutletItems: [],
            cart: [],
            bookingLookupResult: null,

            // ── General ───────────────────────────────────────────────
            saving: false,

            // ==============================================================
            // Init
            // ==============================================================

            init: function () {
                this.outletForm = this.defaultOutletForm();
                this.itemForm = this.defaultItemForm();
                this.newOrderForm = this.defaultNewOrderForm();
                this.loadOutlets();
                this.loadStats();
                this.loadOrders();
            },

            // ==============================================================
            // Default form values
            // ==============================================================

            defaultOutletForm: function () {
                return {
                    id: null,
                    name: '',
                    name_ar: '',
                    type: 'restaurant',
                    description: '',
                    status: 'active',
                    sort_order: 0
                };
            },

            defaultItemForm: function () {
                return {
                    id: null,
                    outlet_id: '',
                    name: '',
                    name_ar: '',
                    category: '',
                    price: 0,
                    status: 'active',
                    sort_order: 0
                };
            },

            defaultNewOrderForm: function () {
                return {
                    outlet_id: '',
                    room_number: '',
                    booking_id: '',
                    guest_id: '',
                    notes: ''
                };
            },

            // ==============================================================
            // Tab switching
            // ==============================================================

            switchTab: function (tab) {
                this.activeTab = tab;

                if (tab === 'orders' && this.orders.length === 0) {
                    this.loadOrders();
                }
                if (tab === 'outlets' && this.outlets.length === 0) {
                    this.loadOutlets();
                }
                if (tab === 'items' && this.items.length === 0) {
                    this.loadItems();
                }
            },

            // ==============================================================
            // Computed helpers
            // ==============================================================

            get activeOutlets() {
                return this.outlets.filter(function (o) {
                    return o.status === 'active';
                });
            },

            // ==============================================================
            // Data loading
            // ==============================================================

            loadStats: function () {
                var self = this;
                NozuleAPI.get('/admin/pos/stats').then(function (response) {
                    self.stats = response.data || { total_orders: 0, total_revenue: 0 };
                }).catch(function (err) {
                    console.error('POS stats error:', err);
                });
            },

            loadOutlets: function () {
                var self = this;
                self.loadingOutlets = true;

                NozuleAPI.get('/admin/pos/outlets').then(function (response) {
                    self.outlets = response.data || [];
                }).catch(function (err) {
                    console.error('POS outlets error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                }).finally(function () {
                    self.loadingOutlets = false;
                });
            },

            loadItems: function () {
                var self = this;
                self.loadingItems = true;

                var params = {};
                if (self.itemFilterOutlet) {
                    params.outlet_id = self.itemFilterOutlet;
                }

                NozuleAPI.get('/admin/pos/items', params).then(function (response) {
                    self.items = response.data || [];
                }).catch(function (err) {
                    console.error('POS items error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                }).finally(function () {
                    self.loadingItems = false;
                });
            },

            loadOrders: function () {
                var self = this;
                self.loadingOrders = true;

                var params = {
                    page: self.ordersPage,
                    per_page: 20
                };

                if (self.orderFilters.outlet_id) params.outlet_id = self.orderFilters.outlet_id;
                if (self.orderFilters.status) params.status = self.orderFilters.status;
                if (self.orderFilters.date_from) {
                    params.date_from = self.orderFilters.date_from;
                    params.date_to = self.orderFilters.date_from;
                }

                NozuleAPI.get('/admin/pos/orders', params).then(function (response) {
                    self.orders = response.data || [];
                    if (response.pagination) {
                        self.ordersPage = response.pagination.page || 1;
                        self.ordersTotalPages = response.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    console.error('POS orders error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                }).finally(function () {
                    self.loadingOrders = false;
                });
            },

            // ==============================================================
            // Outlet CRUD
            // ==============================================================

            openOutletModal: function () {
                this.outletForm = this.defaultOutletForm();
                this.showOutletModal = true;
            },

            editOutlet: function (outlet) {
                this.outletForm = {
                    id: outlet.id,
                    name: outlet.name || '',
                    name_ar: outlet.name_ar || '',
                    type: outlet.type || 'restaurant',
                    description: outlet.description || '',
                    status: outlet.status || 'active',
                    sort_order: outlet.sort_order || 0
                };
                this.showOutletModal = true;
            },

            saveOutlet: function () {
                var self = this;
                self.saving = true;

                NozuleAPI.post('/admin/pos/outlets', self.outletForm).then(function (response) {
                    self.showOutletModal = false;
                    self.loadOutlets();
                    self.loadStats();
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('saved'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            confirmDeleteOutlet: function (outletId) {
                if (!confirm(NozuleI18n.t('confirm_delete') || 'Are you sure you want to delete this outlet?')) return;
                var self = this;

                NozuleAPI.delete('/admin/pos/outlets/' + outletId).then(function (response) {
                    self.loadOutlets();
                    self.loadStats();
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('deleted'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
                });
            },

            // ==============================================================
            // Item CRUD
            // ==============================================================

            openItemModal: function () {
                this.itemForm = this.defaultItemForm();
                // Pre-select outlet if filtered
                if (this.itemFilterOutlet) {
                    this.itemForm.outlet_id = parseInt(this.itemFilterOutlet, 10);
                }
                this.showItemModal = true;
            },

            editItem: function (item) {
                this.itemForm = {
                    id: item.id,
                    outlet_id: item.outlet_id || '',
                    name: item.name || '',
                    name_ar: item.name_ar || '',
                    category: item.category || '',
                    price: item.price || 0,
                    status: item.status || 'active',
                    sort_order: item.sort_order || 0
                };
                this.showItemModal = true;
            },

            saveItem: function () {
                var self = this;
                self.saving = true;

                NozuleAPI.post('/admin/pos/items', self.itemForm).then(function (response) {
                    self.showItemModal = false;
                    self.loadItems();
                    self.loadOutlets(); // refresh item counts
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('saved'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            confirmDeleteItem: function (itemId) {
                if (!confirm(NozuleI18n.t('confirm_delete') || 'Are you sure you want to delete this item?')) return;
                var self = this;

                NozuleAPI.delete('/admin/pos/items/' + itemId).then(function (response) {
                    self.loadItems();
                    self.loadOutlets(); // refresh item counts
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('deleted'),
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete'), 'error');
                });
            },

            // ==============================================================
            // New Order
            // ==============================================================

            openNewOrder: function () {
                this.newOrderForm = this.defaultNewOrderForm();
                this.newOrderOutletItems = [];
                this.cart = [];
                this.bookingLookupResult = null;
                this.showNewOrderModal = true;
            },

            onNewOrderOutletChange: function () {
                var self = this;
                self.cart = [];
                self.newOrderOutletItems = [];

                if (!self.newOrderForm.outlet_id) return;

                NozuleAPI.get('/admin/pos/items', {
                    outlet_id: self.newOrderForm.outlet_id
                }).then(function (response) {
                    var allItems = response.data || [];
                    self.newOrderOutletItems = allItems.filter(function (item) {
                        return item.status === 'active';
                    });
                }).catch(function (err) {
                    console.error('POS outlet items error:', err);
                });
            },

            lookupBooking: function () {
                var self = this;
                self.bookingLookupResult = null;

                if (!self.newOrderForm.room_number) return;

                // Try to find a checked-in booking for this room number.
                NozuleAPI.get('/admin/bookings', {
                    room_number: self.newOrderForm.room_number,
                    status: 'checked_in',
                    per_page: 1
                }).then(function (response) {
                    var bookings = response.data.items || response.data || [];
                    if (bookings.length > 0) {
                        var booking = bookings[0];
                        self.newOrderForm.booking_id = booking.id;
                        self.newOrderForm.guest_id = booking.guest_id || '';
                        self.bookingLookupResult = booking;
                    }
                }).catch(function () {
                    // Lookup failed silently - user can enter manually.
                });
            },

            // ── Cart ─────────────────────────────────────────────────

            addToCart: function (menuItem) {
                // Check if already in cart.
                var existing = -1;
                for (var i = 0; i < this.cart.length; i++) {
                    if (this.cart[i].item_id === menuItem.id) {
                        existing = i;
                        break;
                    }
                }

                if (existing >= 0) {
                    this.cart[existing].quantity++;
                } else {
                    this.cart.push({
                        item_id: menuItem.id,
                        name: menuItem.name,
                        price: parseFloat(menuItem.price) || 0,
                        quantity: 1
                    });
                }
            },

            removeFromCart: function (index) {
                this.cart.splice(index, 1);
            },

            updateQuantity: function (index, qty) {
                if (qty < 1) qty = 1;
                this.cart[index].quantity = qty;
            },

            isInCart: function (itemId) {
                for (var i = 0; i < this.cart.length; i++) {
                    if (this.cart[i].item_id === itemId) {
                        return true;
                    }
                }
                return false;
            },

            cartTotal: function () {
                var total = 0;
                for (var i = 0; i < this.cart.length; i++) {
                    total += (this.cart[i].price * this.cart[i].quantity);
                }
                return Math.round(total * 100) / 100;
            },

            submitNewOrder: function () {
                var self = this;

                if (!self.newOrderForm.outlet_id) {
                    NozuleUtils.toast(NozuleI18n.t('select_outlet') || 'Please select an outlet.', 'error');
                    return;
                }

                if (self.cart.length === 0) {
                    NozuleUtils.toast(NozuleI18n.t('add_items') || 'Please add at least one item.', 'error');
                    return;
                }

                var orderItems = self.cart.map(function (cartItem) {
                    return {
                        item_id: cartItem.item_id,
                        quantity: cartItem.quantity
                    };
                });

                var payload = {
                    outlet_id: self.newOrderForm.outlet_id,
                    items: orderItems
                };

                if (self.newOrderForm.room_number) payload.room_number = self.newOrderForm.room_number;
                if (self.newOrderForm.booking_id) payload.booking_id = parseInt(self.newOrderForm.booking_id, 10);
                if (self.newOrderForm.guest_id) payload.guest_id = parseInt(self.newOrderForm.guest_id, 10);
                if (self.newOrderForm.notes) payload.notes = self.newOrderForm.notes;

                self.saving = true;

                NozuleAPI.post('/admin/pos/orders', payload).then(function (response) {
                    self.showNewOrderModal = false;
                    self.loadOrders();
                    self.loadStats();
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('order_created') || 'Order created successfully.',
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_create_order') || 'Failed to create order.', 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ==============================================================
            // Order actions
            // ==============================================================

            viewOrder: function (orderId) {
                var self = this;
                self.orderDetail = null;
                self.loadingOrderDetail = true;
                self.showOrderDetail = true;

                NozuleAPI.get('/admin/pos/orders/' + orderId).then(function (response) {
                    self.orderDetail = response.data || null;
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_data'), 'error');
                    self.showOrderDetail = false;
                }).finally(function () {
                    self.loadingOrderDetail = false;
                });
            },

            confirmPostToFolio: function (orderId) {
                if (!confirm(NozuleI18n.t('confirm_post_to_folio') || 'Post this order to the room folio?')) return;
                var self = this;

                NozuleAPI.post('/admin/pos/orders/' + orderId + '/post-to-folio').then(function (response) {
                    self.loadOrders();
                    self.loadStats();
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('posted_to_folio') || 'Order posted to folio.',
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_post_to_folio') || 'Failed to post to folio.', 'error');
                });
            },

            confirmCancelOrder: function (orderId) {
                if (!confirm(NozuleI18n.t('confirm_cancel_order') || 'Cancel this order? This cannot be undone.')) return;
                var self = this;

                NozuleAPI.put('/admin/pos/orders/' + orderId + '/status', {
                    status: 'cancelled'
                }).then(function (response) {
                    self.loadOrders();
                    self.loadStats();
                    NozuleUtils.toast(
                        response.message || NozuleI18n.t('order_cancelled') || 'Order cancelled.',
                        'success'
                    );
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_cancel_order') || 'Failed to cancel order.', 'error');
                });
            },

            // ==============================================================
            // Helpers
            // ==============================================================

            getOutletName: function (outletId) {
                for (var i = 0; i < this.outlets.length; i++) {
                    if (this.outlets[i].id == outletId) {
                        return this.outlets[i].name;
                    }
                }
                return '—';
            },

            outletTypeLabel: function (type) {
                var labels = {
                    restaurant: NozuleI18n.t('restaurant') || 'Restaurant',
                    minibar: NozuleI18n.t('minibar') || 'Minibar',
                    spa: NozuleI18n.t('spa') || 'Spa',
                    laundry: NozuleI18n.t('laundry') || 'Laundry',
                    other: NozuleI18n.t('other') || 'Other'
                };
                return labels[type] || type;
            },

            orderStatusLabel: function (status) {
                var labels = {
                    open: NozuleI18n.t('open') || 'Open',
                    posted: NozuleI18n.t('posted') || 'Posted',
                    cancelled: NozuleI18n.t('cancelled') || 'Cancelled'
                };
                return labels[status] || status;
            },

            formatCurrency: function (amount) {
                return NozuleUtils.formatCurrency ? NozuleUtils.formatCurrency(amount) : parseFloat(amount || 0).toFixed(2);
            },

            formatDate: function (date) {
                return NozuleUtils.formatDate ? NozuleUtils.formatDate(date) : (date || '');
            }
        };
    });
});
