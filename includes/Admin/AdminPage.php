<?php

namespace Nozule\Admin;

/**
 * Generic admin page renderer.
 *
 * Replaces 28 identical per-page classes with a single reusable class.
 * Each instance holds a template name and one or more required capabilities.
 */
class AdminPage {

	private string $template;

	/**
	 * @var string|string[] One capability string, or an array where ANY match grants access.
	 */
	private $capability;

	/**
	 * @param string          $template   Template filename (without .php) under templates/admin/.
	 * @param string|string[] $capability Single capability or array of capabilities (OR logic).
	 */
	public function __construct( string $template, $capability = 'nzl_admin' ) {
		$this->template   = $template;
		$this->capability = $capability;
	}

	/**
	 * Render the admin page.
	 *
	 * When multiple capabilities are provided, the user must have at least one.
	 */
	public function render(): void {
		if ( is_array( $this->capability ) ) {
			$allowed = false;
			foreach ( $this->capability as $cap ) {
				if ( current_user_can( $cap ) ) {
					$allowed = true;
					break;
				}
			}
			if ( ! $allowed ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
			}
		} elseif ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nozule' ) );
		}

		include NZL_PLUGIN_DIR . 'templates/admin/' . $this->template . '.php';
	}
}
