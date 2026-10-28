<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use WSystem\EuVatCheck\Service\VatApiClient;
use WSystem\EuVatCheck\Service\VatDecision;

/**
 * Adds a live VIES check to the VAT ID field on registration and profile update.
 * Shopware already checks the format (optional regex per country); this checks that
 * the number is really registered, so only confirmed VAT IDs reach Shopware's
 * "Tax-free for companies" (reverse charge) price.
 */
class VatIdValidationSubscriber implements EventSubscriberInterface
{
    public const CONFIG = 'WSystemEuVatCheck.config.';

    /** @var array<string,string> error code => message */
    public const MESSAGES = [
        VatDecision::ERR_FORMAT => 'Please enter an EU VAT ID starting with the country code (for example DE123456789).',
        VatDecision::ERR_COUNTRY => 'The VAT ID country does not match your billing country.',
        VatDecision::ERR_INVALID => 'This VAT ID is not valid according to the EU VIES database. Please check it.',
        VatDecision::ERR_UNAVAILABLE => 'The EU VAT check (VIES) is temporarily unavailable. Please try again in a few minutes.',
    ];

    /**
     * @param EntityRepository<\Shopware\Core\System\Country\CountryCollection>|null $countryRepository
     */
    public function __construct(
        private readonly VatApiClient $client,
        private readonly SystemConfigService $config,
        private readonly ?EntityRepository $countryRepository = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.customer.create' => 'onBuildValidation',
            'framework.validation.customer.profile.update' => 'onBuildValidation',
        ];
    }

    public function onBuildValidation(BuildValidationEvent $event): void
    {
        $data = $event->getData();
        $billingCountry = $this->billingIso($data, $event->getContext());
        $event->getDefinition()->add('vatIds', new Callback(
            callback: function (mixed $value, ExecutionContextInterface $ctx) use ($billingCountry): void {
                foreach ($this->violations($value, $billingCountry) as $code) {
                    $ctx->buildViolation(self::MESSAGES[$code])->setCode($code)->addViolation();
                }
            }
        ));
    }

    /**
     * @return list<string> error codes for the given vatIds value
     */
    public function violations(mixed $value, string $billingCountry): array
    {
        if ($value instanceof DataBag) {
            $value = $value->all();
        }
        if (!\is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }
        $onInvalid = (string) ($this->config->get(self::CONFIG . 'onInvalid') ?: VatDecision::ON_INVALID_BLOCK);
        $onUnavailable = (string) ($this->config->get(self::CONFIG . 'onUnavailable') ?: VatDecision::ON_UNAVAILABLE_ALLOW);
        $apiKey = (string) ($this->config->get(self::CONFIG . 'apiKey') ?? '');
        $requester = (string) ($this->config->get(self::CONFIG . 'requesterVatId') ?? '');
        $codes = [];
        foreach ($value as $vat) {
            if (!\is_string($vat) || trim($vat) === '') {
                continue;
            }
            $pre = VatDecision::evaluate($vat, $billingCountry, null, $onInvalid, $onUnavailable);
            if ($pre !== '') {
                $codes[] = $pre;
                continue;
            }
            $code = VatDecision::evaluate($vat, $billingCountry, $this->client->verify($vat, $apiKey, $requester), $onInvalid, $onUnavailable);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function billingIso(DataBag $data, Context $context): string
    {
        $billing = $data->get('billingAddress');
        $countryId = $billing instanceof DataBag ? $billing->get('countryId') : null;
        if (!\is_string($countryId) || $countryId === '' || $this->countryRepository === null) {
            return '';
        }
        try {
            $country = $this->countryRepository->search(new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria([$countryId]), $context)->first();
        } catch (\Throwable) {
            return '';
        }

        return $country instanceof CountryEntity ? (string) $country->getIso() : '';
    }
}
