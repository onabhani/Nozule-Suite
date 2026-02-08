/**
 * Venezia Hotel Manager - Room Cards Component
 *
 * Displays room types in a card layout.
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('roomCards', function () {
        return {
            rooms: [],
            loading: true,
            error: null,

            init: function () {
                this.loadRooms();
            },

            loadRooms: function () {
                var self = this;
                self.loading = true;

                VeneziaAPI.get('/room-types').then(function (response) {
                    self.rooms = response.data || [];
                }).catch(function (err) {
                    self.error = err.message;
                }).finally(function () {
                    self.loading = false;
                });
            },

            getAmenities: function (room) {
                if (!room.amenities) return [];
                if (typeof room.amenities === 'string') {
                    try { return JSON.parse(room.amenities); }
                    catch (e) { return []; }
                }
                return room.amenities;
            },

            getImages: function (room) {
                if (!room.images) return [];
                if (typeof room.images === 'string') {
                    try { return JSON.parse(room.images); }
                    catch (e) { return []; }
                }
                return room.images;
            },

            getLocalizedName: function (room) {
                if (VeneziaI18n.isRTL() && room.name_ar) {
                    return room.name_ar;
                }
                return room.name;
            },

            getLocalizedDescription: function (room) {
                if (VeneziaI18n.isRTL() && room.description_ar) {
                    return room.description_ar;
                }
                return room.description || '';
            },

            formatPrice: function (amount) {
                return VeneziaUtils.formatPrice(amount);
            }
        };
    });
});
