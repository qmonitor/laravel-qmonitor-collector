<?php

namespace Qmonitor;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Qmonitor\Jobs\QmonitorHeartbeatJob;
use Qmonitor\Jobs\QmonitorPingJob;

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
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendPing(array $payload)
    {
        return Http::timeout(5)
            ->retry(2, 500)
            ->withHeaders([
                'Signature' => static::calculateSignature($payload, config('qmonitor.signing_secret')),
            ])
            ->asJson()
            ->acceptJson()
            ->post(static::pingUrl(), $payload)
            ->throw();
    }

    /**
     * Send heartbeat to Qmonitor
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendHeartbeat()
    {
        return Http::timeout(5)
            ->retry(2, 500)
            ->withHeaders([
                'Signature' => static::calculateSignature([], config('qmonitor.signing_secret')),
            ])
            ->asJson()
            ->acceptJson()
            ->post(static::heartbeatUrl())
            ->throw();
    }

    /**
     * Send ping payload to Qmonitor
     *
     * @param  array $payload
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendSetup(array $payload)
    {
        return Http::timeout(5)
            ->retry(2, 500)
            ->withHeaders([
                'Signature' => static::calculateSignature($payload, config('qmonitor.app_id')),
            ])
            ->asJson()
            ->acceptJson()
            ->post(static::setupUrl(), $payload)
            ->throw();
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
     * @return string
     */
    public static function setupUrl()
    {
        return sprintf('%s/apps/%s/setup', config('qmonitor.endpoint'), config('qmonitor.app_id'));
    }

    public static function isMonitoredJob(string $jobName)
    {
        return ! collect(config('qmonitor.dont_monitor'))
            ->merge([
                QmonitorPingJob::class,
                QmonitorHeartbeatJob::class,
            ])
            ->contains($jobName);
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
        $payloadJson = json_encode($payload);

        return hash_hmac('sha256', $payloadJson, $secret);
    }
}
