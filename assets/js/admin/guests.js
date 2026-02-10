/**
 * Venezia Hotel Manager - Admin Guests
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('vhmGuests', function () {
        return {
            loading: true,
            search: '',
            guests: [],
            currentPage: 1,
            totalPages: 1,

            init: function () {
                this.loadGuests();
            },

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
                }).finally(function () {
                    self.loading = false;
                });
            },

            viewGuest: function (id) {
                var config = window.VeneziaAdmin || window.VeneziaConfig || {};
                if (config.adminUrl) {
                    window.location.href = config.adminUrl + 'admin.php?page=vhm-guests&guest_id=' + id;
                }
            },

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

            formatDate: function (date) {
                return VeneziaUtils.formatDate(date);
            }
        };
    });
});
