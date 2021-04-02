<?php

return [
    /**
     * Master toggle for Qmonitor event tracking
     */
    'enabled' => env('QMONITOR_ENABLED', true),

    /**
     * The queue connection to use for dispatching events to Qmonitor
     */
    'queue_connection' => $connection = env('QMONITOR_QUEUE_CONNECTION', config('queue.default')),

    /**
     * The queue to use for dispatching events to Qmonitor
     */
    'queue_name' => env('QMONITOR_QUEUE_NAME', config(sprintf('queue.connections.%s.queue', $connection))),

    /**
     * Payload signing secret
     */
    'signing_secret' => env('QMONITOR_SECRET'),

    /**
     * Qmonitor app id
     */
    'app_id' => env('QMONITOR_APP_ID'),

    /**
     * Qmonitor event collector endpoint
     */
    'endpoint' => env('QMONITOR_ENDPOINT', 'https://collector.qmonitor.io'),

    /**
     * Toggle Qmonitor job tag collection
     */
    'tags' => env('QMONITOR_TAGS', true),

    /**
     * Determine what queued jobs are monitored
     */
    'monitor_types' => [
        'job' => true, // regular queued jobs
        'mail' => true, // queued mails
        'notification' => true, // queued notifications
        'broadcast' => true, // queued broadcasts
        'event' => true, // queued event listeners
    ],

    /**
     * A list of jobs you don't want to monitor
     */
    'dont_monitor' => [
        // eg. \App\Jobs\UntrackedJob::class,
    ],
];
