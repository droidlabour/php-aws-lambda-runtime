#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   aws-lambda/invoke-sqs.sh BODY_JSON [MESSAGE_ID]
#
# BODY_JSON is the raw SQS message body Laravel's queue would have produced
# itself (uuid/displayName/job/data). Generate one for local testing with:
#
#   docker exec <worker-container> php aws-lambda/make-test-job.php \
#       'App\Jobs\SomeJob' arg1 arg2
#
# Example:
#   aws-lambda/invoke-sqs.sh "$(docker exec <worker-container> php aws-lambda/make-test-job.php \
#       'App\Jobs\SomeJob' arg1 arg2)"

BODY="${1:?Usage: invoke-sqs.sh BODY_JSON [MESSAGE_ID]}"
MESSAGE_ID="${2:-$(uuidgen 2>/dev/null || echo test-message-id)}"

EVENT=$(jq -n \
  --arg body "$BODY" \
  --arg messageId "$MESSAGE_ID" \
  '{
    Records: [{
      messageId: $messageId,
      receiptHandle: "test-receipt-handle",
      body: $body,
      attributes: {ApproximateReceiveCount: "1"},
      messageAttributes: {},
      md5OfBody: "",
      eventSource: "aws:sqs",
      eventSourceARN: "arn:aws:sqs:eu-west-1:000000000000:local-test-queue",
      awsRegion: "eu-west-1"
    }]
  }')

curl -sS -XPOST "http://localhost:9001/2015-03-31/functions/function/invocations" -d "$EVENT" | jq .
