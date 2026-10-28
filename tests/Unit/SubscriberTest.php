<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Validation;
use WSystem\EuVatCheck\Service\VatApiClient;
use WSystem\EuVatCheck\Service\VatDecision;
use WSystem\EuVatCheck\Subscriber\VatIdValidationSubscriber;

final class SubscriberTest extends TestCase
{
    private function sub(FakeTransport $t, array $cfg = []): VatIdValidationSubscriber
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (string $k) => $cfg[substr($k, \strlen(VatIdValidationSubscriber::CONFIG))] ?? null);

        return new VatIdValidationSubscriber(new VatApiClient($t), $config);
    }

    public function testEvents(): void
    {
        $events = VatIdValidationSubscriber::getSubscribedEvents();
        self::assertArrayHasKey('framework.validation.customer.create', $events);
        self::assertArrayHasKey('framework.validation.customer.profile.update', $events);
        // Event names come from the definition names in Shopware core.
        $e = new BuildValidationEvent(new DataValidationDefinition('customer.create'), new DataBag(), Context::createDefaultContext());
        self::assertSame('framework.validation.customer.create', $e->getName());
    }

    public function testInvalidBlocksByDefault(): void
    {
        $s = $this->sub(new FakeTransport(200, '{"status":"invalid","valid":false}'));
        self::assertSame([VatDecision::ERR_INVALID], $s->violations(['DE123456789'], 'DE'));
        self::assertSame([], $this->sub(new FakeTransport(200, '{"status":"invalid","valid":false}'), ['onInvalid' => 'allow'])->violations(['DE123456789'], 'DE'));
    }

    public function testValidPassesAndEmptyIgnored(): void
    {
        $t = new FakeTransport(200, '{"status":"valid","valid":true}');
        self::assertSame([], $this->sub($t, ['apiKey' => 'k1'])->violations(new DataBag(['DE123456789', '']), 'DE'));
        self::assertSame('k1', $t->calls[0]['headers']['X-API-Key']);
        self::assertSame([], $this->sub(new FakeTransport())->violations(null, 'DE'));
    }

    public function testCountryMismatchNoNetwork(): void
    {
        $t = new FakeTransport();
        self::assertSame([VatDecision::ERR_COUNTRY], $this->sub($t)->violations(['FR12345678901'], 'DE'));
        self::assertCount(0, $t->calls);
    }

    public function testUnavailableAllowedByDefault(): void
    {
        self::assertSame([], $this->sub(new FakeTransport(503, ''))->violations(['DE123456789'], ''));
        self::assertSame([VatDecision::ERR_UNAVAILABLE], $this->sub(new FakeTransport(503, ''), ['onUnavailable' => 'block'])->violations(['DE123456789'], ''));
    }

    public function testCallbackConstraintProducesViolation(): void
    {
        $s = $this->sub(new FakeTransport(200, '{"status":"invalid","valid":false}'));
        $def = new DataValidationDefinition('customer.create');
        $s->onBuildValidation(new BuildValidationEvent($def, new DataBag(), Context::createDefaultContext()));
        $constraints = $def->getProperties()['vatIds'];
        self::assertInstanceOf(Callback::class, $constraints[0]);
        $violations = Validation::createValidator()->validate(['DE123456789'], $constraints);
        self::assertCount(1, $violations);
        self::assertSame(VatDecision::ERR_INVALID, $violations[0]->getCode());
    }
}
