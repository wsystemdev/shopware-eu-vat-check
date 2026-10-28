<?php declare(strict_types=1);

namespace WSystem\EuVatCheck\Tests\Unit;

use WSystem\EuVatCheck\Service\HttpTransport;

final class FakeTransport implements HttpTransport
{
    /** @var list<array{url:string,headers:array<string,string>}> */
    public array $calls = [];

    public function __construct(private int $status = 200, private string $body = '{}', private ?\RuntimeException $throw = null)
    {
    }

    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers];
        if ($this->throw) {
            throw $this->throw;
        }

        return ['status' => $this->status, 'body' => $this->body];
    }
}
