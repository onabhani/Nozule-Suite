<?php
/**
 * Admin template: Property Management
 *
 * Single-property PMS feature — manage hotel details, address, photos,
 * facilities, star rating, and policies.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlProperty">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Property', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage your hotel property details, photos, facilities, and policies.', 'nozule' ); ?></p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <template x-if="!property && !loading">
                <button class="nzl-btn nzl-btn-primary" @click="createNew()">
                    + <?php esc_html_e( 'Setup Property', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="property && !editing">
                <button class="nzl-btn nzl-btn-primary" @click="startEditing()">
                    <?php esc_html_e( 'Edit Property', 'nozule' ); ?>
                </button>
            </template>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- No Property Yet -->
    <template x-if="!loading && !property && !editing">
        <div class="nzl-card" style="text-align:center; padding:3rem; color:#94a3b8;">
            <p style="font-size:1.25rem; margin-bottom:0.5rem;"><?php esc_html_e( 'No property configured yet.', 'nozule' ); ?></p>
            <p><?php esc_html_e( 'Set up your hotel property details to get started.', 'nozule' ); ?></p>
        </div>
    </template>

    <!-- ======================== VIEW MODE ======================== -->
    <template x-if="!loading && property && !editing">
        <div>
            <!-- Header Card with Cover Image -->
            <div class="nzl-card" style="padding:0; overflow:hidden; margin-bottom:1.25rem;">
                <template x-if="property.cover_image_url">
                    <div style="height:200px; background-size:cover; background-position:center; position:relative;" :style="'background-image:url(' + property.cover_image_url + ')'">
                        <div style="position:absolute; inset:0; background:linear-gradient(transparent 40%, rgba(0,0,0,0.6));"></div>
                    </div>
                </template>
                <div style="padding:1.25rem;">
                    <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <template x-if="property.logo_url">
                            <img :src="property.logo_url" :alt="property.name" style="width:64px; height:64px; object-fit:contain; border-radius:0.5rem; border:1px solid #e2e8f0; background:#f8fafc; padding:4px;">
                        </template>
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                                <h2 style="margin:0; font-size:1.5rem;" x-text="property.name"></h2>
                                <template x-if="property.star_rating">
                                    <span style="color:#f59e0b; font-size:1rem;" x-text="'★'.repeat(property.star_rating)"></span>
                                </template>
                                <span class="nzl-badge" :class="property.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-pending'" x-text="property.status"></span>
                            </div>
                            <div style="color:#64748b; font-size:0.875rem; margin-top:0.25rem;" x-show="property.name_ar" dir="rtl" x-text="property.name_ar"></div>
                            <div style="color:#64748b; font-size:0.875rem; margin-top:0.25rem;" x-show="property.property_type">
                                <span x-text="property.property_type.charAt(0).toUpperCase() + property.property_type.slice(1)"></span>
                                <template x-if="property.city || property.country">
                                    <span> &mdash; <span x-text="[property.city, property.country].filter(Boolean).join(', ')"></span></span>
                                </template>
                            </div>
                        </div>
                        <div style="font-size:0.75rem; color:#94a3b8;">
                            <div><?php esc_html_e( 'Property ID:', 'nozule' ); ?> <code style="font-size:0.7rem;" x-text="property.property_id"></code></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details Grid -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.25rem;">

                <!-- Contact & Address -->
                <div class="nzl-card">
                    <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Contact & Address', 'nozule' ); ?></h3>
                    <div style="display:flex; flex-direction:column; gap:0.5rem; font-size:0.875rem;">
                        <template x-if="property.address_line_1">
                            <div><strong><?php esc_html_e( 'Address:', 'nozule' ); ?></strong> <span x-text="[property.address_line_1, property.address_line_2].filter(Boolean).join(', ')"></span></div>
                        </template>
                        <template x-if="property.city || property.state_province || property.postal_code">
                            <div><strong><?php esc_html_e( 'City:', 'nozule' ); ?></strong> <span x-text="[property.city, property.state_province, property.postal_code].filter(Boolean).join(', ')"></span></div>
                        </template>
                        <template x-if="property.country">
                            <div><strong><?php esc_html_e( 'Country:', 'nozule' ); ?></strong> <span x-text="property.country"></span></div>
                        </template>
                        <template x-if="property.phone">
                            <div><strong><?php esc_html_e( 'Phone:', 'nozule' ); ?></strong> <span x-text="property.phone"></span></div>
                        </template>
                        <template x-if="property.email">
                            <div><strong><?php esc_html_e( 'Email:', 'nozule' ); ?></strong> <span x-text="property.email"></span></div>
                        </template>
                        <template x-if="property.website">
                            <div><strong><?php esc_html_e( 'Website:', 'nozule' ); ?></strong> <a :href="property.website" target="_blank" x-text="property.website" style="color:#3b82f6;"></a></div>
                        </template>
                    </div>
                </div>

                <!-- Operations -->
                <div class="nzl-card">
                    <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Operations', 'nozule' ); ?></h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; font-size:0.875rem;">
                        <div><strong><?php esc_html_e( 'Check-in:', 'nozule' ); ?></strong> <span x-text="property.check_in_time || '14:00'"></span></div>
                        <div><strong><?php esc_html_e( 'Check-out:', 'nozule' ); ?></strong> <span x-text="property.check_out_time || '12:00'"></span></div>
                        <div><strong><?php esc_html_e( 'Timezone:', 'nozule' ); ?></strong> <span x-text="property.timezone || '-'"></span></div>
                        <div><strong><?php esc_html_e( 'Currency:', 'nozule' ); ?></strong> <span x-text="property.currency || 'USD'"></span></div>
                        <template x-if="property.total_rooms">
                            <div><strong><?php esc_html_e( 'Total Rooms:', 'nozule' ); ?></strong> <span x-text="property.total_rooms"></span></div>
                        </template>
                        <template x-if="property.total_floors">
                            <div><strong><?php esc_html_e( 'Total Floors:', 'nozule' ); ?></strong> <span x-text="property.total_floors"></span></div>
                        </template>
                        <template x-if="property.year_built">
                            <div><strong><?php esc_html_e( 'Year Built:', 'nozule' ); ?></strong> <span x-text="property.year_built"></span></div>
                        </template>
                        <template x-if="property.year_renovated">
                            <div><strong><?php esc_html_e( 'Renovated:', 'nozule' ); ?></strong> <span x-text="property.year_renovated"></span></div>
                        </template>
                    </div>
                </div>

                <!-- Description -->
                <template x-if="property.description">
                    <div class="nzl-card">
                        <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Description', 'nozule' ); ?></h3>
                        <p style="font-size:0.875rem; color:#475569; white-space:pre-line; margin:0;" x-text="property.description"></p>
                        <template x-if="property.description_ar">
                            <p style="font-size:0.875rem; color:#64748b; white-space:pre-line; margin:0.75rem 0 0;" dir="rtl" x-text="property.description_ar"></p>
                        </template>
                    </div>
                </template>

                <!-- Facilities -->
                <template x-if="property.facilities && property.facilities.length > 0">
                    <div class="nzl-card">
                        <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Facilities', 'nozule' ); ?></h3>
                        <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                            <template x-for="(facility, idx) in property.facilities" :key="idx">
                                <span class="nzl-badge" style="font-size:0.8rem;" x-text="facility.name || facility"></span>
                            </template>
                        </div>
                    </div>
                </template>

                <!-- Policies -->
                <template x-if="property.policies && Object.keys(property.policies).length > 0">
                    <div class="nzl-card">
                        <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Policies', 'nozule' ); ?></h3>
                        <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.875rem;">
                            <template x-for="(value, key) in property.policies" :key="key">
                                <div>
                                    <strong style="text-transform:capitalize;" x-text="key.replace(/_/g, ' ') + ':'"></strong>
                                    <span style="color:#475569;" x-text="value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Photos Gallery -->
            <template x-if="property.photos && property.photos.length > 0">
                <div class="nzl-card" style="margin-top:1.25rem;">
                    <h3 style="margin:0 0 1rem; font-size:1rem; font-weight:600;"><?php esc_html_e( 'Photos', 'nozule' ); ?> <span style="color:#94a3b8; font-weight:400;" x-text="'(' + property.photos.length + ')'"></span></h3>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:0.75rem;">
                        <template x-for="(photo, idx) in property.photos" :key="idx">
                            <div style="aspect-ratio:4/3; border-radius:0.5rem; overflow:hidden; border:1px solid #e2e8f0;">
                                <img :src="photo.url || photo" :alt="photo.caption || ''" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- ======================== EDIT MODE ======================== -->
    <template x-if="!loading && editing">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h2 style="margin:0; font-size:1.25rem;" x-text="form.id ? '<?php echo esc_js( __( 'Edit Property', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Property', 'nozule' ) ); ?>'"></h2>
                <div style="display:flex; gap:0.5rem;">
                    <button class="nzl-btn" @click="cancelEditing()"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveProperty()" :disabled="saving">
                        <span x-show="!saving"><?php esc_html_e( 'Save Property', 'nozule' ); ?></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>

            <!-- ========== General Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'General Information', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Property Name *', 'nozule' ); ?></label>
                        <input type="text" x-model="form.name" class="nzl-input" dir="ltr" placeholder="<?php esc_attr_e( 'Grand Hotel Damascus', 'nozule' ); ?>">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Property Name (Arabic)', 'nozule' ); ?></label>
                        <input type="text" x-model="form.name_ar" class="nzl-input" dir="rtl">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Property Type', 'nozule' ); ?></label>
                        <select x-model="form.property_type" class="nzl-input">
                            <option value="hotel"><?php esc_html_e( 'Hotel', 'nozule' ); ?></option>
                            <option value="resort"><?php esc_html_e( 'Resort', 'nozule' ); ?></option>
                            <option value="boutique"><?php esc_html_e( 'Boutique Hotel', 'nozule' ); ?></option>
                            <option value="hostel"><?php esc_html_e( 'Hostel', 'nozule' ); ?></option>
                            <option value="apartment"><?php esc_html_e( 'Serviced Apartment', 'nozule' ); ?></option>
                            <option value="guesthouse"><?php esc_html_e( 'Guesthouse', 'nozule' ); ?></option>
                            <option value="villa"><?php esc_html_e( 'Villa', 'nozule' ); ?></option>
                            <option value="motel"><?php esc_html_e( 'Motel', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Star Rating', 'nozule' ); ?></label>
                        <select x-model="form.star_rating" class="nzl-input">
                            <option value=""><?php esc_html_e( '— None —', 'nozule' ); ?></option>
                            <option value="1">&#9733; (1)</option>
                            <option value="2">&#9733;&#9733; (2)</option>
                            <option value="3">&#9733;&#9733;&#9733; (3)</option>
                            <option value="4">&#9733;&#9733;&#9733;&#9733; (4)</option>
                            <option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; (5)</option>
                        </select>
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                        <select x-model="form.status" class="nzl-input">
                            <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                            <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                        <input type="text" x-model="form.currency" class="nzl-input" dir="ltr" maxlength="3" placeholder="USD" style="max-width:120px;">
                    </div>
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Description', 'nozule' ); ?></label>
                    <textarea x-model="form.description" class="nzl-input" dir="ltr" rows="3" placeholder="<?php esc_attr_e( 'A brief description of your property...', 'nozule' ); ?>"></textarea>
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Description (Arabic)', 'nozule' ); ?></label>
                    <textarea x-model="form.description_ar" class="nzl-input" dir="rtl" rows="3"></textarea>
                </div>
            </div>

            <!-- ========== Address Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Address', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Address Line 1', 'nozule' ); ?></label>
                    <input type="text" x-model="form.address_line_1" class="nzl-input" dir="ltr">
                </div>
                <div class="nzl-form-group">
                    <label><?php esc_html_e( 'Address Line 2', 'nozule' ); ?></label>
                    <input type="text" x-model="form.address_line_2" class="nzl-input" dir="ltr">
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'City', 'nozule' ); ?></label>
                        <input type="text" x-model="form.city" class="nzl-input" dir="ltr">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'State / Province', 'nozule' ); ?></label>
                        <input type="text" x-model="form.state_province" class="nzl-input" dir="ltr">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Country', 'nozule' ); ?></label>
                        <input type="text" x-model="form.country" class="nzl-input" dir="ltr">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Postal Code', 'nozule' ); ?></label>
                        <input type="text" x-model="form.postal_code" class="nzl-input" dir="ltr" style="max-width:200px;">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Latitude', 'nozule' ); ?></label>
                        <input type="text" x-model="form.latitude" class="nzl-input" dir="ltr" placeholder="33.5138073" style="max-width:200px;">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Longitude', 'nozule' ); ?></label>
                        <input type="text" x-model="form.longitude" class="nzl-input" dir="ltr" placeholder="36.2765279" style="max-width:200px;">
                    </div>
                </div>
            </div>

            <!-- ========== Contact Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Contact', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Phone', 'nozule' ); ?></label>
                        <input type="text" x-model="form.phone" class="nzl-input" dir="ltr" placeholder="+963 11 123 4567">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Alternative Phone', 'nozule' ); ?></label>
                        <input type="text" x-model="form.phone_alt" class="nzl-input" dir="ltr">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Email', 'nozule' ); ?></label>
                        <input type="email" x-model="form.email" class="nzl-input" dir="ltr">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Website', 'nozule' ); ?></label>
                        <input type="url" x-model="form.website" class="nzl-input" dir="ltr" placeholder="https://">
                    </div>
                </div>
            </div>

            <!-- ========== Operations Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Operations', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Check-in Time', 'nozule' ); ?></label>
                        <input type="time" x-model="form.check_in_time" class="nzl-input" style="max-width:160px;">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Check-out Time', 'nozule' ); ?></label>
                        <input type="time" x-model="form.check_out_time" class="nzl-input" style="max-width:160px;">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Timezone', 'nozule' ); ?></label>
                        <input type="text" x-model="form.timezone" class="nzl-input" dir="ltr" placeholder="Asia/Damascus">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Total Rooms', 'nozule' ); ?></label>
                        <input type="number" x-model="form.total_rooms" class="nzl-input" min="0" style="max-width:120px;">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Total Floors', 'nozule' ); ?></label>
                        <input type="number" x-model="form.total_floors" class="nzl-input" min="0" style="max-width:120px;">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Year Built', 'nozule' ); ?></label>
                        <input type="number" x-model="form.year_built" class="nzl-input" min="1800" max="2100" style="max-width:120px;">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Year Renovated', 'nozule' ); ?></label>
                        <input type="number" x-model="form.year_renovated" class="nzl-input" min="1800" max="2100" style="max-width:120px;">
                    </div>
                </div>
            </div>

            <!-- ========== Media Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Media', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Logo', 'nozule' ); ?></label>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <template x-if="form.logo_url">
                                <div style="position:relative; display:inline-block;">
                                    <img :src="form.logo_url" alt="Logo" style="max-width:120px; max-height:60px; object-fit:contain; border:1px solid #e2e8f0; border-radius:0.375rem; padding:0.25rem; background:#f8fafc;">
                                    <button type="button" @click="form.logo_url = ''" style="position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#ef4444; color:white; border:none; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                                </div>
                            </template>
                            <button type="button" class="nzl-btn nzl-btn-sm" @click="openMediaLibrary('logo_url')"><?php esc_html_e( 'Upload Logo', 'nozule' ); ?></button>
                        </div>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Cover Image', 'nozule' ); ?></label>
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <template x-if="form.cover_image_url">
                                <div style="position:relative; display:inline-block;">
                                    <img :src="form.cover_image_url" alt="Cover" style="max-width:180px; max-height:90px; object-fit:cover; border:1px solid #e2e8f0; border-radius:0.375rem;">
                                    <button type="button" @click="form.cover_image_url = ''" style="position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#ef4444; color:white; border:none; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                                </div>
                            </template>
                            <button type="button" class="nzl-btn nzl-btn-sm" @click="openMediaLibrary('cover_image_url')"><?php esc_html_e( 'Upload Cover', 'nozule' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div class="nzl-form-group" style="margin-top:1rem;">
                    <label><?php esc_html_e( 'Property Photos', 'nozule' ); ?></label>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:0.75rem; margin-bottom:0.75rem;">
                        <template x-for="(photo, idx) in form.photos" :key="idx">
                            <div style="position:relative; aspect-ratio:4/3; border-radius:0.375rem; overflow:hidden; border:1px solid #e2e8f0;">
                                <img :src="photo.url || photo" style="width:100%; height:100%; object-fit:cover;">
                                <button type="button" @click="removePhoto(idx)" style="position:absolute; top:4px; right:4px; width:22px; height:22px; border-radius:50%; background:#ef4444; color:white; border:none; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center;">&times;</button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="nzl-btn nzl-btn-sm" @click="addPhoto()">+ <?php esc_html_e( 'Add Photo', 'nozule' ); ?></button>
                </div>
            </div>

            <!-- ========== Facilities Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Facilities & Amenities', 'nozule' ); ?>
                </h4>
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                    <template x-for="(facility, idx) in form.facilities" :key="idx">
                        <span style="display:inline-flex; align-items:center; gap:0.25rem; padding:0.25rem 0.5rem; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:0.375rem; font-size:0.8rem;">
                            <span x-text="facility.name || facility"></span>
                            <button type="button" @click="removeFacility(idx)" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.9rem; line-height:1; padding:0 2px;">&times;</button>
                        </span>
                    </template>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <input type="text" x-model="newFacility" class="nzl-input" dir="ltr" placeholder="<?php esc_attr_e( 'e.g. Free WiFi, Swimming Pool, Gym...', 'nozule' ); ?>" @keydown.enter.prevent="addFacility()" style="max-width:300px;">
                    <button type="button" class="nzl-btn nzl-btn-sm" @click="addFacility()">+ <?php esc_html_e( 'Add', 'nozule' ); ?></button>
                </div>
            </div>

            <!-- ========== Policies Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Policies', 'nozule' ); ?>
                </h4>
                <div style="display:flex; flex-direction:column; gap:0.75rem;">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Cancellation Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.cancellation" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Free cancellation up to 24 hours before check-in...', 'nozule' ); ?>"></textarea>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Children Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.children" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Children of all ages are welcome...', 'nozule' ); ?>"></textarea>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Pet Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.pets" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Pets are not allowed...', 'nozule' ); ?>"></textarea>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Smoking Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.smoking" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Smoking is not permitted inside the hotel...', 'nozule' ); ?>"></textarea>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Payment Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.payment" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Payment is due upon check-in. We accept cash and credit cards...', 'nozule' ); ?>"></textarea>
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Extra Bed Policy', 'nozule' ); ?></label>
                        <textarea x-model="form.policies.extra_bed" class="nzl-input" rows="2" dir="ltr" placeholder="<?php esc_attr_e( 'Extra beds are available upon request...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- ========== Legal / Registration Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Legal & Registration', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Tax ID / VAT Number', 'nozule' ); ?></label>
                        <input type="text" x-model="form.tax_id" class="nzl-input" dir="ltr">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Hotel License Number', 'nozule' ); ?></label>
                        <input type="text" x-model="form.license_number" class="nzl-input" dir="ltr">
                    </div>
                </div>
            </div>

            <!-- ========== Social Links Section ========== -->
            <div class="nzl-card" style="margin-bottom:1.25rem;">
                <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:0 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                    <?php esc_html_e( 'Social Links', 'nozule' ); ?>
                </h4>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label>Facebook</label>
                        <input type="url" x-model="form.social_links.facebook" class="nzl-input" dir="ltr" placeholder="https://facebook.com/...">
                    </div>
                    <div class="nzl-form-group">
                        <label>Instagram</label>
                        <input type="url" x-model="form.social_links.instagram" class="nzl-input" dir="ltr" placeholder="https://instagram.com/...">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label>X (Twitter)</label>
                        <input type="url" x-model="form.social_links.twitter" class="nzl-input" dir="ltr" placeholder="https://x.com/...">
                    </div>
                    <div class="nzl-form-group">
                        <label>TripAdvisor</label>
                        <input type="url" x-model="form.social_links.tripadvisor" class="nzl-input" dir="ltr" placeholder="https://tripadvisor.com/...">
                    </div>
                </div>
                <div class="nzl-form-grid">
                    <div class="nzl-form-group">
                        <label>Google Maps</label>
                        <input type="url" x-model="form.social_links.google_maps" class="nzl-input" dir="ltr" placeholder="https://maps.google.com/...">
                    </div>
                </div>
            </div>

            <!-- Save Button (bottom) -->
            <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                <button class="nzl-btn" @click="cancelEditing()"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                <button class="nzl-btn nzl-btn-primary" @click="saveProperty()" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Save Property', 'nozule' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                </button>
            </div>
        </div>
    </template>
</div>

<!-- Toast Notifications -->
<div class="nzl-toast-container" x-data x-show="$store.notifications.items.length > 0">
    <template x-for="notif in $store.notifications.items" :key="notif.id">
        <div class="nzl-toast" :class="'nzl-toast-' + notif.type">
            <span x-text="notif.message"></span>
            <button @click="$store.notifications.remove(notif.id)" style="margin-left:0.5rem; cursor:pointer; background:none; border:none;">&times;</button>
        </div>
    </template>
</div>
