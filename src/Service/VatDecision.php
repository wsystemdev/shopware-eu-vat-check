<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Service;

/**
 * Decides whether a VAT ID entered by a customer is accepted.
 * Shopware itself applies the tax-free (reverse charge) price once a company
 * customer has a VAT ID and the country has "Tax-free for companies" on;
 * this class makes sure only VAT IDs that VIES confirms get that far.
 */
final class VatDecision
{
    public const ON_INVALID_BLOCK = 'block';
    public const ON_INVALID_ALLOW = 'allow';
    public const ON_UNAVAILABLE_ALLOW = 'allow';
    public const ON_UNAVAILABLE_BLOCK = 'block';

    /** Error codes, translated in the storefront snippets. */
    public const ERR_FORMAT = 'WSYSTEM_VAT_FORMAT';
    public const ERR_COUNTRY = 'WSYSTEM_VAT_COUNTRY_MISMATCH';
    public const ERR_INVALID = 'WSYSTEM_VAT_INVALID';
    public const ERR_UNAVAILABLE = 'WSYSTEM_VAT_UNAVAILABLE';

    /**
     * @param array{status:string,valid:bool|null}|null $result VIES result (null when not checked yet)
     *
     * @return string error code, or '' when accepted
     */
    public static function evaluate(string $vat, string $billingCountry, ?array $result, string $onInvalid, string $onUnavailable): string
    {
        $vat = VatNumber::normalize($vat);
        if ($vat === '') {
            return '';
        }
        $vatCountry = VatNumber::countryOf($vat);
        if ($vatCountry === '' || !VatNumber::looksValid($vat)) {
            return self::ERR_FORMAT;
        }
        if ($billingCountry !== '' && strtoupper($billingCountry) !== $vatCountry) {
            return self::ERR_COUNTRY;
        }
        if ($result === null) {
            return '';
        }
        if ($result['valid'] === true) {
            return '';
        }
        if ($result['valid'] === false) {
            return $onInvalid === self::ON_INVALID_ALLOW ? '' : self::ERR_INVALID;
        }

        return $onUnavailable === self::ON_UNAVAILABLE_BLOCK ? self::ERR_UNAVAILABLE : '';
    }
}
