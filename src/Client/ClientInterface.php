<?php

namespace Qmonitor\Client;

interface ClientInterface
{
    public function timeout(int $seconds): self;

    public function withSignature(string $signature): self;

    public function withPayload(array $payload): self;

    public function sendTo(string $url): array;

    public function buildHttpClient();
}
