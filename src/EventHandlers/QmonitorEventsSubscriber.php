<?php

namespace Qmonitor\EventHandlers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Qmonitor\Jobs\QmonitorPingJob;
use Qmonitor\Qmonitor;
use Qmonitor\Support\QmonitorJobPayload;
use Throwable;

class QmonitorEventsSubscriber
{
    /**
     * Subscribe to queue jobs events
     *
     * @param  Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            [
                JobProcessing::class,
                JobProcessed::class,
                JobFailed::class,
            ],
            [static::class, 'handleJobEvent']
        );
    }

    /**
     * Job events handler method
     *
     * @param  mixed $event
     * @return void
     */
    public static function handleJobEvent($event)
    {
        // try sending the payload using the current running job process
        try {
            if (! config('qmonitor.enabled') || ! config('qmonitor.app_id') || ! config('qmonitor.signing_secret')) {
                return;
            }

            // prevent processed event if the job is marked as failed
            if ($event instanceof JobProcessed && $event->job->hasFailed()) {
                return;
            }

            // check if the job name is not our ping
            if (! Qmonitor::isMonitoredJob($event->job->resolveName())) {
                return;
            }

            $payload = QmonitorJobPayload::make($event);

            // check if the job type is being collected
            if (! Qmonitor::monitoredTypes()->has($payload->type)) {
                return;
            }

            // send payload
            Qmonitor::sendPing($payload->toArray());
        } catch (Throwable $e) {
            // if the payload failed to be created, stop trying
            if (! isset($payload)) {
                report($e);

                return;
            }

            // dispatch a job with the payload so we can handle retries
            QmonitorPingJob::dispatch(
                $payload->toArray()
            );
        }
    }
}
