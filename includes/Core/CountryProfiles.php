<?php

namespace Nozule\Core;

/**
 * Country-specific configuration profiles.
 *
 * Pre-configured defaults for currency, taxes, timezone,
 * and feature flags based on the hotel's operating country.
 */
class CountryProfiles {

    /**
     * Get all supported country profiles.
     *
     * @return array<string, array>
     */
    public static function getAll(): array {
        return [
            'SY' => self::syria(),
            'SA' => self::saudiArabia(),
        ];
    }

    /**
     * Get a single country profile by ISO code.
     *
     * @param string $code ISO 3166-1 alpha-2 country code.
     * @return array|null
     */
    public static function get( string $code ): ?array {
        $profiles = self::getAll();
        return $profiles[ strtoupper( $code ) ] ?? null;
    }

    /**
     * Get only the list of supported countries (for dropdowns).
     *
     * @return array<string, array{label: string, label_ar: string}>
     */
    public static function getCountryList(): array {
        $list = [];
        foreach ( self::getAll() as $code => $profile ) {
            $list[ $code ] = [
                'label'    => $profile['label'],
                'label_ar' => $profile['label_ar'],
            ];
        }
        return $list;
    }

    /**
     * Syria (SY) profile.
     */
    private static function syria(): array {
        return [
            'code'     => 'SY',
            'label'    => 'Syria',
            'label_ar' => 'سوريا',
            'currency' => [
                'code'     => 'SYP',
                'symbol'   => 'ل.س',
                'position' => 'after',
            ],
            'timezone' => 'Asia/Damascus',
            'features' => [
                'guest_type_pricing' => true,   // Syrian / Non-Syrian rate differentiation
                'multi_property'     => false,  // NZL-019 — off by default, toggled by sysadmin
                'zatca'              => false,
                'shomos'             => false,
            ],
            'taxes' => [
                [
                    'name'       => 'Tourism Tax',
                    'name_ar'    => 'ضريبة السياحة',
                    'rate'       => 10,
                    'type'       => 'percentage',
                    'applies_to' => 'room_charge',
                    'sort_order' => 1,
                ],
                [
                    'name'       => 'City Tax',
                    'name_ar'    => 'ضريبة المدينة',
                    'rate'       => 5,
                    'type'       => 'percentage',
                    'applies_to' => 'all',
                    'sort_order' => 2,
                ],
            ],
        ];
    }

    /**
     * Saudi Arabia (SA) profile.
     */
    private static function saudiArabia(): array {
        return [
            'code'     => 'SA',
            'label'    => 'Saudi Arabia',
            'label_ar' => 'المملكة العربية السعودية',
            'currency' => [
                'code'     => 'SAR',
                'symbol'   => '﷼',
                'position' => 'after',
            ],
            'timezone' => 'Asia/Riyadh',
            'features' => [
                'guest_type_pricing' => false,  // Unified pricing
                'multi_property'     => false,  // NZL-019 — off by default, toggled by sysadmin
                'zatca'              => true,   // Coming soon — ZATCA e-invoicing
                'shomos'             => true,   // Coming soon — Shomos tourism platform
            ],
            'taxes' => [
                [
                    'name'       => 'VAT',
                    'name_ar'    => 'ضريبة القيمة المضافة',
                    'rate'       => 15,
                    'type'       => 'percentage',
                    'applies_to' => 'all',
                    'sort_order' => 1,
                ],
            ],
        ];
    }
}
