<?php
/**
 * Email Template: Booking Cancelled
 *
 * Available variables:
 * @var string $guest_name
 * @var string $booking_number
 * @var string $room_type_name
 * @var string $check_in
 * @var string $check_out
 * @var string $cancel_reason
 * @var string $hotel_name
 * @var string $hotel_email
 * @var string $hotel_phone
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php esc_html_e( 'Booking Cancellation', 'venezia-hotel' ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:2rem 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#dc2626; padding:2rem; text-align:center;">
                            <h1 style="color:#ffffff; margin:0; font-size:1.5rem;"><?php echo esc_html( $hotel_name ?? 'Venezia Hotel' ); ?></h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:2rem;">
                            <h2 style="color:#dc2626; margin:0 0 1rem 0; font-size:1.25rem;"><?php esc_html_e( 'Booking Cancelled', 'venezia-hotel' ); ?></h2>

                            <p style="color:#374151; margin:0 0 1.5rem 0;">
                                <?php
                                printf(
                                    /* translators: %s: guest name */
                                    esc_html__( 'Dear %s,', 'venezia-hotel' ),
                                    esc_html( $guest_name ?? '' )
                                );
                                ?>
                                <br>
                                <?php esc_html_e( 'Your reservation has been cancelled. Here are the details:', 'venezia-hotel' ); ?>
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin-bottom:1.5rem;">
                                <tr>
                                    <td style="padding:1.5rem;">
                                        <table width="100%" cellpadding="4" cellspacing="0">
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem; width:40%;"><?php esc_html_e( 'Booking Number', 'venezia-hotel' ); ?></td>
                                                <td style="color:#1e293b; font-weight:600;"><?php echo esc_html( $booking_number ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Room Type', 'venezia-hotel' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $room_type_name ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Check-in', 'venezia-hotel' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $check_in ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Check-out', 'venezia-hotel' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $check_out ?? '' ); ?></td>
                                            </tr>
                                            <?php if ( ! empty( $cancel_reason ) ) : ?>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Reason', 'venezia-hotel' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $cancel_reason ); ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#374151; font-size:0.875rem;">
                                <?php esc_html_e( 'If you did not request this cancellation or have any questions, please contact us immediately.', 'venezia-hotel' ); ?>
                            </p>

                            <?php if ( ! empty( $hotel_email ) ) : ?>
                            <p style="color:#374151; font-size:0.875rem; margin:0.25rem 0;">
                                <?php esc_html_e( 'Email', 'venezia-hotel' ); ?>: <?php echo esc_html( $hotel_email ); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $hotel_phone ) ) : ?>
                            <p style="color:#374151; font-size:0.875rem; margin:0.25rem 0;">
                                <?php esc_html_e( 'Phone', 'venezia-hotel' ); ?>: <?php echo esc_html( $hotel_phone ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8fafc; padding:1.5rem; text-align:center; border-top:1px solid #e2e8f0;">
                            <p style="color:#94a3b8; font-size:0.75rem; margin:0;">
                                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $hotel_name ?? 'Venezia Hotel' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
