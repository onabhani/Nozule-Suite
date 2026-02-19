/**
 * Nozule - Alpine.js Global Store
 *
 * Manages booking selection state across components.
 */
document.addEventListener('alpine:init', function () {

    Alpine.store('booking', {
        selection: null,
        confirmation: null,

        setSelection: function (data) {
            this.selection = data;
            try {
                sessionStorage.setItem('nzl_selection', JSON.stringify(data));
            } catch (e) {
                // Ignore storage errors
            }
        },

        setConfirmation: function (booking) {
            this.confirmation = booking;
        },

        clear: function () {
            this.selection = null;
            this.confirmation = null;
            try {
                sessionStorage.removeItem('nzl_selection');
            } catch (e) {
                // Ignore storage errors
            }
        },

        init: function () {
            try {
                var saved = sessionStorage.getItem('nzl_selection');
                if (saved) {
                    this.selection = JSON.parse(saved);
                }
            } catch (e) {
                sessionStorage.removeItem('nzl_selection');
            }
        }
    });

    Alpine.store('notifications', {
        items: [],
        unreadCount: 0,

        add: function (notification) {
            this.items.unshift(notification);
            this.unreadCount++;

            // Auto-remove after 10 seconds for non-persistent notifications
            if (!notification.persistent) {
                var self = this;
                setTimeout(function () {
                    self.remove(notification.id);
                }, 10000);
            }
        },

        remove: function (id) {
            this.items = this.items.filter(function (n) {
                return n.id !== id;
            });
        },

        markAllRead: function () {
            this.unreadCount = 0;
        }
    });
});
