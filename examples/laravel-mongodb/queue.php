<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\CallQueuedHandler;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/LambdaSqsJob.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

function handler(array $event): array
{
    global $app;

    $handler = $app->make(CallQueuedHandler::class);
    $failures = [];

    foreach ($event['Records'] ?? [] as $record) {
        $payload = json_decode($record['body'], true);

        $job = new LambdaSqsJob(
            $app,
            $record['messageId'],
            (int) ($record['attributes']['ApproximateReceiveCount'] ?? 1),
            $record['body'],
        );

        try {
            $handler->call($job, $payload['data']);
        } catch (Throwable $e) {
            report($e);
            $handler->failed($payload['data'], $e, $payload['uuid'] ?? $record['messageId']);
            $failures[] = ['itemIdentifier' => $record['messageId']];
        }
    }

    return ['batchItemFailures' => $failures];
}
