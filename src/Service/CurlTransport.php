<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Service;

final class CurlTransport implements HttpTransport
{
    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => $timeoutSeconds,
            \CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
            \CURLOPT_HTTPHEADER => $lines,
            \CURLOPT_USERAGENT => 'wsystem-shopware-eu-vat-check/' . VatApiClient::VERSION,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($err !== '' ? $err : 'network error');
        }
        $status = (int) curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $body];
    }
}
