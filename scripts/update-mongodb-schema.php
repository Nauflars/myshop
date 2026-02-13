<?php

require 'vendor/autoload.php';

try {
    $client = new \MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    $database = $client->selectDatabase('myshop');
    
    echo "Updating user_embeddings collection schema...\n\n";
    
    // New validator that matches the code
    $newValidator = [
        '$jsonSchema' => [
            'bsonType' => 'object',
            'required' => ['user_id', 'vector', 'version', 'created_at', 'updated_at'],
            'properties' => [
                'user_id' => [
                    'bsonType' => 'string',
                    'description' => 'User UUID (string format from MySQL users table)',
                    'pattern' => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' // UUID format
                ],
                'vector' => [
                    'bsonType' => 'array',
                    'minItems' => 1536,
                    'maxItems' => 1536,
                    'items' => [
                        'bsonType' => 'double'
                    ],
                    'description' => '1536-dimensional embedding vector (OpenAI text-embedding-3-small)'
                ],
                'version' => [
                    'bsonType' => 'int',
                    'minimum' => 1,
                    'description' => 'Optimistic locking version number'
                ],
                'created_at' => [
                    'bsonType' => 'date',
                    'description' => 'Timestamp when embedding was first created'
                ],
                'updated_at' => [
                    'bsonType' => 'date',
                    'description' => 'Timestamp when embedding was last updated'
                ],
                'last_updated_at' => [
                    'bsonType' => 'date',
                    'description' => 'Timestamp of last embedding update (domain field)'
                ]
            ]
        ]
    ];
    
    // Update the validator using collMod command
    $command = [
        'collMod' => 'user_embeddings',
        'validator' => $newValidator,
        'validationLevel' => 'moderate',
        'validationAction' => 'error'
    ];
    
    $result = $database->command($command);
    
    echo "Schema updated successfully!\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
