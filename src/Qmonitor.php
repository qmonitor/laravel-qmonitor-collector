<?php

namespace Qmonitor;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Qmonitor\Client\ClientInterface;

class Qmonitor
{
    /**
     * @var string
     */
    const VERSION = '0.0.1';

    /**
     * Collector version
     *
     * @return string
     */
    public static function version()
    {
        return static::VERSION;
    }

    /**
     * Get tags config flag
     *
     * @return string
     */
    public static function tagsEnabled()
    {
        return config('qmonitor.tags');
    }

    /**
     * Return the collected job types
     *
     * @return \Illuminate\Support\Collection
     */
    public static function monitoredTypes(): Collection
    {
        return collect(config('qmonitor.monitor_types'))
            ->filter(function ($type) {
                return ! ! $type;
            });
    }

    /**
     * Send ping payload to Qmonitor
     *
     * @param array $payload
     *
     * @throws \Illuminate\Http\Client\RequestException
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendPing(array $payload)
    {
        return app(ClientInterface::class)
            ->timeout(5)
            ->withSignature(static::calculateSignature($payload, config('qmonitor.signing_secret')))
            ->withPayload($payload)
            ->sendTo(static::pingUrl());
    }

    /**
     * Send heartbeat to Qmonitor
     *
     * @throws \Illuminate\Http\Client\RequestException
     *
     * @return array
     */
    public static function sendHeartbeat(): array
    {
        return app(ClientInterface::class)
            ->timeout(5)
            ->withSignature(static::calculateSignature(static::heartbeatPayload(), config('qmonitor.signing_secret')))
            ->withPayload(static::heartbeatPayload())
            ->sendTo(static::heartbeatUrl());
    }

    /**
     * Send setup payload to Qmonitor
     *
     * @param  string $setupKey
     * @param  array $payload
     *
     * @throws \Illuminate\Http\Client\RequestException
     *
     * @return array
     */
    public static function sendSetup(string $setupKey, array $payload): array
    {
        return app(ClientInterface::class)
            ->timeout(5)
            ->withSignature(static::calculateSignature($payload, $setupKey))
            ->withPayload($payload)
            ->sendTo(static::setupUrl($setupKey));
    }

    /**
     * Ping endpoint
     *
     * @return string
     */
    public static function pingUrl()
    {
        return sprintf('%s/apps/%s/events', config('qmonitor.endpoint'), config('qmonitor.app_id'));
    }

    /**
     * Heartbeat endpoint
     *
     * @return string
     */
    public static function heartbeatUrl()
    {
        return sprintf('%s/apps/%s/heartbeat', config('qmonitor.endpoint'), config('qmonitor.app_id'));
    }

    /**
     * Setup endpoint
     *
     * @param string $setupKey
     *
     * @return string
     */
    public static function setupUrl(string $setupKey)
    {
        return sprintf('%s/apps/%s/setup', config('qmonitor.endpoint'), $setupKey);
    }

    /**
     * Determine if the given job can be monitored
     *
     * @param  string  $jobName
     *
     * @return bool
     */
    public static function isMonitoredJob(string $jobName)
    {
        return ! collect(config('qmonitor.dont_monitor'))
            ->contains($jobName);
    }

    /**
    * Compile heartbeat payload
    *
    * @return array
    */
    protected static function heartbeatPayload()
    {
        return [
            'uuid' => Str::uuid(),
            'hostname' => gethostname(),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Calculate payload signature
     *
     * @param  array $payload
     * @param  string $secret
     *
     * @return string
     */
    protected static function calculateSignature(array $payload, string $secret)
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
