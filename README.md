# Laravel Qmonitor Collector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qmonitor/laravel-qmonitor-collector.svg?style=flat-square)](https://packagist.org/packages/qmonitor/laravel-qmonitor-collector)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/qmonitor/laravel-qmonitor-collector/run-tests?label=tests)](https://github.com/qmonitor/laravel-qmonitor-collector/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/qmonitor/laravel-qmonitor-collector/Check%20&%20fix%20styling?label=code%20style)](https://github.com/qmonitor/laravel-qmonitor-collector/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)

Qmonitor enables you to monitor queue tasks from all your Laravel apps. This package will collect and send data and telemetry to qmonitor.io from where you can then inspect and analyze your queue jobs.

## Installation

Before running this command, you need to create and account with [qmonitor.io](https://qmonitor.io).

```bash
composer require qmonitor/laravel-qmonitor-collector:^1.0
```

> The v1.0 of this package is only compatible with Laravel 5.7, 5.8 and 6.x, If you're using Laravel 7.x or 8.x, install the v2.0 of this package

## Usage

When you create a new application on qmonitor.io, you'll get a setup key with each individual app. This setup key is used to setup things in your Laravel app by running this command:

```bash
php artisan qmonitor:setup <setup-key>
```

This command will handle a few things for you:

- it will reach out to our setup endpoint and retrieve the some credentials;
- it will publish the ```qmonitor.php``` config file into your Laravel app configs folder;
- it will add the credentials keys and values to the```.env``` file;
- it will add the credentials keys to your the ```.env.example``` file;
- it will send a test heartbeat to qmonitor.io.

**Note:** This step requires you to be connected to the internet, as it will reach out to our setup endpoint to retrieve the required credentials to set up in your app environment.

This is the content of the published config file:

```php
return [
    /**
     * Main toggle for Qmonitor event tracking
     */
    'enabled' => env('QMONITOR_ENABLED', true),

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
        // \Qmonitor\Jobs\QmonitorHeartbeatJob::class,
        // ...
        // eg. \App\Jobs\UntrackedJob::class,
    ],
  
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
];
```

### Heartbeats (optional)

You also have the option for your app to send heartbeats to [qmonitor.io](https://qmonitor.io) to have that extra assurance that your queue workers are doing their thing.

In order to setup heartbeats, all you have to do is add a new scheduled command to your console kernel file:

```php
// in app/Console/Kernel.php
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule) 
    {
      	// ...
		$schedule->command('qmonitor:heartbeat')->everyFiveMinutes();      
    }
}
```

This command will dispatch a job to the queue that will send a ping to our heartbeat endpoint. You can choose whatever frequency you want to trigger this, with every 5 minutes being the lowest one that will work with our system. If you set this command to run more often than 5 minutes, our system will drop the extra requests and only register them every 5 minutes.

**Note**: this command makes use of the [Laravel Task Scheduling](https://laravel.com/docs/8.x/scheduling#introduction), so make sure you have your scheduler up and running on your machine.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Lucian Brodoceanu](https://github.com/brodos)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
