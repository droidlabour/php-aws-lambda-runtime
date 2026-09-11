<?php

use Illuminate\Foundation\Application;
use Sentry\Laravel\Integration as SentryIntegration;
use Sentry\SentrySdk;
use Sentry\State\Scope;

/**
 * bootstrap keeps one PHP process - and one booted Laravel application -
 * alive across many invocations, and across every SQS record inside one
 * batch, on a warm container. Anything an invocation writes to shared or
 * scoped state would otherwise leak into the next one on that same
 * container: memory that only grows, and worse, data from one invocation
 * showing up in the next (log context, Sentry breadcrumbs).
 *
 * This clears that state while keeping the expensive things (service
 * providers, config, open DB connections) warm. It is a small subset of
 * what Laravel Octane does between requests.
 *
 * http.php calls it once per request; queue.php calls it after every
 * record in the batch.
 */
function lambda_flush_state(Application $app): void
{
    // Scoped container bindings, e.g. the Log context repository which is
    // registered with $app->scoped().
    $app->forgetScopedInstances();

    // The query log grows without bound while it is enabled, and the
    // "record has been modified" flag would carry over too. The connection
    // itself stays open.
    if ($app->resolved('db')) {
        foreach ($app->make('db')->getConnections() as $connection) {
            $connection->flushQueryLog();
            $connection->forgetRecordModificationState();
        }
    }

    // Context shared via Log::shareContext() / Context::add().
    if ($app->resolved('log')) {
        $logger = $app->make('log');
        $logger->flushSharedContext();
        $logger->withoutContext();
    }

    // Sentry, if your app uses sentry/sentry-laravel - this whole block is
    // a no-op otherwise, since $app->bound('sentry') is only true when the
    // package's service provider has registered. These handlers drive the
    // HTTP kernel / CallQueuedHandler directly, not through Laravel's queue
    // worker or Octane, so Sentry's own per-request / per-job scope reset
    // never runs. Send anything buffered (the SDK's shutdown-function flush
    // also never fires in the persistent loop) and drop this invocation's
    // breadcrumbs so they don't attach to the next one.
    if ($app->bound('sentry')) {
        SentryIntegration::flushEvents();

        SentrySdk::getCurrentHub()->configureScope(function (Scope $scope) {
            $scope->clearBreadcrumbs();
        });
    }

    gc_collect_cycles();
}
