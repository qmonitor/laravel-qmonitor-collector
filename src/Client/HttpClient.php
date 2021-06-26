<?php

namespace Qmonitor\Client;

use Illuminate\Support\Facades\Http;

class HttpClient implements ClientInterface
{
    protected $timeout;

    protected $signature;

    protected $payload;

    public function withSignature(string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function withPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function sendTo(string $url): array
    {
        $response = $this->buildHttpClient()->post($url, $this->payload ?? []);

        $response->throw();

        return $response->json();
    }

    /**
     * Build Http client
     *
     * @return Illuminate\Http\Client\PendingRequest
     */
    public function buildHttpClient()
    {
        $client = Http::asJson()->acceptJson();

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
