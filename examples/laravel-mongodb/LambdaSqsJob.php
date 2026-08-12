<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

/**
 * Lambda's SQS event source mapping already owns message deletion/retry
 * (it deletes on successful return, retries via redrive policy on
 * failure/batchItemFailures), so this deliberately doesn't talk to a real
 * SqsClient the way Illuminate\Queue\Jobs\SqsJob does - delete()/release()
 * just use the base Job class's no-op flag-setting behaviour.
 */
class LambdaSqsJob extends Job implements JobContract
{
    public function __construct(
        Container $container,
        private readonly string $messageId,
        private readonly int $receiveCount,
        private readonly string $rawBody,
    ) {
        $this->container = $container;
        $this->connectionName = 'sqs';
        $this->queue = 'default';
    }

    public function getJobId()
    {
        return $this->messageId;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }

    public function attempts()
    {
        return $this->receiveCount;
    }
}
