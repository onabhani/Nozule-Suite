<?php
/**
 * Email Template: Booking Confirmation
 *
 * Available variables:
 * @var string $guest_name
 * @var string $booking_number
 * @var string $room_type_name
 * @var string $check_in
 * @var string $check_out
 * @var int    $nights
 * @var int    $adults
 * @var int    $children
 * @var string $total_price
 * @var string $hotel_name
 * @var string $hotel_email
 * @var string $hotel_phone
 * @var string $hotel_address
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
    <title><?php esc_html_e( 'Booking Confirmation', 'nozule' ); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:2rem 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#1e40af; padding:2rem; text-align:center;">
                            <h1 style="color:#ffffff; margin:0; font-size:1.5rem;"><?php echo esc_html( $hotel_name ?? 'Nozule Hotel' ); ?></h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:2rem;">
                            <h2 style="color:#1e40af; margin:0 0 1rem 0; font-size:1.25rem;"><?php esc_html_e( 'Booking Confirmed!', 'nozule' ); ?></h2>

                            <p style="color:#374151; margin:0 0 1.5rem 0;">
                                <?php
                                printf(
                                    /* translators: %s: guest name */
                                    esc_html__( 'Dear %s,', 'nozule' ),
                                    esc_html( $guest_name ?? '' )
                                );
                                ?>
                                <br>
                                <?php esc_html_e( 'Thank you for your reservation. Here are your booking details:', 'nozule' ); ?>
                            </p>

                            <!-- Booking Details Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:1.5rem;">
                                <tr>
                                    <td style="padding:1.5rem;">
                                        <table width="100%" cellpadding="4" cellspacing="0">
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem; width:40%;"><?php esc_html_e( 'Booking Number', 'nozule' ); ?></td>
                                                <td style="color:#1e293b; font-weight:600; font-size:1rem;"><?php echo esc_html( $booking_number ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Room Type', 'nozule' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $room_type_name ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Check-in', 'nozule' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $check_in ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Check-out', 'nozule' ); ?></td>
                                                <td style="color:#1e293b;"><?php echo esc_html( $check_out ?? '' ); ?></td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Duration', 'nozule' ); ?></td>
                                                <td style="color:#1e293b;">
                                                    <?php
                                                    printf(
                                                        /* translators: %d: number of nights */
                                                        esc_html( _n( '%d night', '%d nights', $nights ?? 1, 'nozule' ) ),
                                                        (int) ( $nights ?? 1 )
                                                    );
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem;"><?php esc_html_e( 'Guests', 'nozule' ); ?></td>
                                                <td style="color:#1e293b;">
                                                    <?php
                                                    printf(
                                                        /* translators: 1: adults count, 2: children count */
                                                        esc_html__( '%1$d adults, %2$d children', 'nozule' ),
                                                        (int) ( $adults ?? 1 ),
                                                        (int) ( $children ?? 0 )
                                                    );
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color:#64748b; font-size:0.875rem; padding-top:0.75rem; border-top:1px solid #e2e8f0;"><?php esc_html_e( 'Total', 'nozule' ); ?></td>
                                                <td style="color:#1e40af; font-weight:700; font-size:1.125rem; padding-top:0.75rem; border-top:1px solid #e2e8f0;"><?php echo esc_html( $total_price ?? '' ); ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="color:#374151; font-size:0.875rem;">
                                <?php esc_html_e( 'If you have any questions or need to modify your reservation, please contact us:', 'nozule' ); ?>
                            </p>

                            <?php if ( ! empty( $hotel_email ) ) : ?>
                            <p style="color:#374151; font-size:0.875rem; margin:0.25rem 0;">
                                <?php esc_html_e( 'Email', 'nozule' ); ?>: <?php echo esc_html( $hotel_email ); ?>
                            </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $hotel_phone ) ) : ?>
                            <p style="color:#374151; font-size:0.875rem; margin:0.25rem 0;">
                                <?php esc_html_e( 'Phone', 'nozule' ); ?>: <?php echo esc_html( $hotel_phone ); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8fafc; padding:1.5rem; text-align:center; border-top:1px solid #e2e8f0;">
                            <p style="color:#94a3b8; font-size:0.75rem; margin:0;">
                                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $hotel_name ?? 'Nozule Hotel' ); ?>
                                <?php if ( ! empty( $hotel_address ) ) : ?>
                                <br><?php echo esc_html( $hotel_address ); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
