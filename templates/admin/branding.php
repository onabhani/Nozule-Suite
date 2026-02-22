<?php
/**
 * Admin template: Branding / White-Label (NZL-041)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="nzl-admin-wrap" x-data="nzlBranding">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Branding', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage white-label brands and customize the look and feel of your property.', 'nozule' ); ?></p>
        </div>
        <button class="nzl-btn nzl-btn-primary" @click="openBrandModal()">
            + <?php esc_html_e( 'Add Brand', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Brand Cards Grid -->
    <template x-if="!loading">
        <div>
            <template x-if="brands.length === 0">
                <div class="nzl-card" style="text-align:center; padding:3rem; color:#94a3b8;">
                    <p style="font-size:1.25rem; margin-bottom:0.5rem;"><?php esc_html_e( 'No brands configured yet.', 'nozule' ); ?></p>
                    <p><?php esc_html_e( 'Create your first brand to start customizing the look and feel.', 'nozule' ); ?></p>
                </div>
            </template>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:1.25rem;">
                <template x-for="brand in brands" :key="brand.id">
                    <div class="nzl-card" style="position:relative; overflow:hidden;">
                        <!-- Color band at top -->
                        <div style="height:6px; display:flex; margin:-1.25rem -1.25rem 1rem -1.25rem;">
                            <div :style="'flex:1; background:' + (brand.primary_color || '#1e40af')"></div>
                            <div :style="'flex:1; background:' + (brand.secondary_color || '#3b82f6')"></div>
                            <div :style="'flex:1; background:' + (brand.accent_color || '#f59e0b')"></div>
                        </div>

                        <!-- Default badge -->
                        <template x-if="brand.is_default">
                            <span class="nzl-badge nzl-badge-confirmed" style="position:absolute; top:14px; right:12px;">
                                <?php esc_html_e( 'Default', 'nozule' ); ?>
                            </span>
                        </template>

                        <!-- Inactive badge -->
                        <template x-if="!brand.is_active">
                            <span class="nzl-badge nzl-badge-pending" style="position:absolute; top:14px; right:12px;">
                                <?php esc_html_e( 'Inactive', 'nozule' ); ?>
                            </span>
                        </template>

                        <!-- Logo + Name -->
                        <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1rem;">
                            <template x-if="brand.logo_url">
                                <img :src="brand.logo_url" :alt="brand.name" style="width:48px; height:48px; object-fit:contain; border-radius:0.375rem; border:1px solid #e2e8f0;">
                            </template>
                            <template x-if="!brand.logo_url">
                                <div style="width:48px; height:48px; border-radius:0.375rem; display:flex; align-items:center; justify-content:center; font-size:1.25rem; font-weight:700; color:white;"
                                     :style="'background:' + (brand.primary_color || '#1e40af')">
                                    <span x-text="brand.name ? brand.name.charAt(0).toUpperCase() : 'B'"></span>
                                </div>
                            </template>
                            <div>
                                <div style="font-weight:600; font-size:1rem;" x-text="brand.name"></div>
                                <div style="font-size:0.8rem; color:#94a3b8;" dir="rtl" x-show="brand.name_ar" x-text="brand.name_ar"></div>
                            </div>
                        </div>

                        <!-- Color swatches -->
                        <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                            <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                                <div style="width:32px; height:32px; border-radius:0.375rem; border:1px solid #e2e8f0;" :style="'background:' + (brand.primary_color || '#1e40af')"></div>
                                <span style="font-size:0.65rem; color:#94a3b8;"><?php esc_html_e( 'Primary', 'nozule' ); ?></span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                                <div style="width:32px; height:32px; border-radius:0.375rem; border:1px solid #e2e8f0;" :style="'background:' + (brand.secondary_color || '#3b82f6')"></div>
                                <span style="font-size:0.65rem; color:#94a3b8;"><?php esc_html_e( 'Secondary', 'nozule' ); ?></span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                                <div style="width:32px; height:32px; border-radius:0.375rem; border:1px solid #e2e8f0;" :style="'background:' + (brand.accent_color || '#f59e0b')"></div>
                                <span style="font-size:0.65rem; color:#94a3b8;"><?php esc_html_e( 'Accent', 'nozule' ); ?></span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; gap:0.2rem;">
                                <div style="width:32px; height:32px; border-radius:0.375rem; border:1px solid #e2e8f0;" :style="'background:' + (brand.text_color || '#1e293b')"></div>
                                <span style="font-size:0.65rem; color:#94a3b8;"><?php esc_html_e( 'Text', 'nozule' ); ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex; gap:0.5rem; border-top:1px solid #e2e8f0; padding-top:0.75rem;">
                            <button class="nzl-btn nzl-btn-sm" @click="openBrandModal(brand)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                            <template x-if="!brand.is_default">
                                <button class="nzl-btn nzl-btn-sm" @click="setDefault(brand.id)"><?php esc_html_e( 'Set Default', 'nozule' ); ?></button>
                            </template>
                            <template x-if="!brand.is_default">
                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteBrand(brand.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- ======================= CREATE/EDIT MODAL ======================= -->
    <template x-if="showBrandModal">
        <div class="nzl-modal-overlay" @click.self="showBrandModal = false">
            <div class="nzl-modal" style="max-width:780px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingBrandId ? '<?php echo esc_js( __( 'Edit Brand', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Brand', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showBrandModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">

                    <!-- ========== General Section ========== -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'General', 'nozule' ); ?>
                    </h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Brand Name *', 'nozule' ); ?></label>
                            <input type="text" x-model="brandForm.name" class="nzl-input" dir="ltr" placeholder="<?php esc_attr_e( 'My Hotel Brand', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Brand Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="brandForm.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Logo', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <template x-if="brandForm.logo_url">
                                    <div style="position:relative; display:inline-block;">
                                        <img :src="brandForm.logo_url" alt="Logo" style="max-width:120px; max-height:60px; object-fit:contain; border:1px solid #e2e8f0; border-radius:0.375rem; padding:0.25rem; background:#f8fafc;">
                                        <button type="button" @click="brandForm.logo_url = ''" style="position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#ef4444; color:white; border:none; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1;">&times;</button>
                                    </div>
                                </template>
                                <button type="button" class="nzl-btn nzl-btn-sm" @click="openMediaLibrary('logo_url')">
                                    <?php esc_html_e( 'Upload Logo', 'nozule' ); ?>
                                </button>
                            </div>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Favicon', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <template x-if="brandForm.favicon_url">
                                    <div style="position:relative; display:inline-block;">
                                        <img :src="brandForm.favicon_url" alt="Favicon" style="width:32px; height:32px; object-fit:contain; border:1px solid #e2e8f0; border-radius:0.25rem; padding:2px; background:#f8fafc;">
                                        <button type="button" @click="brandForm.favicon_url = ''" style="position:absolute; top:-6px; right:-6px; width:20px; height:20px; border-radius:50%; background:#ef4444; color:white; border:none; font-size:0.75rem; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1;">&times;</button>
                                    </div>
                                </template>
                                <button type="button" class="nzl-btn nzl-btn-sm" @click="openMediaLibrary('favicon_url')">
                                    <?php esc_html_e( 'Upload Favicon', 'nozule' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ========== Colors Section ========== -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:1.5rem 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Colors', 'nozule' ); ?>
                    </h4>
                    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:1rem;">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Primary Color', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <input type="color" x-model="brandForm.primary_color" style="width:40px; height:36px; border:1px solid #e2e8f0; border-radius:0.25rem; cursor:pointer; padding:2px;">
                                <input type="text" x-model="brandForm.primary_color" class="nzl-input" dir="ltr" style="font-family:monospace; font-size:0.8rem;">
                            </div>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Secondary Color', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <input type="color" x-model="brandForm.secondary_color" style="width:40px; height:36px; border:1px solid #e2e8f0; border-radius:0.25rem; cursor:pointer; padding:2px;">
                                <input type="text" x-model="brandForm.secondary_color" class="nzl-input" dir="ltr" style="font-family:monospace; font-size:0.8rem;">
                            </div>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Accent Color', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <input type="color" x-model="brandForm.accent_color" style="width:40px; height:36px; border:1px solid #e2e8f0; border-radius:0.25rem; cursor:pointer; padding:2px;">
                                <input type="text" x-model="brandForm.accent_color" class="nzl-input" dir="ltr" style="font-family:monospace; font-size:0.8rem;">
                            </div>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Text Color', 'nozule' ); ?></label>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <input type="color" x-model="brandForm.text_color" style="width:40px; height:36px; border:1px solid #e2e8f0; border-radius:0.25rem; cursor:pointer; padding:2px;">
                                <input type="text" x-model="brandForm.text_color" class="nzl-input" dir="ltr" style="font-family:monospace; font-size:0.8rem;">
                            </div>
                        </div>
                    </div>

                    <!-- Live Color Preview -->
                    <div style="margin-top:1rem; padding:1rem; border:1px solid #e2e8f0; border-radius:0.5rem; background:#f8fafc;">
                        <label style="font-size:0.75rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.05em; display:block; margin-bottom:0.5rem;"><?php esc_html_e( 'Preview', 'nozule' ); ?></label>
                        <div style="display:flex; gap:1rem; align-items:stretch; flex-wrap:wrap;">
                            <!-- Sample card -->
                            <div style="flex:1; min-width:200px; border-radius:0.5rem; overflow:hidden; border:1px solid #e2e8f0; background:white;">
                                <div style="padding:0.75rem;" :style="'background:' + brandForm.primary_color + '; color:white;'">
                                    <div style="font-weight:600; font-size:0.9rem;" x-text="brandForm.name || '<?php echo esc_js( __( 'Brand Name', 'nozule' ) ); ?>'"></div>
                                </div>
                                <div style="padding:0.75rem;" :style="'color:' + brandForm.text_color">
                                    <p style="font-size:0.8rem; margin:0 0 0.5rem;"><?php esc_html_e( 'Sample content showing how text appears with the selected colors.', 'nozule' ); ?></p>
                                    <div style="display:flex; gap:0.5rem;">
                                        <button style="padding:0.35rem 0.75rem; border:none; border-radius:0.25rem; font-size:0.8rem; color:white; cursor:pointer;" :style="'background:' + brandForm.primary_color">
                                            <?php esc_html_e( 'Primary', 'nozule' ); ?>
                                        </button>
                                        <button style="padding:0.35rem 0.75rem; border:none; border-radius:0.25rem; font-size:0.8rem; color:white; cursor:pointer;" :style="'background:' + brandForm.secondary_color">
                                            <?php esc_html_e( 'Secondary', 'nozule' ); ?>
                                        </button>
                                        <button style="padding:0.35rem 0.75rem; border:none; border-radius:0.25rem; font-size:0.8rem; color:white; cursor:pointer;" :style="'background:' + brandForm.accent_color">
                                            <?php esc_html_e( 'Accent', 'nozule' ); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Color bars -->
                            <div style="display:flex; flex-direction:column; gap:0.35rem; justify-content:center; min-width:100px;">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div style="width:24px; height:24px; border-radius:50%; border:1px solid #e2e8f0;" :style="'background:' + brandForm.primary_color"></div>
                                    <span style="font-size:0.75rem; color:#64748b;" x-text="brandForm.primary_color"></span>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div style="width:24px; height:24px; border-radius:50%; border:1px solid #e2e8f0;" :style="'background:' + brandForm.secondary_color"></div>
                                    <span style="font-size:0.75rem; color:#64748b;" x-text="brandForm.secondary_color"></span>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div style="width:24px; height:24px; border-radius:50%; border:1px solid #e2e8f0;" :style="'background:' + brandForm.accent_color"></div>
                                    <span style="font-size:0.75rem; color:#64748b;" x-text="brandForm.accent_color"></span>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <div style="width:24px; height:24px; border-radius:50%; border:1px solid #e2e8f0;" :style="'background:' + brandForm.text_color"></div>
                                    <span style="font-size:0.75rem; color:#64748b;" x-text="brandForm.text_color"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========== Email Section ========== -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:1.5rem 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Email', 'nozule' ); ?>
                    </h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Email Header HTML', 'nozule' ); ?></label>
                            <textarea x-model="brandForm.email_header_html" class="nzl-input" dir="ltr" rows="4" placeholder="<?php esc_attr_e( '<div style=&quot;background:#1e40af; padding:20px; text-align:center;&quot;>...</div>', 'nozule' ); ?>" style="font-family:monospace; font-size:0.8rem;"></textarea>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Email Footer HTML', 'nozule' ); ?></label>
                            <textarea x-model="brandForm.email_footer_html" class="nzl-input" dir="ltr" rows="4" placeholder="<?php esc_attr_e( '<div style=&quot;text-align:center; padding:10px; color:#999;&quot;>...</div>', 'nozule' ); ?>" style="font-family:monospace; font-size:0.8rem;"></textarea>
                        </div>
                    </div>

                    <!-- ========== Advanced Section ========== -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; text-transform:uppercase; letter-spacing:0.05em; margin:1.5rem 0 0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0;">
                        <?php esc_html_e( 'Advanced', 'nozule' ); ?>
                    </h4>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Custom CSS', 'nozule' ); ?></label>
                        <textarea x-model="brandForm.custom_css" class="nzl-input" dir="ltr" rows="6" placeholder="<?php esc_attr_e( '/* Custom CSS overrides */', 'nozule' ); ?>" style="font-family:'Courier New', Courier, monospace; font-size:0.8rem; tab-size:2;"></textarea>
                        <p style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;"><?php esc_html_e( 'Additional CSS rules injected on public pages when this brand is active.', 'nozule' ); ?></p>
                    </div>

                    <!-- Active toggle -->
                    <div style="margin-top:1rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="brandForm.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>

                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showBrandModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveBrand()" :disabled="saving">
                        <span x-show="!saving" x-text="editingBrandId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
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
