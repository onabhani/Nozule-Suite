/**
 * Nozule - Admin Property Management
 *
 * Manage hotel property details: address, description, photos, facilities,
 * star rating, and policies. Supports multi-property mode (NZL-019).
 */
document.addEventListener('alpine:init', function () {

    Alpine.data('nzlProperty', function () {
        return {
            loading: true,
            saving: false,
            editing: false,
            property: null,

            // Multi-property state (NZL-019)
            multiProperty: false,
            properties: [],
            view: 'list', // 'list' or 'detail'

            // Edit form state
            form: {},
            newFacility: '',

            init: function () {
                this.checkMultiProperty();
            },

            // ---- Multi-property detection ----

            checkMultiProperty: function () {
                var self = this;
                NozuleAPI.get('/admin/settings?group=features').then(function (response) {
                    var data = response.data || response;
                    if (data.features && (data.features.multi_property === '1' || data.features.multi_property === true)) {
                        self.multiProperty = true;
                    }
                }).catch(function () {
                    // Default to single-property mode on error.
                }).finally(function () {
                    if (self.multiProperty) {
                        self.loadProperties();
                    } else {
                        self.loadProperty();
                    }
                });
            },

            // ---- Default form ----

            defaultForm: function () {
                return {
                    id: null,
                    name: '',
                    name_ar: '',
                    slug: '',
                    description: '',
                    description_ar: '',
                    property_type: 'hotel',
                    star_rating: '',
                    address_line_1: '',
                    address_line_2: '',
                    city: '',
                    state_province: '',
                    country: '',
                    postal_code: '',
                    latitude: '',
                    longitude: '',
                    phone: '',
                    phone_alt: '',
                    email: '',
                    website: '',
                    check_in_time: '14:00',
                    check_out_time: '12:00',
                    timezone: 'Asia/Damascus',
                    logo_url: '',
                    cover_image_url: '',
                    photos: [],
                    facilities: [],
                    policies: {
                        cancellation: '',
                        children: '',
                        pets: '',
                        smoking: '',
                        payment: '',
                        extra_bed: ''
                    },
                    social_links: {
                        facebook: '',
                        instagram: '',
                        twitter: '',
                        tripadvisor: '',
                        google_maps: ''
                    },
                    tax_id: '',
                    license_number: '',
                    total_rooms: '',
                    total_floors: '',
                    year_built: '',
                    year_renovated: '',
                    currency: 'USD',
                    status: 'active'
                };
            },

            // ---- Data loading ----

            loadProperty: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/property').then(function (response) {
                    var items = response.data || [];
                    if (items.length > 0) {
                        self.property = items[0];
                    } else {
                        self.property = null;
                    }
                }).catch(function (err) {
                    console.error('Property load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_property'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            loadProperties: function () {
                var self = this;
                self.loading = true;

                NozuleAPI.get('/admin/property').then(function (response) {
                    self.properties = response.data || [];
                }).catch(function (err) {
                    console.error('Properties load error:', err);
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_load_property'), 'error');
                }).finally(function () {
                    self.loading = false;
                });
            },

            // ---- Multi-property navigation ----

            viewProperty: function (p) {
                this.property = p;
                this.view = 'detail';
            },

            backToList: function () {
                this.property = null;
                this.view = 'list';
                this.loadProperties();
            },

            // ---- Edit mode ----

            createNew: function () {
                this.form = this.defaultForm();
                this.editing = true;
            },

            startEditing: function () {
                var p = this.property;
                this.form = {
                    id: p.id,
                    name: p.name || '',
                    name_ar: p.name_ar || '',
                    slug: p.slug || '',
                    description: p.description || '',
                    description_ar: p.description_ar || '',
                    property_type: p.property_type || 'hotel',
                    star_rating: p.star_rating || '',
                    address_line_1: p.address_line_1 || '',
                    address_line_2: p.address_line_2 || '',
                    city: p.city || '',
                    state_province: p.state_province || '',
                    country: p.country || '',
                    postal_code: p.postal_code || '',
                    latitude: p.latitude || '',
                    longitude: p.longitude || '',
                    phone: p.phone || '',
                    phone_alt: p.phone_alt || '',
                    email: p.email || '',
                    website: p.website || '',
                    check_in_time: p.check_in_time || '14:00',
                    check_out_time: p.check_out_time || '12:00',
                    timezone: p.timezone || 'Asia/Damascus',
                    logo_url: p.logo_url || '',
                    cover_image_url: p.cover_image_url || '',
                    photos: Array.isArray(p.photos) ? JSON.parse(JSON.stringify(p.photos)) : [],
                    facilities: Array.isArray(p.facilities) ? JSON.parse(JSON.stringify(p.facilities)) : [],
                    policies: Object.assign({
                        cancellation: '',
                        children: '',
                        pets: '',
                        smoking: '',
                        payment: '',
                        extra_bed: ''
                    }, p.policies || {}),
                    social_links: Object.assign({
                        facebook: '',
                        instagram: '',
                        twitter: '',
                        tripadvisor: '',
                        google_maps: ''
                    }, p.social_links || {}),
                    tax_id: p.tax_id || '',
                    license_number: p.license_number || '',
                    total_rooms: p.total_rooms || '',
                    total_floors: p.total_floors || '',
                    year_built: p.year_built || '',
                    year_renovated: p.year_renovated || '',
                    currency: p.currency || 'USD',
                    status: p.status || 'active'
                };
                this.editing = true;
            },

            cancelEditing: function () {
                this.editing = false;
                if (this.multiProperty && !this.form.id) {
                    this.view = 'list';
                }
            },

            // ---- Save ----

            saveProperty: function () {
                var self = this;

                if (!self.form.name) {
                    NozuleUtils.toast(NozuleI18n.t('fill_required_fields'), 'error');
                    return;
                }

                // Auto-generate slug if empty.
                if (!self.form.slug) {
                    self.form.slug = self.form.name.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .trim();
                }

                self.saving = true;

                var data = JSON.parse(JSON.stringify(self.form));

                // Clean empty policies.
                if (data.policies) {
                    var cleaned = {};
                    for (var key in data.policies) {
                        if (data.policies[key]) {
                            cleaned[key] = data.policies[key];
                        }
                    }
                    data.policies = cleaned;
                }

                // Clean empty social links.
                if (data.social_links) {
                    var cleanedLinks = {};
                    for (var lk in data.social_links) {
                        if (data.social_links[lk]) {
                            cleanedLinks[lk] = data.social_links[lk];
                        }
                    }
                    data.social_links = cleanedLinks;
                }

                // Convert empty strings to null for optional numeric fields.
                var numericFields = ['star_rating', 'total_rooms', 'total_floors', 'year_built', 'year_renovated', 'latitude', 'longitude'];
                numericFields.forEach(function (f) {
                    if (data[f] === '' || data[f] === undefined) {
                        data[f] = null;
                    }
                });

                var promise;
                if (data.id) {
                    var id = data.id;
                    delete data.id;
                    promise = NozuleAPI.put('/admin/property/' + id, data);
                } else {
                    delete data.id;
                    promise = NozuleAPI.post('/admin/property', data);
                }

                promise.then(function (response) {
                    self.property = response.data;
                    self.editing = false;
                    NozuleUtils.toast(
                        NozuleI18n.t(self.form.id ? 'property_updated' : 'property_created'),
                        'success'
                    );
                    if (self.multiProperty) {
                        self.view = 'detail';
                        self.loadProperties();
                    } else {
                        self.loadProperty();
                    }
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_save_property'), 'error');
                }).finally(function () {
                    self.saving = false;
                });
            },

            // ---- Delete (multi-property only) ----

            deleteProperty: function (id) {
                var self = this;
                if (!confirm(NozuleI18n.t('confirm_delete_property') || 'Are you sure you want to delete this property? This action cannot be undone.')) {
                    return;
                }

                NozuleAPI.delete('/admin/property/' + id).then(function () {
                    NozuleUtils.toast(NozuleI18n.t('property_deleted') || 'Property deleted.', 'success');
                    self.property = null;
                    self.view = 'list';
                    self.loadProperties();
                }).catch(function (err) {
                    NozuleUtils.toast(err.message || NozuleI18n.t('failed_delete_property'), 'error');
                });
            },

            // ---- Photos ----

            addPhoto: function () {
                var self = this;
                var frame = wp.media({
                    title: NozuleI18n.t('select_photo') || 'Select Photo',
                    button: { text: NozuleI18n.t('use_image') || 'Use Image' },
                    multiple: true,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachments = frame.state().get('selection').toJSON();
                    attachments.forEach(function (attachment) {
                        self.form.photos.push({
                            url: attachment.url,
                            caption: attachment.caption || ''
                        });
                    });
                });

                frame.open();
            },

            removePhoto: function (index) {
                this.form.photos.splice(index, 1);
            },

            // ---- Facilities ----

            addFacility: function () {
                var name = this.newFacility.trim();
                if (!name) return;

                this.form.facilities.push({ name: name });
                this.newFacility = '';
            },

            removeFacility: function (index) {
                this.form.facilities.splice(index, 1);
            },

            // ---- Media Library ----

            openMediaLibrary: function (field) {
                var self = this;
                var frame = wp.media({
                    title: NozuleI18n.t('select_image') || 'Select Image',
                    button: { text: NozuleI18n.t('use_image') || 'Use Image' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    self.form[field] = attachment.url;
                });

                frame.open();
            }
        };
    });
});
