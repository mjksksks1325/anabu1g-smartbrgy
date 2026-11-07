<?php

use Illuminate\Support\Facades\Log;

test('the test suite never writes to the application log', function () {
    $log = storage_path('logs/laravel.log');
    clearstatcache();
    $sizeBefore = file_exists($log) ? filesize($log) : null;

    Log::error('test-suite log isolation check');
    report(new RuntimeException('test-suite reported exception'));

    clearstatcache();
    expect(file_exists($log) ? filesize($log) : null)->toBe($sizeBefore);
});
