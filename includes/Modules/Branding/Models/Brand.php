<?php

namespace Nozule\Modules\Branding\Models;

use Nozule\Core\BaseModel;

/**
 * Brand model representing a white-label brand configuration.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $name_ar
 * @property string|null $logo_url
 * @property string|null $favicon_url
 * @property string      $primary_color
 * @property string      $secondary_color
 * @property string      $accent_color
 * @property string      $text_color
 * @property string|null $custom_css
 * @property string|null $email_header_html
 * @property string|null $email_footer_html
 * @property bool        $is_default
 * @property bool        $is_active
 * @property string      $created_at
 * @property string      $updated_at
 */
class Brand extends BaseModel {

	/**
	 * Fields that should be cast to integers.
	 *
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
	];

	/**
	 * Fields that should be cast to booleans.
	 *
	 * @var string[]
	 */
	protected static array $boolFields = [
		'is_default',
		'is_active',
	];

	/**
	 * Default color values.
	 */
	public const DEFAULT_PRIMARY_COLOR   = '#1e40af';
	public const DEFAULT_SECONDARY_COLOR = '#3b82f6';
	public const DEFAULT_ACCENT_COLOR    = '#f59e0b';
	public const DEFAULT_TEXT_COLOR      = '#1e293b';

	/**
	 * Create from a database row with type casting.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		foreach ( static::$boolFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (bool) (int) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this brand is the default.
	 */
	public function isDefault(): bool {
		return (bool) $this->is_default;
	}

	/**
	 * Check whether this brand is active.
	 */
	public function isActive(): bool {
		return (bool) $this->is_active;
	}

	/**
	 * Get CSS custom properties string from this brand's colors.
	 */
	public function getCSSVariables(): string {
		$vars = [];
		$vars[] = '--nzl-brand-primary: ' . ( $this->primary_color ?: self::DEFAULT_PRIMARY_COLOR );
		$vars[] = '--nzl-brand-secondary: ' . ( $this->secondary_color ?: self::DEFAULT_SECONDARY_COLOR );
		$vars[] = '--nzl-brand-accent: ' . ( $this->accent_color ?: self::DEFAULT_ACCENT_COLOR );
		$vars[] = '--nzl-brand-text: ' . ( $this->text_color ?: self::DEFAULT_TEXT_COLOR );

		return implode( '; ', $vars ) . ';';
	}

	/**
	 * Get brand data suitable for email templates.
	 */
	public function toEmailArray(): array {
		return [
			'name'              => $this->name,
			'name_ar'           => $this->name_ar,
			'logo_url'          => $this->logo_url,
			'primary_color'     => $this->primary_color ?: self::DEFAULT_PRIMARY_COLOR,
			'secondary_color'   => $this->secondary_color ?: self::DEFAULT_SECONDARY_COLOR,
			'accent_color'      => $this->accent_color ?: self::DEFAULT_ACCENT_COLOR,
			'text_color'        => $this->text_color ?: self::DEFAULT_TEXT_COLOR,
			'email_header_html' => $this->email_header_html ?: '',
			'email_footer_html' => $this->email_footer_html ?: '',
		];
	}
}
