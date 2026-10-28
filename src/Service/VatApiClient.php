<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Service;

/**
 * Client for the W System VAT check API (VIES), https://api.wsystem.dev/verify.
 * Works without a key (free tier per IP); an API key raises the limits.
 */
class VatApiClient
{
    public const VERSION = '0.1.0';
    public const ENDPOINT = 'https://api.wsystem.dev/verify';

    /** @var array<string,array{at:int,result:array<string,mixed>}> */
    private array $cache = [];

    public function __construct(
        private readonly HttpTransport $transport,
        private readonly string $endpoint = self::ENDPOINT,
        private readonly int $cacheSeconds = 86400,
    ) {
    }

    /**
     * @return array{status:string,valid:bool|null,name:string,address:string,consultation_number:string,request_date:string,error:string}
     */
    public function verify(string $vat, string $apiKey = '', string $requester = ''): array
    {
        $vat = VatNumber::normalize($vat);
        if (!VatNumber::looksValid($vat)) {
            return self::result('invalid_format', false);
        }
        $cacheKey = $vat . '|' . $requester;
        if (isset($this->cache[$cacheKey]) && $this->cache[$cacheKey]['at'] > time() - $this->cacheSeconds) {
            return $this->cache[$cacheKey]['result'];
        }
        $query = ['vat' => $vat];
        if ($requester !== '') {
            $query['requester'] = VatNumber::normalize($requester);
        }
        $headers = ['Accept' => 'application/json'];
        if ($apiKey !== '') {
            $headers['X-API-Key'] = $apiKey;
        }
        try {
            $resp = $this->transport->get($this->endpoint . '?' . http_build_query($query), $headers, 15);
        } catch (\RuntimeException $e) {
            return self::result('error', null, error: $e->getMessage());
        }
        $code = $resp['status'];
        if (\in_array($code, [401, 402, 429], true)) {
            return self::result('quota', null, error: $code === 401 ? 'invalid_api_key' : 'quota_exhausted');
        }
        $body = json_decode($resp['body'], true);
        if ($code !== 200 || !\is_array($body) || !isset($body['status'])) {
            return self::result('error', null, error: 'http_' . $code);
        }
        $status = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $body['status'])) ?? '';
        $valid = $body['valid'] ?? null;
        $result = self::result(
            $status,
            \is_bool($valid) ? $valid : null,
            self::text($body['name'] ?? ''),
            self::text($body['address'] ?? ''),
            self::text($body['consultation_number'] ?? ''),
            self::text($body['request_date'] ?? ''),
        );
        if (\in_array($status, ['valid', 'invalid'], true)) {
            $this->cache[$cacheKey] = ['at' => time(), 'result' => $result];
        }

        return $result;
    }

    private static function text(mixed $v): string
    {
        return \is_scalar($v) ? trim(strip_tags((string) $v)) : '';
    }

    /**
     * @return array{status:string,valid:bool|null,name:string,address:string,consultation_number:string,request_date:string,error:string}
     */
    private static function result(string $status, ?bool $valid, string $name = '', string $address = '', string $cn = '', string $date = '', string $error = ''): array
    {
        return [
            'status' => $status,
            'valid' => $valid,
            'name' => $name,
            'address' => $address,
            'consultation_number' => $cn,
            'request_date' => $date,
            'error' => $error,
        ];
    }
}
