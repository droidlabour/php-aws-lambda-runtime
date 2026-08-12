#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./invoke.sh '{"any": "json event"}'
#
# POSTs a raw JSON event to the Lambda Runtime Interface Emulator (RIE) -
# the same shape your handler's $event argument receives. Framework-specific
# examples (HTTP, SQS) wrap this pattern with their own event-shape builders -
# see examples/laravel-mongodb/invoke-http.sh and invoke-sqs.sh.

EVENT="${1:?Usage: invoke.sh EVENT_JSON}"

curl -sS -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d "$EVENT" | jq .
