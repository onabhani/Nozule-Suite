<?php
/**
 * Template: Reception Portal
 *
 * Standalone simplified interface for front desk staff.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'Reception Portal - Venezia Hotel', 'venezia-hotel' ); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/css/admin.css' ); ?>">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/js/core/api.js' ); ?>"></script>
    <script src="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/js/core/utils.js' ); ?>"></script>
    <script src="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/js/core/store.js' ); ?>"></script>
    <script src="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/js/admin/dashboard.js' ); ?>"></script>
    <script>
        window.VeneziaConfig = {
            nonce: '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>',
            apiBase: '<?php echo esc_js( rest_url( 'venezia/v1' ) ); ?>'
        };
    </script>
    <?php if ( is_rtl() ) : ?>
    <link rel="stylesheet" href="<?php echo esc_url( VHM_PLUGIN_URL . 'assets/css/rtl.css' ); ?>">
    <?php endif; ?>
</head>
<body style="background:#f1f5f9; margin:0; padding:1rem;">
    <?php include VHM_PLUGIN_DIR . 'templates/admin/dashboard.php'; ?>
</body>
</html>
