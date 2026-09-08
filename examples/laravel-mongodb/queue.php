<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/LambdaSqsJob.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/**
 * Reflects on the job's deserialized command object and pulls out any
 * property whose name ends in "id" (e.g. orderId, customerId), so the log
 * lines below carry the job's own identifiers without this file needing to
 * know any specific job class.
 */
function queueJobIdProperties(string $command): array
{
    $instance = @unserialize($command);

    if (!is_object($instance)) {
        return [];
    }

    $properties = [];

    foreach ((new ReflectionObject($instance))->getProperties() as $property) {
        if (!str_ends_with(strtolower($property->getName()), 'id')) {
            continue;
        }

        if (!$property->isInitialized($instance)) {
            continue;
        }

        $properties[$property->getName()] = $property->getValue($instance);
    }

    return $properties;
}

function queueJobLogContext(string $status, JobContract $job, string $exceptionMessage=null): string
{
    $command = $job->payload()['data']['command'] ?? null;

    $log = [
        'status' => $status,
        'job' => $job->resolveName(),
        'jobId' => $job->getJobId(),
        'attempts' => $job->attempts(),
        ...(is_string($command) ? queueJobIdProperties($command) : []),
    ];

    if (!is_null($exceptionMessage))
    {
        $log['exceptionMessage'] = $exceptionMessage;
    }

    return json_encode($log);
}

function handler(array $event): array
{
    global $app;

    $handler = $app->make(CallQueuedHandler::class);
    $failures = [];

    Log::info(json_encode($event));

    foreach ($event['Records'] ?? [] as $record) {
        try {
            $payload = json_decode($record['body'], true);

            $job = new LambdaSqsJob(
                $app,
                $record['messageId'],
                (int) ($record['attributes']['ApproximateReceiveCount'] ?? 1),
                $record['body'],
            );

            Log::info(queueJobLogContext('Job-Started', $job));
            $handler->call($job, $payload['data']);
            Log::info(queueJobLogContext('Job-Finished', $job));
        } catch (Throwable $e) {
            Log::info(queueJobLogContext('Job-Failed', $job, $e->getMessage()));
            report($e);
            $handler->failed($payload['data'], $e, $payload['uuid'] ?? $record['messageId']);
            $failures[] = ['itemIdentifier' => $record['messageId']];
        }
    }

    return ['batchItemFailures' => $failures];
}
