<?php

namespace Qmonitor\Client;

use Zttp\ConnectionException;
use Zttp\Zttp;

class HttpClient implements ClientInterface
{
    protected $timeout;

    protected $signature;

    protected $payload;

    public function withSignature(string $signature): ClientInterface
    {
        $this->signature = $signature;

        return $this;
    }

    public function withPayload(array $payload): ClientInterface
    {
        $this->payload = $payload;

        return $this;
    }

    public function timeout(int $seconds): ClientInterface
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function sendTo(string $url): array
    {
        $response = $this->buildHttpClient()->post($url, $this->payload ?? []);

        if ($response->isClientError() || $response->isServerError()) {
            throw new ConnectionException('Connection error');
        }

        return $response->json();
    }

    /**
     * Build Http client
     *
     * @return Illuminate\Http\Client\PendingRequest
     */
    public function buildHttpClient()
    {
        $client = app(Zttp::class)->asJson()->accept('application/json');

        if ($this->timeout) {
            $client->timeout($this->timeout);
        }

        if ($this->signature) {
            $client->withHeaders([
                'Signature' => $this->signature,
            ]);
        }

        return $client;
    }
}
