<?php

require __DIR__.'/vendor/autoload.php';

use App\Infrastructure\AI\Tool\SemanticProductSearchTool;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
(new Dotenv())->bootEnv(__DIR__.'/.env');

// Boot Symfony kernel
$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get the SemanticProductSearchTool service
$searchTool = $container->get(SemanticProductSearchTool::class);

// Test queries
$queries = [
    'natural tea for a healthy daily routine',
    'organic beverage for clean living',
    'healthy alternative to sugary drinks',
    'plant-based drink for wellness',
    'antioxidant-rich tea for everyday health',
    'low calorie drink for balanced diet',
    'chemical-free herbal tea option',
    'natural drink to support overall wellbeing',
    'professional blender for home use', // Your original query
];

echo "==========================================================\n";
echo "SEMANTIC PRODUCT SEARCH TOOL TEST\n";
echo "==========================================================\n\n";

foreach ($queries as $index => $query) {
    echo "-----------------------------------------------------------\n";
    echo 'Query #'.($index + 1).": \"$query\"\n";
    echo "-----------------------------------------------------------\n";

    try {
        // Call the tool directly
        $result = $searchTool->__invoke(
            query: $query,
            mode: 'semantic',
            limit: 5,
            category: null,
            minSimilarity: 0.5
        );

        echo 'Success: '.($result['success'] ? 'YES' : 'NO')."\n";
        echo 'Count: '.$result['count']."\n";
        echo 'Mode: '.($result['search_mode'] ?? 'N/A')."\n";
        echo 'Execution Time: '.($result['execution_time_ms'] ?? 'N/A')." ms\n";

        if ($result['count'] > 0) {
            echo "\nProducts Found:\n";
            foreach ($result['products'] as $idx => $product) {
                echo '  '.($idx + 1).". {$product['name']}\n";
                echo "     - Price: \${$product['price']} {$product['currency']}\n";
                echo "     - Category: {$product['category']}\n";
                echo "     - Stock: {$product['stock']}\n";
                echo "     - Similarity Score: {$product['similarity_score']}\n";
                if (!empty($product['description'])) {
                    $shortDesc = substr($product['description'], 0, 80);
                    echo "     - Description: $shortDesc".(strlen($product['description']) > 80 ? '...' : '')."\n";
                }
                echo "\n";
            }
        } else {
            echo "\n⚠️  No products found!\n";
            if (!empty($result['suggestions'])) {
                echo "Suggestions:\n";
                foreach ($result['suggestions'] as $suggestion) {
                    echo "  - $suggestion\n";
                }
            }
        }
    } catch (Exception $e) {
        echo '❌ ERROR: '.$e->getMessage()."\n";
        echo "Stack trace:\n".$e->getTraceAsString()."\n";
    }

    echo "\n";
}

echo "==========================================================\n";
echo "TEST COMPLETED\n";
echo "==========================================================\n";
