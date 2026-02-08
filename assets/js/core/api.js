/**
 * Venezia Hotel Manager - API Client
 *
 * Provides a simple interface for communicating with the REST API.
 */
const VeneziaAPI = {
    get baseURL() {
        return (window.VeneziaConfig && window.VeneziaConfig.apiBase)
            ? window.VeneziaConfig.apiBase
            : '/wp-json/venezia/v1';
    },

    get nonce() {
        return (window.VeneziaConfig && window.VeneziaConfig.nonce) || '';
    },

    /**
     * Make an API request.
     *
     * @param {string} method - HTTP method
     * @param {string} endpoint - API endpoint path
     * @param {object|null} data - Request data
     * @returns {Promise<object>}
     */
    async request(method, endpoint, data = null) {
        const url = new URL(this.baseURL + endpoint, window.location.origin);

        if (method === 'GET' && data) {
            Object.keys(data).forEach(function (k) {
                if (data[k] != null) {
                    url.searchParams.append(k, data[k]);
                }
            });
        }

        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            }
        };

        if (method !== 'GET' && data) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(url.toString(), config);
        const json = await response.json();

        if (!response.ok) {
            const error = new Error(
                (json.error && json.error.message) ? json.error.message : 'Request failed'
            );
            error.code = json.error ? json.error.code : 'UNKNOWN_ERROR';
            error.status = response.status;
            throw error;
        }

        return json;
    },

    /**
     * GET request.
     */
    get: function (endpoint, params) {
        return VeneziaAPI.request('GET', endpoint, params);
    },

    /**
     * POST request.
     */
    post: function (endpoint, data) {
        return VeneziaAPI.request('POST', endpoint, data);
    },

    /**
     * PUT request.
     */
    put: function (endpoint, data) {
        return VeneziaAPI.request('PUT', endpoint, data);
    },

    /**
     * DELETE request.
     */
    delete: function (endpoint) {
        return VeneziaAPI.request('DELETE', endpoint);
    }
};

// Export for use in other scripts
window.VeneziaAPI = VeneziaAPI;
