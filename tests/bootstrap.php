<?php

// The tests run without SeAT or a database, so only code that needs neither is
// loaded: the sums, the rules and the source files themselves.

require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    foreach (['RattingManager\\Tests\\' => __DIR__ . '/', 'RattingManager\\' => dirname(__DIR__) . '/src/'] as $prefix => $root) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});

// A mention nobody can use is logged. There is nowhere to log to here.
if (!class_exists('Illuminate\Support\Facades\Log')) {
    class_alias(RattingManager\Tests\NoLog::class, 'Illuminate\Support\Facades\Log');
}
