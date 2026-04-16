<?php
/**
 * Template: My Account (guest portal)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $login_url = esc_url( (string) apply_filters( 'nozule/portal/login_url', wp_login_url( get_permalink() ) ) );
    printf(
        '<div class="nzl-card"><div class="nzl-card-body">%s <a href="%s">%s</a></div></div>',
        esc_html__( 'Please sign in to view your account.', 'nozule' ),
        $login_url,
        esc_html__( 'Sign in', 'nozule' )
    );
    return;
}
?>
<div class="nzl-widget" x-data="myAccount" x-init="load()">
    <!-- Tabs -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div class="nzl-card-body" style="display:flex; gap:0.5rem; padding:0.5rem; overflow-x:auto;">
            <template x-for="t in tabs" :key="t.key">
                <button
                    class="nzl-btn"
                    :class="tab === t.key ? 'nzl-btn-primary' : 'nzl-btn-ghost'"
                    @click="tab = t.key"
                    x-text="t.label"
                    style="white-space:nowrap;">
                </button>
            </template>
            <button class="nzl-btn nzl-btn-ghost" @click="logout()" style="margin-inline-start:auto;">
                <?php esc_html_e( 'Sign out', 'nozule' ); ?>
            </button>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-card"><div class="nzl-card-body" style="text-align:center; padding:2rem; color:#6b7280;"><?php esc_html_e( 'Loading…', 'nozule' ); ?></div></div>
    </template>

    <!-- Error -->
    <template x-if="error">
        <div class="nzl-alert" style="padding:1rem; background:#fee2e2; color:#991b1b; border-radius:0.375rem;" x-text="error"></div>
    </template>

    <!-- Upcoming bookings -->
    <template x-if="!loading && tab === 'upcoming'">
        <div>
            <template x-if="upcoming.length === 0">
                <div class="nzl-card"><div class="nzl-card-body" style="text-align:center; padding:2rem; color:#6b7280;"><?php esc_html_e( 'No upcoming stays.', 'nozule' ); ?></div></div>
            </template>
            <template x-for="b in upcoming" :key="b.id">
                <div class="nzl-card" style="margin-bottom:0.75rem;">
                    <div class="nzl-card-body" style="display:grid; grid-template-columns:1fr auto; gap:1rem; align-items:center;">
                        <div>
                            <div style="font-weight:600;" x-text="b.booking_number"></div>
                            <div style="color:#6b7280; font-size:0.875rem;">
                                <span x-text="b.check_in"></span> &rarr; <span x-text="b.check_out"></span>
                                (<span x-text="b.nights"></span> <?php esc_html_e( 'nights', 'nozule' ); ?>)
                            </div>
                            <div style="margin-top:0.25rem;">
                                <span class="nzl-badge" :class="badgeClass(b.status)" x-text="b.status"></span>
                            </div>
                        </div>
                        <div style="text-align:end;">
                            <div style="font-weight:700; color:#1e40af;" x-text="formatPrice(b.total_price, b.currency)"></div>
                            <button
                                class="nzl-btn nzl-btn-ghost"
                                style="margin-top:0.5rem; color:#dc2626;"
                                @click="cancel(b)"
                                x-show="canCancel(b)">
                                <?php esc_html_e( 'Cancel', 'nozule' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Past bookings -->
    <template x-if="!loading && tab === 'past'">
        <div>
            <template x-if="past.length === 0">
                <div class="nzl-card"><div class="nzl-card-body" style="text-align:center; padding:2rem; color:#6b7280;"><?php esc_html_e( 'No past stays yet.', 'nozule' ); ?></div></div>
            </template>
            <template x-for="b in past" :key="b.id">
                <div class="nzl-card" style="margin-bottom:0.75rem;">
                    <div class="nzl-card-body" style="display:grid; grid-template-columns:1fr auto; gap:1rem;">
                        <div>
                            <div style="font-weight:600;" x-text="b.booking_number"></div>
                            <div style="color:#6b7280; font-size:0.875rem;">
                                <span x-text="b.check_in"></span> &rarr; <span x-text="b.check_out"></span>
                            </div>
                            <div style="margin-top:0.25rem;">
                                <span class="nzl-badge" :class="badgeClass(b.status)" x-text="b.status"></span>
                            </div>
                        </div>
                        <div style="text-align:end; font-weight:700;" x-text="formatPrice(b.total_price, b.currency)"></div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- Profile -->
    <template x-if="!loading && tab === 'profile'">
        <div class="nzl-card">
            <div class="nzl-card-header"><h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Profile', 'nozule' ); ?></h3></div>
            <div class="nzl-card-body">
                <template x-if="profileSaved">
                    <div class="nzl-alert nzl-alert-success" style="padding:0.75rem; background:#d1fae5; color:#065f46; border-radius:0.375rem; margin-bottom:1rem;">
                        <?php esc_html_e( 'Profile updated.', 'nozule' ); ?>
                    </div>
                </template>
                <form @submit.prevent="saveProfile">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div><label class="nzl-label"><?php esc_html_e( 'First Name', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.first_name"></div>
                        <div><label class="nzl-label"><?php esc_html_e( 'Last Name', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.last_name"></div>
                        <div><label class="nzl-label"><?php esc_html_e( 'Phone', 'nozule' ); ?></label><input type="tel" class="nzl-input" x-model="profile.phone"></div>
                        <div><label class="nzl-label"><?php esc_html_e( 'Nationality', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.nationality"></div>
                        <div><label class="nzl-label"><?php esc_html_e( 'City', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.city"></div>
                        <div><label class="nzl-label"><?php esc_html_e( 'Country', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.country"></div>
                        <div style="grid-column:1/-1;"><label class="nzl-label"><?php esc_html_e( 'Address', 'nozule' ); ?></label><input type="text" class="nzl-input" x-model="profile.address"></div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Preferred Language', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="profile.locale">
                                <option value="en">English</option>
                                <option value="ar">العربية</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="nzl-btn nzl-btn-primary" style="margin-top:1rem;" :disabled="savingProfile">
                        <span x-show="!savingProfile"><?php esc_html_e( 'Save Changes', 'nozule' ); ?></span>
                        <span x-show="savingProfile"><?php esc_html_e( 'Saving…', 'nozule' ); ?></span>
                    </button>
                </form>
            </div>
        </div>
    </template>
</div>
