<?php

declare(strict_types=1);

namespace App\Tests\Debug;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DebugContainerTest extends KernelTestCase
{
    public function testContainerHasTestService(): void
    {
        self::bootKernel();
        
        $kernel = self::$kernel;
        echo "\n=== DEBUG INFO ===\n";
        echo "Kernel class: " . get_class($kernel) . "\n";
        echo "Kernel environment: " . $kernel->getEnvironment() . "\n";
        echo "Kernel debug: " . ($kernel->isDebug() ? 'true' : 'false') . "\n";
        
        $container = $kernel->getContainer();
        echo "Container class: " . get_class($container) . "\n";
        echo "Has test.service_container: " . ($container->has('test.service_container') ? 'YES' : 'NO') . "\n";
        
        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            echo "Test container retrieved successfully\n";
            echo "Test container class: " . get_class($testContainer) . "\n";
        } else {
            echo "ERROR: test.service_container NOT FOUND\n";
            echo "Available service IDs containing 'test': \n";
            foreach ($container->getServiceIds() as $id) {
                if (str_contains($id, 'test')) {
                    echo " - $id\n";
                }
            }
        }
        
        $this->assertTrue($container->has('test.service_container'));
    }
}
