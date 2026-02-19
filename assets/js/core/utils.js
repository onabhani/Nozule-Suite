/**
 * Nozule - Utility Functions
 */
var NozuleUtils = (function () {
    'use strict';

    return {
        /**
         * Format a price with currency.
         */
        formatPrice: function (amount, currency) {
            var config = window.NozuleConfig || {};
            currency = currency || config.currency || 'USD';

            try {
                return new Intl.NumberFormat(config.locale || 'en-US', {
                    style: 'currency',
                    currency: currency,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(amount);
            } catch (e) {
                return currency + ' ' + parseFloat(amount).toFixed(2);
            }
        },

        /**
         * Format a date string.
         */
        formatDate: function (dateStr, format) {
            if (!dateStr) return '';
            var date = new Date(dateStr + 'T00:00:00');
            var config = window.NozuleConfig || {};

            try {
                return date.toLocaleDateString(config.locale || 'en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            } catch (e) {
                return dateStr;
            }
        },

        /**
         * Calculate number of nights between two dates.
         */
        calculateNights: function (checkIn, checkOut) {
            if (!checkIn || !checkOut) return 0;
            var start = new Date(checkIn);
            var end = new Date(checkOut);
            var diff = end.getTime() - start.getTime();
            return Math.max(0, Math.ceil(diff / 86400000));
        },

        /**
         * Get today's date as YYYY-MM-DD.
         */
        today: function () {
            return new Date().toISOString().split('T')[0];
        },

        /**
         * Get a date offset from today.
         */
        dateOffset: function (days) {
            var date = new Date();
            date.setDate(date.getDate() + days);
            return date.toISOString().split('T')[0];
        },

        /**
         * Debounce a function.
         */
        debounce: function (func, wait) {
            var timeout;
            return function () {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function () {
                    func.apply(context, args);
                }, wait);
            };
        },

        /**
         * Generate a simple unique ID.
         */
        uniqueId: function (prefix) {
            return (prefix || 'nzl_') + Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
        },

        /**
         * Sanitize HTML to prevent XSS.
         */
        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },

        /**
         * Parse query string parameters.
         */
        getUrlParams: function () {
            var params = {};
            var search = window.location.search.substring(1);
            if (!search) return params;

            search.split('&').forEach(function (pair) {
                var parts = pair.split('=');
                params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
            });

            return params;
        },

        /**
         * Show a toast notification.
         */
        toast: function (message, type) {
            type = type || 'info';
            var store = window.Alpine && Alpine.store('notifications');
            if (store) {
                store.add({
                    id: this.uniqueId('toast_'),
                    message: message,
                    type: type,
                    persistent: false
                });
            }
        }
    };
})();

window.NozuleUtils = NozuleUtils;
