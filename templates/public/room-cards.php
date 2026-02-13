<?php
/**
 * Template: Public Room Cards Display
 *
 * @var array $atts Shortcode attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$columns = (int) ( $atts['columns'] ?? 3 );
?>
<div class="nzl-widget" x-data="roomCards">
    <!-- Loading -->
    <template x-if="loading">
        <div style="text-align:center; padding:2rem;">
            <div class="nzl-spinner nzl-spinner-lg"></div>
        </div>
    </template>

    <!-- Error -->
    <template x-if="error">
        <div class="nzl-card">
            <div class="nzl-card-body" style="color:#ef4444;" x-text="error"></div>
        </div>
    </template>

    <!-- Room Grid -->
    <template x-if="!loading && !error">
        <div style="display:grid; grid-template-columns:repeat(<?php echo esc_attr( $columns ); ?>, 1fr); gap:1.5rem;">
            <template x-for="room in rooms" :key="room.id">
                <div class="nzl-card nzl-room-card">
                    <!-- Image -->
                    <template x-if="getImages(room).length > 0">
                        <img class="nzl-room-image" :src="getImages(room)[0]" :alt="getLocalizedName(room)">
                    </template>
                    <template x-if="getImages(room).length === 0">
                        <div style="height:200px; background:#f3f4f6; display:flex; align-items:center; justify-content:center; color:#9ca3af;">
                            <?php esc_html_e( 'No Image', 'nozule' ); ?>
                        </div>
                    </template>

                    <div class="nzl-card-body">
                        <h3 style="font-size:1.125rem; font-weight:600; margin:0 0 0.5rem 0;" x-text="getLocalizedName(room)"></h3>
                        <p style="font-size:0.875rem; color:#6b7280; margin:0 0 0.75rem 0;" x-text="getLocalizedDescription(room)"></p>

                        <!-- Details -->
                        <div style="display:flex; gap:1rem; flex-wrap:wrap; font-size:0.75rem; color:#6b7280; margin-bottom:0.75rem;">
                            <template x-if="room.size_sqm">
                                <span x-text="room.size_sqm + ' m²'"></span>
                            </template>
                            <template x-if="room.bed_type">
                                <span x-text="room.bed_type"></span>
                            </template>
                            <span x-text="'Max ' + room.max_occupancy + ' guests'"></span>
                        </div>

                        <!-- Amenities -->
                        <template x-if="getAmenities(room).length > 0">
                            <div style="display:flex; flex-wrap:wrap; gap:0.375rem; margin-bottom:0.75rem;">
                                <template x-for="amenity in getAmenities(room).slice(0, 5)" :key="amenity">
                                    <span style="font-size:0.675rem; padding:0.125rem 0.5rem; background:#f3f4f6; border-radius:9999px; color:#374151;" x-text="amenity"></span>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="nzl-card-footer" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span class="nzl-room-price" x-text="formatPrice(room.base_price)"></span>
                            <span style="font-size:0.75rem; color:#6b7280;"> / <?php esc_html_e( 'night', 'nozule' ); ?></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
