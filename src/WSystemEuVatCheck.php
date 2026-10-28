<?php declare(strict_types=1);

namespace WSystem\EuVatCheck;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class WSystemEuVatCheck extends Plugin
{
    public const FIELD_SET = 'wsystem_vat';

    public function install(InstallContext $installContext): void
    {
        $this->upsertCustomFields($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }
        /** @var EntityRepository $repo */
        $repo = $this->container->get('custom_field_set.repository');
        $ids = $repo->searchIds((new Criteria())->addFilter(new EqualsFilter('name', self::FIELD_SET)), $uninstallContext->getContext())->getIds();
        if ($ids !== []) {
            $repo->delete(array_map(static fn ($id) => ['id' => $id], $ids), $uninstallContext->getContext());
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function customFieldSet(): array
    {
        $field = static fn (string $name, string $en, string $de, int $pos) => [
            'name' => $name,
            'type' => CustomFieldTypes::TEXT,
            'config' => ['label' => ['en-GB' => $en, 'de-DE' => $de], 'customFieldPosition' => $pos],
        ];

        return [
            'name' => self::FIELD_SET,
            'config' => ['label' => ['en-GB' => 'EU VAT check (VIES)', 'de-DE' => 'EU-USt-IdNr.-Prüfung (VIES)']],
            'relations' => [['entityName' => 'order']],
            'customFields' => [
                $field('wsystem_vat_id', 'VAT ID', 'USt-IdNr.', 1),
                $field('wsystem_vat_status', 'VIES status', 'VIES-Status', 2),
                $field('wsystem_vat_consultation_number', 'VIES consultation number', 'VIES-Abfragenummer', 3),
                $field('wsystem_vat_company_name', 'Name in VIES', 'Name laut VIES', 4),
                $field('wsystem_vat_checked_at', 'Checked at', 'Geprüft am', 5),
                $field('wsystem_vat_reverse_charge', 'Reverse charge (tax-free)', 'Reverse Charge (steuerfrei)', 6),
            ],
        ];
    }

    private function upsertCustomFields(Context $context): void
    {
        /** @var EntityRepository $repo */
        $repo = $this->container->get('custom_field_set.repository');
        $exists = $repo->searchIds((new Criteria())->addFilter(new EqualsFilter('name', self::FIELD_SET)), $context)->getTotal() > 0;
        if (!$exists) {
            $repo->create([self::customFieldSet()], $context);
        }
    }
}
