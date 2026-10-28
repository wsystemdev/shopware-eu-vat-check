<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Service;

/**
 * Pure helpers for EU VAT numbers (no Shopware dependency, unit tested offline).
 */
final class VatNumber
{
    /** VIES prefix => ISO 3166-1 alpha-2 country code used by Shopware. */
    public const PREFIXES = [
        'AT' => 'AT', 'BE' => 'BE', 'BG' => 'BG', 'CY' => 'CY', 'CZ' => 'CZ', 'DE' => 'DE',
        'DK' => 'DK', 'EE' => 'EE', 'EL' => 'GR', 'ES' => 'ES', 'FI' => 'FI', 'FR' => 'FR',
        'HR' => 'HR', 'HU' => 'HU', 'IE' => 'IE', 'IT' => 'IT', 'LT' => 'LT', 'LU' => 'LU',
        'LV' => 'LV', 'MT' => 'MT', 'NL' => 'NL', 'PL' => 'PL', 'PT' => 'PT', 'RO' => 'RO',
        'SE' => 'SE', 'SI' => 'SI', 'SK' => 'SK', 'XI' => 'GB',
    ];

    /** Uppercase, strip spaces, dots and dashes; GR prefix becomes EL (VIES uses EL). */
    public static function normalize(string $vat): string
    {
        $vat = strtoupper((string) preg_replace('/[\s.\-]/', '', $vat));
        if (str_starts_with($vat, 'GR')) {
            $vat = 'EL' . substr($vat, 2);
        }

        return $vat;
    }

    /** ISO country for a normalised VAT number, or '' if the prefix is not EU/XI. */
    public static function countryOf(string $vat): string
    {
        return self::PREFIXES[substr($vat, 0, 2)] ?? '';
    }

    public static function looksValid(string $vat): bool
    {
        return (bool) preg_match('/^[A-Z]{2}[0-9A-Z+*]{2,13}$/', $vat) && self::countryOf($vat) !== '';
    }
}
