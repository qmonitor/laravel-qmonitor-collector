<?php

namespace Qmonitor\Client;

use Zttp\ConnectionException;
use Zttp\Zttp;

class HttpClient implements ClientInterface
{
    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var string
     */
    protected $signature;

    /**
     * @var array
     */
    protected $payload;

    /**
     * Set the signature param
     *
     * @param  string $signature
     *
     * @return self
     */
    public function withSignature(string $signature): ClientInterface
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * Set the payload param
     *
     * @param  array  $payload
     *
     * @return self
     */
    public function withPayload(array $payload): ClientInterface
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Set the timeout param
     *
     * @param  int    $seconds
     *
     * @return self
     */
    public function timeout(int $seconds): ClientInterface
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Send request to specified url
     *
     * @param  string $url
     *
     * @return array
     */
    public function sendTo(string $url): array
    {
        $response = $this->buildHttpClient()->post($url, $this->payload ?? []);

        if ($response->isClientError() || $response->isServerError()) {
            throw new ConnectionException('Connection error');
        }

        return $response->json() ?? [];
    }

    /**
     * Build Http client
     *
     * @return Illuminate\Http\Client\PendingRequest
     */
    public function buildHttpClient()
    {
        $client = app(Zttp::class)::asJson()->accept('application/json');

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
