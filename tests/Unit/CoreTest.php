<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WSystem\EuVatCheck\Service\VatApiClient;
use WSystem\EuVatCheck\Service\VatDecision;
use WSystem\EuVatCheck\Service\VatNumber;
use WSystem\EuVatCheck\Subscriber\OrderVatEvidenceSubscriber;

final class CoreTest extends TestCase
{
    public function testNormalize(): void
    {
        self::assertSame('DE123456789', VatNumber::normalize(' de 123.456-789 '));
        self::assertSame('EL123456789', VatNumber::normalize('GR123456789'));
        self::assertSame('GR', VatNumber::countryOf('EL123456789'));
        self::assertSame('GB', VatNumber::countryOf('XI123456789'));
        self::assertSame('', VatNumber::countryOf('US123'));
        self::assertFalse(VatNumber::looksValid('DE1'));
        self::assertTrue(VatNumber::looksValid('ATU12345678'));
    }

    public function testValidResponseIsParsedAndCached(): void
    {
        $t = new FakeTransport(200, json_encode(['status' => 'valid', 'valid' => true, 'name' => '<b>ACME GmbH</b>', 'consultation_number' => 'WAPIAAAA', 'request_date' => '2026-10-28']));
        $c = new VatApiClient($t);
        $r = $c->verify('de 123456789', 'k_live', 'HU33119856');
        self::assertTrue($r['valid']);
        self::assertSame('ACME GmbH', $r['name']);
        self::assertSame('WAPIAAAA', $r['consultation_number']);
        self::assertStringContainsString('vat=DE123456789', $t->calls[0]['url']);
        self::assertStringContainsString('requester=HU33119856', $t->calls[0]['url']);
        self::assertSame('k_live', $t->calls[0]['headers']['X-API-Key']);
        $c->verify('DE123456789', 'k_live', 'HU33119856');
        self::assertCount(1, $t->calls, 'second call served from cache');
    }

    public function testNoKeyNoHeader(): void
    {
        $t = new FakeTransport(200, '{"status":"invalid","valid":false}');
        $r = (new VatApiClient($t))->verify('FR12345678901');
        self::assertFalse($r['valid']);
        self::assertArrayNotHasKey('X-API-Key', $t->calls[0]['headers']);
    }

    public function testBadFormatSkipsNetwork(): void
    {
        $t = new FakeTransport();
        $r = (new VatApiClient($t))->verify('US12345');
        self::assertSame('invalid_format', $r['status']);
        self::assertCount(0, $t->calls);
    }

    public function testErrorsAreUnknownNotInvalid(): void
    {
        foreach ([[429, 'quota'], [402, 'quota'], [401, 'quota'], [503, 'error']] as [$code, $status]) {
            $r = (new VatApiClient(new FakeTransport($code, '{}')))->verify('DE123456789');
            self::assertSame($status, $r['status']);
            self::assertNull($r['valid']);
        }
        $r = (new VatApiClient(new FakeTransport(throw: new \RuntimeException('timeout'))))->verify('DE123456789');
        self::assertSame('error', $r['status']);
        self::assertSame('timeout', $r['error']);
        $r = (new VatApiClient(new FakeTransport(200, '{"status":"unavailable","valid":null}')))->verify('DE123456789');
        self::assertNull($r['valid']);
    }

    public function testDecision(): void
    {
        $valid = ['status' => 'valid', 'valid' => true];
        $invalid = ['status' => 'invalid', 'valid' => false];
        $down = ['status' => 'unavailable', 'valid' => null];
        self::assertSame('', VatDecision::evaluate('', 'DE', null, 'block', 'allow'));
        self::assertSame(VatDecision::ERR_FORMAT, VatDecision::evaluate('US1234', 'DE', null, 'block', 'allow'));
        self::assertSame(VatDecision::ERR_COUNTRY, VatDecision::evaluate('FR12345678901', 'DE', $valid, 'block', 'allow'));
        self::assertSame('', VatDecision::evaluate('GR123456789', 'GR', $valid, 'block', 'allow'));
        self::assertSame('', VatDecision::evaluate('DE123456789', 'DE', $valid, 'block', 'allow'));
        self::assertSame(VatDecision::ERR_INVALID, VatDecision::evaluate('DE123456789', 'DE', $invalid, 'block', 'allow'));
        self::assertSame('', VatDecision::evaluate('DE123456789', 'DE', $invalid, 'allow', 'allow'));
        self::assertSame('', VatDecision::evaluate('DE123456789', '', $down, 'block', 'allow'));
        self::assertSame(VatDecision::ERR_UNAVAILABLE, VatDecision::evaluate('DE123456789', '', $down, 'block', 'block'));
    }

    public function testEvidenceFields(): void
    {
        $e = OrderVatEvidenceSubscriber::evidence('de123456789', ['status' => 'valid', 'consultation_number' => 'WAPI1', 'name' => 'ACME', 'request_date' => '2026-10-28'], true);
        self::assertSame('DE123456789', $e['wsystem_vat_id']);
        self::assertSame('WAPI1', $e['wsystem_vat_consultation_number']);
        self::assertSame('yes', $e['wsystem_vat_reverse_charge']);
    }
}
