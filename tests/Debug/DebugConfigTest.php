<?php

declare(strict_types=1);

namespace App\Tests\Debug;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DebugConfigTest extends KernelTestCase
{
    public function testCheckEnvironment(): void
    {
        // Don't call bootKernel yet, create kernel manually
        $kernel = static::createKernel(['environment' => 'test', 'debug' => true]);
        echo "\n=== BEFORE BOOT ===\n";
        echo "Kernel class: " . get_class($kernel) . "\n";
        echo "Kernel environment: " . $kernel->getEnvironment() . "\n";
        echo "Kernel debug: " . ($kernel->isDebug() ? 'true' : 'false') . "\n";
        
        echo "\n=== BOOTING KERNEL ===\n";
        $kernel->boot();
        
        echo "\n=== AFTER BOOT ===\n";
        echo "Kernel booted: YES\n";
        
        $container = $kernel->getContainer();
        echo "Container class: " . get_class($container) . "\n";
        echo "Has service_container: " . ($container->has('service_container') ? 'YES' : 'NO') . "\n";
        echo "Has test.service_container: " . ($container->has('test.service_container') ? 'YES' : 'NO') . "\n";
        
        echo "\n=== TRYING TO GET test.service_container ===\n";
        try {
            $testContainer = $container->get('test.service_container');
            echo "SUCCESS: Got test.service_container\n";
            echo "Test container class: " . get_class($testContainer) . "\n";
        } catch (\Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        
        // Try to get framework test parameter
        if ($container->hasParameter('kernel.environment')) {
            echo "kernel.environment: " . $container->getParameter('kernel.environment') . "\n";
        }
        
        $this->assertTrue($container->has('service_container'));
    }
}
