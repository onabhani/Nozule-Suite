/**
 * Venezia Hotel Manager - Admin Guests (CRM)
 *
 * Full guest management: list, create, edit, detail with booking history.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmGuests', function () {
        return {
            loading: true,
            saving: false,
            search: '',
            guests: [],
            currentPage: 1,
            totalPages: 1,

            // Guest form modal
            showGuestModal: false,
            editingGuestId: null,
            guestForm: {},

            // Guest detail panel
            showDetailPanel: false,
            loadingDetail: false,
            selectedGuest: null,
            guestBookings: [],

            init: function () {
                this.loadGuests();
            },

            // ---- Default form ----

            defaultGuestForm: function () {
                return {
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    phone_alt: '',
                    nationality: '',
                    id_type: '',
                    id_number: '',
                    date_of_birth: '',
                    gender: '',
                    address: '',
                    city: '',
                    country: '',
                    company: '',
                    notes: ''
                };
            },

            // ---- Data loading ----

            loadGuests: function () {
                var self = this;
                self.loading = true;

                var params = {
                    page: self.currentPage,
                    per_page: 20
                };
                if (self.search) {
                    params.search = self.search;
                }

                VeneziaAPI.get('/admin/guests', params).then(function (response) {
                    self.guests = response.data.items || response.data || [];
                    if (response.data.pagination) {
                        self.currentPage = response.data.pagination.page || 1;
                        self.totalPages = response.data.pagination.total_pages || 1;
                    }
                }).catch(function (err) {
                    console.error('Guests load error:', err);
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_load_guests'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Guest CRUD ----

            openGuestModal: function () {
                this.editingGuestId = null;
                this.guestForm = this.defaultGuestForm();
                this.showGuestModal = true;
            },

            editGuest: function (guest) {
                this.editingGuestId = guest.id;
                this.guestForm = {
                    first_name: guest.first_name || '',
                    last_name: guest.last_name || '',
                    email: guest.email || '',
                    phone: guest.phone || '',
                    phone_alt: guest.phone_alt || '',
                    nationality: guest.nationality || '',
                    id_type: guest.id_type || '',
                    id_number: guest.id_number || '',
                    date_of_birth: guest.date_of_birth || '',
                    gender: guest.gender || '',
                    address: guest.address || '',
                    city: guest.city || '',
                    country: guest.country || '',
                    company: guest.company || '',
                    notes: guest.notes || ''
                };
                this.showGuestModal = true;
            },

            editFromDetail: function () {
                if (this.selectedGuest) {
                    this.showDetailPanel = false;
                    this.editGuest(this.selectedGuest);
                }
            },

            saveGuest: function () {
                var self = this;
                var data = {};

                // Only send non-empty fields
                var fields = Object.keys(self.guestForm);
                for (var i = 0; i < fields.length; i++) {
                    var key = fields[i];
                    var val = self.guestForm[key];
                    if (val !== '' && val != null) {
                        data[key] = val;
                    }
                }

                if (!data.first_name || !data.last_name || !data.email || !data.phone) {
                    VeneziaUtils.toast(VeneziaI18n.t('fill_required_fields'), 'error');
                    return;
                }

                self.saving = true;

                var promise;
                if (self.editingGuestId) {
                    promise = VeneziaAPI.put('/guests/' + self.editingGuestId, data);
                } else {
                    promise = VeneziaAPI.post('/guests', data);
                }

                promise.then(function () {
                    self.showGuestModal = false;
                    self.loadGuests();
                    VeneziaUtils.toast(
                        VeneziaI18n.t(self.editingGuestId ? 'guest_updated' : 'guest_created'),
                        'success'
                    );
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_save_guest'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Guest Detail ----

            viewGuest: function (id) {
                var self = this;
                self.loadingDetail = true;
                self.showDetailPanel = true;
                self.selectedGuest = null;
                self.guestBookings = [];

                VeneziaAPI.get('/guests/' + id + '/history').then(function (response) {
                    self.selectedGuest = response.guest || null;
                    self.guestBookings = response.bookings || [];
                }).catch(function (err) {
                    VeneziaUtils.toast(err.message || VeneziaI18n.t('failed_load_guest'), 'error');
                    self.showDetailPanel = false;
                }).finally(function () {
                    self.loadingDetail = false;
                });
            },

            closeDetail: function () {
                this.showDetailPanel = false;
                this.selectedGuest = null;
                this.guestBookings = [];
            },

            // ---- Pagination ----

            prevPage: function () {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadGuests();
                }
            },

            nextPage: function () {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadGuests();
                }
            },

            // ---- Helpers ----

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            },

            statusLabel: function (status) {
                return VeneziaI18n.t(status);
            }
        };
    });
});
