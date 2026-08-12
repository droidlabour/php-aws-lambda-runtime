<?php

function handler(array $event): array
{
    return [
        'message' => 'Hello from a custom PHP Lambda runtime',
        'datetime' => date('Y-m-d H:i:s'),
        'event' => $event,
    ];
}
