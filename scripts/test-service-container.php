<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('test', true);
$kernel->boot();

try {
    $container = $kernel->getContainer();
    echo 'Container class: '.get_class($container)."\n";
    echo 'Has test.service_container: '.($container->has('test.service_container') ? 'YES' : 'NO')."\n";

    if ($container->has('test.service_container')) {
        $testContainer = $container->get('test.service_container');
        echo 'Test container class: '.get_class($testContainer)."\n";
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
