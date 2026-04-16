/**
 * Nozule - My Account Component
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('myAccount', function () {
        return {
            tab: 'upcoming',
            tabs: [
                { key: 'upcoming', label: 'Upcoming' },
                { key: 'past',     label: 'History' },
                { key: 'profile',  label: 'Profile' }
            ],
            loading: false,
            error: '',
            bookings: [],
            profile: {},
            profileSaved: false,
            savingProfile: false,

            get upcoming() {
                const today = new Date().toISOString().slice(0, 10);
                return this.bookings.filter(function (b) {
                    return b.check_out >= today && b.status !== 'cancelled';
                });
            },

            get past() {
                const today = new Date().toISOString().slice(0, 10);
                return this.bookings.filter(function (b) {
                    return b.check_out < today || b.status === 'cancelled';
                });
            },

            load: async function () {
                this.loading = true;
                this.error = '';
                try {
                    const [bookingsRes, profileRes] = await Promise.all([
                        fetch(NozuleConfig.apiBase + '/me/bookings', {
                            headers: { 'X-WP-Nonce': NozuleConfig.nonce },
                            credentials: 'same-origin'
                        }),
                        fetch(NozuleConfig.apiBase + '/me/profile', {
                            headers: { 'X-WP-Nonce': NozuleConfig.nonce },
                            credentials: 'same-origin'
                        })
                    ]);
                    if (bookingsRes.ok) {
                        const data = await bookingsRes.json();
                        this.bookings = data.bookings || [];
                    }
                    if (profileRes.ok) {
                        this.profile = await profileRes.json();
                    }
                } catch (e) {
                    this.error = e.message || 'Unable to load account data.';
                } finally {
                    this.loading = false;
                }
            },

            saveProfile: async function () {
                this.savingProfile = true;
                this.profileSaved = false;
                try {
                    const res = await fetch(NozuleConfig.apiBase + '/me/profile', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': NozuleConfig.nonce
                        },
                        body: JSON.stringify(this.profile),
                        credentials: 'same-origin'
                    });
                    if (!res.ok) {
                        const data = await res.json();
                        this.error = (data && data.message) || 'Save failed.';
                        return;
                    }
                    this.profile = await res.json();
                    this.profileSaved = true;
                    setTimeout(() => { this.profileSaved = false; }, 3000);
                } catch (e) {
                    this.error = e.message || 'Network error.';
                } finally {
                    this.savingProfile = false;
                }
            },

            cancel: async function (b) {
                if (!confirm('Cancel booking ' + b.booking_number + '?')) return;
                try {
                    const res = await fetch(NozuleConfig.apiBase + '/me/bookings/' + b.id + '/cancel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': NozuleConfig.nonce
                        },
                        body: JSON.stringify({ reason: 'Cancelled by guest via portal' }),
                        credentials: 'same-origin'
                    });
                    if (!res.ok) {
                        const data = await res.json();
                        alert((data && data.message) || 'Cancellation failed.');
                        return;
                    }
                    await this.load();
                } catch (e) {
                    alert(e.message || 'Network error.');
                }
            },

            canCancel: function (b) {
                return !['cancelled', 'checked_out', 'no_show', 'checked_in'].includes(b.status);
            },

            badgeClass: function (status) {
                return {
                    confirmed:   'nzl-badge-success',
                    pending:     'nzl-badge-warning',
                    cancelled:   'nzl-badge-danger',
                    checked_in:  'nzl-badge-info',
                    checked_out: 'nzl-badge-neutral',
                    no_show:     'nzl-badge-danger'
                }[status] || 'nzl-badge-neutral';
            },

            formatPrice: function (amount, currency) {
                const c = currency || (NozuleConfig && NozuleConfig.currency) || 'USD';
                try {
                    return new Intl.NumberFormat(NozuleConfig.locale || 'en', {
                        style: 'currency',
                        currency: c
                    }).format(amount);
                } catch (e) {
                    return c + ' ' + Number(amount).toFixed(2);
                }
            },

            logout: function () {
                window.location.href = (window.NozuleConfig && window.NozuleConfig.logoutUrl) || '/wp-login.php?action=logout';
            }
        };
    });
});
