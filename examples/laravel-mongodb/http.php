<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';

function handler(array $event): array
{
    global $app;

    $method = $event['requestContext']['http']['method'] ?? 'GET';
    $uri = $event['rawPath'] ?? '/';
    if (!empty($event['rawQueryString'])) {
        $uri .= '?' . $event['rawQueryString'];
    }

    $body = $event['body'] ?? '';
    if (!empty($event['isBase64Encoded'])) {
        $body = base64_decode($body);
    }

    $cookies = [];
    foreach ($event['cookies'] ?? [] as $cookie) {
        [$name, $value] = array_pad(explode('=', $cookie, 2), 2, '');
        $cookies[$name] = urldecode($value);
    }

    $server = ['REQUEST_METHOD' => $method];
    foreach ($event['headers'] ?? [] as $name => $value) {
        $key = strtoupper(str_replace('-', '_', $name));
        if (!in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $key = 'HTTP_' . $key;
        }
        $server[$key] = $value;
    }

    $request = Request::create($uri, $method, [], $cookies, [], $server, $body);

    /** @var Kernel $kernel */
    $kernel = $app->make(Kernel::class);
    $response = $kernel->handle($request);

    $headers = [];
    $setCookies = [];
    foreach ($response->headers->all() as $name => $values) {
        if (strtolower($name) === 'set-cookie') {
            $setCookies = $values;
            continue;
        }
        $headers[$name] = implode(', ', $values);
    }

    $result = [
        'statusCode' => $response->getStatusCode(),
        'headers' => $headers,
        'body' => $response->getContent(),
        'isBase64Encoded' => false,
    ];
    if ($setCookies) {
        $result['cookies'] = $setCookies;
    }

    $kernel->terminate($request, $response);

    return $result;
}
