<?php
/**
 * Template: Public Guest Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_user_logged_in() ) {
    $account_page_id  = (int) get_option( 'nzl_page_my_account', 0 );
    $default_account  = $account_page_id > 0 ? (string) get_permalink( $account_page_id ) : home_url( '/my-account/' );
    $account_url      = esc_url( (string) apply_filters( 'nozule/portal/my_account_url', $default_account ) );
    printf(
        '<div class="nzl-card"><div class="nzl-card-body">%s <a href="%s">%s</a></div></div>',
        esc_html__( "You're already signed in.", 'nozule' ),
        $account_url,
        esc_html__( 'Go to my account', 'nozule' )
    );
    return;
}
?>
<div class="nzl-widget" x-data="guestRegister">
    <div class="nzl-card" style="max-width:480px; margin:0 auto;">
        <div class="nzl-card-header">
            <h3 style="margin:0; font-size:1rem;"><?php esc_html_e( 'Create your account', 'nozule' ); ?></h3>
        </div>
        <div class="nzl-card-body">
            <template x-if="success">
                <div class="nzl-alert nzl-alert-success" style="padding:1rem; background:#d1fae5; color:#065f46; border-radius:0.375rem;">
                    <?php esc_html_e( 'Your account has been created. Redirecting…', 'nozule' ); ?>
                </div>
            </template>

            <template x-if="!success">
                <form @submit.prevent="submit">
                    <template x-if="error">
                        <div class="nzl-alert" style="padding:0.75rem; background:#fee2e2; color:#991b1b; border-radius:0.375rem; margin-bottom:1rem;" x-text="error"></div>
                    </template>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'First Name', 'nozule' ); ?> *</label>
                            <input type="text" class="nzl-input" x-model="form.first_name" required>
                        </div>
                        <div>
                            <label class="nzl-label"><?php esc_html_e( 'Last Name', 'nozule' ); ?> *</label>
                            <input type="text" class="nzl-input" x-model="form.last_name" required>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="nzl-label"><?php esc_html_e( 'Email', 'nozule' ); ?> *</label>
                            <input type="email" class="nzl-input" x-model="form.email" required>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="nzl-label"><?php esc_html_e( 'Phone', 'nozule' ); ?></label>
                            <input type="tel" class="nzl-input" x-model="form.phone">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="nzl-label"><?php esc_html_e( 'Password', 'nozule' ); ?> *</label>
                            <input type="password" class="nzl-input" x-model="form.password" minlength="8" required>
                            <small style="color:#6b7280;"><?php esc_html_e( 'Minimum 8 characters.', 'nozule' ); ?></small>
                        </div>
                    </div>

                    <button type="submit" class="nzl-btn nzl-btn-primary" style="margin-top:1.25rem; width:100%;" :disabled="submitting">
                        <span x-show="!submitting"><?php esc_html_e( 'Create Account', 'nozule' ); ?></span>
                        <span x-show="submitting"><?php esc_html_e( 'Creating…', 'nozule' ); ?></span>
                    </button>

                    <p style="text-align:center; margin-top:1rem; font-size:0.875rem; color:#6b7280;">
                        <?php
                        $login_url = esc_url( (string) apply_filters( 'nozule/portal/login_url', wp_login_url() ) );
                        echo sprintf(
                            /* translators: %s: link to login page */
                            esc_html__( 'Already have an account? %s', 'nozule' ),
                            '<a href="' . $login_url . '">' . esc_html__( 'Sign in', 'nozule' ) . '</a>'
                        );
                        ?>
                    </p>
                </form>
            </template>
        </div>
    </div>
</div>
