<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use WSystem\EuVatCheck\Service\VatApiClient;
use WSystem\EuVatCheck\Service\VatNumber;

/**
 * Stores the VIES evidence (status, consultation number, company name, date)
 * in the order's custom fields when a company customer with a VAT ID orders.
 */
class OrderVatEvidenceSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<\Shopware\Core\Checkout\Order\OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly VatApiClient $client,
        private readonly SystemConfigService $config,
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckoutOrderPlacedEvent::class => 'onOrderPlaced'];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $vatIds = $order->getOrderCustomer()?->getVatIds() ?? [];
        if ($vatIds === []) {
            $vatIds = $event->getSalesChannelContext()->getCustomer()?->getVatIds() ?? [];
        }
        $vatIds = array_values(array_filter($vatIds, static fn ($v) => \is_string($v) && trim($v) !== ''));
        if ($vatIds === []) {
            return;
        }
        $fields = self::evidence(
            $vatIds[0],
            $this->client->verify(
                $vatIds[0],
                (string) ($this->config->get(VatIdValidationSubscriber::CONFIG . 'apiKey', $event->getSalesChannelId()) ?? ''),
                (string) ($this->config->get(VatIdValidationSubscriber::CONFIG . 'requesterVatId', $event->getSalesChannelId()) ?? ''),
            ),
            $order->getTaxStatus() === 'tax-free',
        );
        $this->orderRepository->update([[
            'id' => $order->getId(),
            'customFields' => array_merge($order->getCustomFields() ?? [], $fields),
        ]], $event->getContext());
    }

    /**
     * @param array{status:string,consultation_number:string,name:string,request_date:string} $r
     *
     * @return array<string,string>
     */
    public static function evidence(string $vat, array $r, bool $taxFree): array
    {
        return [
            'wsystem_vat_id' => VatNumber::normalize($vat),
            'wsystem_vat_status' => $r['status'],
            'wsystem_vat_consultation_number' => $r['consultation_number'],
            'wsystem_vat_company_name' => $r['name'],
            'wsystem_vat_checked_at' => $r['request_date'] !== '' ? $r['request_date'] : gmdate('c'),
            'wsystem_vat_reverse_charge' => $taxFree ? 'yes' : 'no',
        ];
    }
}
