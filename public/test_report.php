<?php

use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Request::create('/reports/unified-customer', 'GET');
$response = $kernel->handle($request);

if ($response->getStatusCode() === 500 || $response->isRedirection()) {
    echo "Response Code: " . $response->getStatusCode() . "\n";
    if (file_exists(__DIR__ . '/my_error_log.txt')) {
        echo "Error Log:\n" . file_get_contents(__DIR__ . '/my_error_log.txt');
    } else {
        echo "No my_error_log.txt found.\n";
    }
} else {
    echo "Success! " . $response->getStatusCode();
}
