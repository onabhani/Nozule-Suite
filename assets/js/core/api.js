/**
 * Venezia Hotel Manager - API Client
 *
 * Provides a simple interface for communicating with the REST API.
 */
const VeneziaAPI = {
    get config() {
        return window.VeneziaAdmin || window.VeneziaConfig || {};
    },

    get baseURL() {
        return this.config.apiBase || '/wp-json/venezia/v1';
    },

    get nonce() {
        return this.config.nonce || '';
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

        // Guard against non-JSON responses (e.g. permission denied returning HTML)
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const error = new Error(
                response.status === 403 ? 'Permission denied. Try deactivating and reactivating the plugin.'
                : response.status === 404 ? 'API endpoint not found. Please flush permalinks (Settings > Permalinks > Save).'
                : 'Server returned an unexpected response (status ' + response.status + '). Check PHP error logs.'
            );
            error.code = 'NON_JSON_RESPONSE';
            error.status = response.status;
            throw error;
        }

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
