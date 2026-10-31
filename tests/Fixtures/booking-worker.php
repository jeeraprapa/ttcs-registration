<?php

use App\Actions\RegisterForSession;
use Illuminate\Contracts\Console\Kernel;

// Synthetic integration-test worker. It refuses any non-test database.
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! preg_match('/_test(?:ing)?$/', config('database.connections.mysql.database'))) {
    fwrite(STDERR, 'Dedicated MySQL test database required.');
    exit(2);
}
try {
    $booking = app(RegisterForSession::class)->execute(json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR));
    echo $booking->reference;
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception));
    exit(1);
}
