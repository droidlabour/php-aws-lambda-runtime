#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   aws-lambda/invoke-http.sh METHOD ROUTE_PATH [BODY_JSON] [QUERY_STRING]
#
# Example:
#   aws-lambda/invoke-http.sh POST /api/login '{
#       "username": "someone",
#       "password": "correct-horse-battery-staple"
#   }'
#
# Set AUTH_TOKEN to add a Bearer authorization header for routes behind
# Sanctum/token auth, e.g.:
#   AUTH_TOKEN=your-token aws-lambda/invoke-http.sh GET /api/me

METHOD="${1:?Usage: invoke-http.sh METHOD ROUTE_PATH [BODY_JSON] [QUERY_STRING]}"
ROUTE_PATH="${2:?Usage: invoke-http.sh METHOD ROUTE_PATH [BODY_JSON] [QUERY_STRING]}"
BODY="${3:-}"
QUERY="${4:-}"

HEADERS='{"content-type":"application/json","accept":"application/json"}'
if [ -n "${AUTH_TOKEN:-}" ]; then
  HEADERS=$(echo "$HEADERS" | jq --arg t "Bearer $AUTH_TOKEN" '. + {authorization: $t}')
fi

EVENT=$(jq -n \
  --arg path "$ROUTE_PATH" \
  --arg query "$QUERY" \
  --arg method "$METHOD" \
  --arg body "$BODY" \
  --argjson headers "$HEADERS" \
  '{
    rawPath: $path,
    rawQueryString: $query,
    headers: $headers,
    requestContext: {http: {method: $method}},
    body: $body,
    isBase64Encoded: false
  }')

curl -sS -XPOST "http://localhost:9000/2015-03-31/functions/function/invocations" -d "$EVENT" | jq .
