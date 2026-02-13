<?php

namespace Nozule\Modules\Notifications\Services;

use Nozule\Core\SettingsManager;

/**
 * Service for rendering notification templates with variable substitution.
 */
class TemplateService {

	private SettingsManager $settings;

	/**
	 * All supported template variable placeholders.
	 *
	 * @var string[]
	 */
	private const TEMPLATE_VARIABLES = [
		'{guest_name}',
		'{guest_first_name}',
		'{guest_last_name}',
		'{guest_email}',
		'{guest_phone}',
		'{booking_number}',
		'{check_in}',
		'{check_out}',
		'{nights}',
		'{adults}',
		'{children}',
		'{room_type}',
		'{room_number}',
		'{rate_plan}',
		'{total_price}',
		'{currency}',
		'{amount_paid}',
		'{balance_due}',
		'{hotel_name}',
		'{hotel_email}',
		'{hotel_phone}',
		'{hotel_address}',
		'{booking_status}',
		'{payment_status}',
		'{special_requests}',
		'{confirmation_url}',
		'{cancellation_url}',
		'{current_date}',
		'{current_year}',
	];

	/**
	 * Default templates keyed by notification type.
	 *
	 * @var array<string, array{ subject: string, body: string, body_html: string }>
	 */
	private const DEFAULT_TEMPLATES = [
		'booking_confirmation' => [
			'subject'   => 'Booking Confirmation - {booking_number}',
			'body'      => "Dear {guest_name},\n\nThank you for your booking at {hotel_name}.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Nights: {nights}\n- Room Type: {room_type}\n- Total: {currency} {total_price}\n\nIf you have any questions, please contact us at {hotel_email} or {hotel_phone}.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'booking_confirmed' => [
			'subject'   => 'Your Booking Has Been Confirmed - {booking_number}',
			'body'      => "Dear {guest_name},\n\nGreat news! Your booking at {hotel_name} has been confirmed.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Nights: {nights}\n- Room Type: {room_type}\n- Total: {currency} {total_price}\n\nWe look forward to welcoming you.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'booking_cancelled' => [
			'subject'   => 'Booking Cancellation - {booking_number}',
			'body'      => "Dear {guest_name},\n\nYour booking {booking_number} at {hotel_name} has been cancelled.\n\nBooking Details:\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Room Type: {room_type}\n\nIf you did not request this cancellation or have any questions, please contact us at {hotel_email} or {hotel_phone}.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'check_in_reminder' => [
			'subject'   => 'Check-in Reminder - {booking_number}',
			'body'      => "Dear {guest_name},\n\nThis is a friendly reminder that your check-in at {hotel_name} is tomorrow.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Room Type: {room_type}\n\nWe look forward to welcoming you!\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'check_out_reminder' => [
			'subject'   => 'Check-out Reminder - {booking_number}',
			'body'      => "Dear {guest_name},\n\nThis is a reminder that your check-out from {hotel_name} is tomorrow.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Check-out: {check_out}\n\nPlease ensure you check out by the designated time. If you need a late check-out, please contact the front desk.\n\nThank you for staying with us!\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'booking_reminder' => [
			'subject'   => 'Upcoming Stay Reminder - {booking_number}',
			'body'      => "Dear {guest_name},\n\nJust a reminder about your upcoming stay at {hotel_name}.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Nights: {nights}\n- Room Type: {room_type}\n\nIf you have any special requests, please let us know.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'payment_receipt' => [
			'subject'   => 'Payment Receipt - {booking_number}',
			'body'      => "Dear {guest_name},\n\nWe have received your payment for booking {booking_number}.\n\nPayment Details:\n- Amount Paid: {currency} {amount_paid}\n- Total: {currency} {total_price}\n- Balance Due: {currency} {balance_due}\n\nThank you for your payment.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
		'review_request' => [
			'subject'   => 'How Was Your Stay at {hotel_name}?',
			'body'      => "Dear {guest_name},\n\nThank you for staying with us at {hotel_name}. We hope you had a wonderful experience.\n\nWe would love to hear your feedback! Your review helps us improve our services for future guests.\n\nBooking Details:\n- Booking Number: {booking_number}\n- Stay: {check_in} to {check_out}\n\nThank you for choosing {hotel_name}.\n\nBest regards,\n{hotel_name}",
			'body_html' => '',
		],
	];

	public function __construct( SettingsManager $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Render a notification template with the given variables.
	 *
	 * @param string               $template_id Template identifier (notification type or custom ID).
	 * @param array<string, mixed> $vars        Variables to substitute into the template.
	 * @return array{ subject: string, body: string, body_html: string }
	 */
	public function render( string $template_id, array $vars = [] ): array {
		$template = $this->getTemplate( $template_id );

		// Merge in hotel-wide variables.
		$vars = array_merge( $this->getHotelVars(), $vars );

		// Add utility variables.
		$vars['current_date'] = wp_date( get_option( 'date_format', 'Y-m-d' ) );
		$vars['current_year'] = wp_date( 'Y' );

		// Calculate balance_due if not provided.
		if ( ! isset( $vars['balance_due'] ) && isset( $vars['total_price'], $vars['amount_paid'] ) ) {
			$vars['balance_due'] = number_format(
				(float) $vars['total_price'] - (float) $vars['amount_paid'],
				2,
				'.',
				''
			);
		}

		$subject   = $this->replaceVariables( $template['subject'], $vars );
		$body      = $this->replaceVariables( $template['body'], $vars );
		$body_html = ! empty( $template['body_html'] )
			? $this->replaceVariables( $template['body_html'], $vars )
			: $this->textToHtml( $body );

		/**
		 * Filter the rendered notification template.
		 *
		 * @param array  $rendered    The rendered template parts.
		 * @param string $template_id The template identifier.
		 * @param array  $vars        The template variables.
		 */
		$rendered = apply_filters( 'nozule/notifications/template_rendered', [
			'subject'   => $subject,
			'body'      => $body,
			'body_html' => $body_html,
		], $template_id, $vars );

		return $rendered;
	}

	/**
	 * Get the list of available templates.
	 *
	 * @return array<string, array{ subject: string, body: string, body_html: string }>
	 */
	public function getAvailableTemplates(): array {
		$templates = [];

		foreach ( self::DEFAULT_TEMPLATES as $type => $template ) {
			// Check for custom template overrides in settings.
			$custom_subject   = $this->settings->get( "notifications.template_{$type}_subject" );
			$custom_body      = $this->settings->get( "notifications.template_{$type}_body" );
			$custom_body_html = $this->settings->get( "notifications.template_{$type}_body_html" );

			$templates[ $type ] = [
				'subject'   => $custom_subject ?: $template['subject'],
				'body'      => $custom_body ?: $template['body'],
				'body_html' => $custom_body_html ?: $template['body_html'],
			];
		}

		/**
		 * Filter the list of available notification templates.
		 *
		 * @param array $templates Array of templates keyed by type.
		 */
		return apply_filters( 'nozule/notifications/available_templates', $templates );
	}

	/**
	 * Get the list of supported template variables and their descriptions.
	 *
	 * @return array<string, string>
	 */
	public function getAvailableVariables(): array {
		return [
			'{guest_name}'        => __( 'Full name of the guest', 'nozule' ),
			'{guest_first_name}'  => __( 'First name of the guest', 'nozule' ),
			'{guest_last_name}'   => __( 'Last name of the guest', 'nozule' ),
			'{guest_email}'       => __( 'Email address of the guest', 'nozule' ),
			'{guest_phone}'       => __( 'Phone number of the guest', 'nozule' ),
			'{booking_number}'    => __( 'Unique booking reference number', 'nozule' ),
			'{check_in}'          => __( 'Check-in date', 'nozule' ),
			'{check_out}'         => __( 'Check-out date', 'nozule' ),
			'{nights}'            => __( 'Number of nights', 'nozule' ),
			'{adults}'            => __( 'Number of adults', 'nozule' ),
			'{children}'          => __( 'Number of children', 'nozule' ),
			'{room_type}'         => __( 'Room type name', 'nozule' ),
			'{room_number}'       => __( 'Assigned room number', 'nozule' ),
			'{rate_plan}'         => __( 'Rate plan name', 'nozule' ),
			'{total_price}'       => __( 'Total booking price', 'nozule' ),
			'{currency}'          => __( 'Currency code', 'nozule' ),
			'{amount_paid}'       => __( 'Amount already paid', 'nozule' ),
			'{balance_due}'       => __( 'Remaining balance', 'nozule' ),
			'{hotel_name}'        => __( 'Hotel name', 'nozule' ),
			'{hotel_email}'       => __( 'Hotel email address', 'nozule' ),
			'{hotel_phone}'       => __( 'Hotel phone number', 'nozule' ),
			'{hotel_address}'     => __( 'Hotel address', 'nozule' ),
			'{booking_status}'    => __( 'Current booking status', 'nozule' ),
			'{payment_status}'    => __( 'Current payment status', 'nozule' ),
			'{special_requests}'  => __( 'Guest special requests', 'nozule' ),
			'{confirmation_url}'  => __( 'Booking confirmation URL', 'nozule' ),
			'{cancellation_url}'  => __( 'Booking cancellation URL', 'nozule' ),
			'{current_date}'      => __( 'Current date', 'nozule' ),
			'{current_year}'      => __( 'Current year', 'nozule' ),
		];
	}

	/**
	 * Get a template by its identifier.
	 *
	 * Checks for custom overrides in settings first, then falls back to defaults.
	 *
	 * @return array{ subject: string, body: string, body_html: string }
	 */
	private function getTemplate( string $template_id ): array {
		// Check for custom template in settings.
		$custom_subject   = $this->settings->get( "notifications.template_{$template_id}_subject" );
		$custom_body      = $this->settings->get( "notifications.template_{$template_id}_body" );
		$custom_body_html = $this->settings->get( "notifications.template_{$template_id}_body_html" );

		if ( $custom_subject || $custom_body ) {
			$default = self::DEFAULT_TEMPLATES[ $template_id ] ?? [
				'subject'   => '',
				'body'      => '',
				'body_html' => '',
			];

			return [
				'subject'   => $custom_subject ?: $default['subject'],
				'body'      => $custom_body ?: $default['body'],
				'body_html' => $custom_body_html ?: $default['body_html'],
			];
		}

		// Fall back to built-in default template.
		if ( isset( self::DEFAULT_TEMPLATES[ $template_id ] ) ) {
			return self::DEFAULT_TEMPLATES[ $template_id ];
		}

		// Unknown template type - return empty.
		return [
			'subject'   => '',
			'body'      => '',
			'body_html' => '',
		];
	}

	/**
	 * Replace placeholder variables in a template string.
	 *
	 * @param string               $template The template string with placeholders.
	 * @param array<string, mixed> $vars     Key-value pairs of variable names and values.
	 */
	private function replaceVariables( string $template, array $vars ): string {
		if ( empty( $template ) ) {
			return '';
		}

		$search  = [];
		$replace = [];

		foreach ( $vars as $key => $value ) {
			// Support both '{key}' and 'key' formats in the vars array.
			$placeholder = str_starts_with( $key, '{' ) ? $key : '{' . $key . '}';
			$search[]    = $placeholder;
			$replace[]   = (string) $value;
		}

		return str_replace( $search, $replace, $template );
	}

	/**
	 * Get hotel-wide template variables from settings.
	 *
	 * @return array<string, string>
	 */
	private function getHotelVars(): array {
		return [
			'hotel_name'    => $this->settings->get( 'hotel.name', get_bloginfo( 'name' ) ),
			'hotel_email'   => $this->settings->get( 'hotel.email', get_option( 'admin_email' ) ),
			'hotel_phone'   => $this->settings->get( 'hotel.phone', '' ),
			'hotel_address' => $this->settings->get( 'hotel.address', '' ),
		];
	}

	/**
	 * Convert plain-text content to a basic HTML representation.
	 *
	 * @param string $text Plain-text content.
	 * @return string HTML content wrapped in a minimal structure.
	 */
	private function textToHtml( string $text ): string {
		if ( empty( $text ) ) {
			return '';
		}

		$hotel_name = esc_html( $this->settings->get( 'hotel.name', get_bloginfo( 'name' ) ) );
		$body       = nl2br( esc_html( $text ) );

		return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
.container { max-width: 600px; margin: 0 auto; padding: 20px; }
.header { background-color: #2c3e50; color: #ffffff; padding: 20px; text-align: center; }
.content { padding: 20px; background-color: #ffffff; }
.footer { padding: 20px; text-align: center; font-size: 12px; color: #999; }
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>{$hotel_name}</h1>
</div>
<div class="content">
{$body}
</div>
<div class="footer">
&copy; {$hotel_name}
</div>
</div>
</body>
</html>
HTML;
	}
}
