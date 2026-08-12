<?php

/**
 * Dev-only helper: prints an SQS-message-body JSON string for a queued job,
 * using Laravel's own payload format, for local testing via invoke-sqs.sh.
 *
 * Usage (from inside the container):
 *   php aws-lambda/make-test-job.php 'App\Jobs\SomeJob' arg1 arg2
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$class, $args] = [$argv[1], array_slice($argv, 2)];
$job = new $class(...$args);

$queue = app('queue')->connection();
$method = new ReflectionMethod($queue, 'createPayload');
$method->setAccessible(true);

echo $method->invoke($queue, $job, 'default');
