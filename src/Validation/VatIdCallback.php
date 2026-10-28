<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Validation;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\CallbackValidator;

/**
 * Callback constraint that declares the plugin's error codes, so Shopware can
 * serialize the violation (Constraint::getErrorName() throws for unknown codes
 * on Symfony 6.x / Shopware 6.6).
 */
class VatIdCallback extends Callback
{
    protected const ERROR_NAMES = [
        'WSYSTEM_VAT_FORMAT' => 'WSYSTEM_VAT_FORMAT',
        'WSYSTEM_VAT_COUNTRY_MISMATCH' => 'WSYSTEM_VAT_COUNTRY_MISMATCH',
        'WSYSTEM_VAT_INVALID' => 'WSYSTEM_VAT_INVALID',
        'WSYSTEM_VAT_UNAVAILABLE' => 'WSYSTEM_VAT_UNAVAILABLE',
    ];

    /** @deprecated kept for Symfony < 6.1 style lookups */
    protected static $errorNames = self::ERROR_NAMES;

    public function validatedBy(): string
    {
        return CallbackValidator::class;
    }
}
