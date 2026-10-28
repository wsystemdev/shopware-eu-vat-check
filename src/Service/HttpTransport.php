<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Service;

/**
 * Minimal HTTP GET abstraction so the API client can be tested offline.
 */
interface HttpTransport
{
    /**
     * @param array<string,string> $headers
     *
     * @return array{status:int,body:string}
     *
     * @throws \RuntimeException on network errors
     */
    public function get(string $url, array $headers, int $timeoutSeconds): array;
}
