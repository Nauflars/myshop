<?php

require 'vendor/autoload.php';

try {
    $client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    $database = $client->selectDatabase('myshop');

    // Get collection info including validator
    $collections = $database->listCollections(['filter' => ['name' => 'user_embeddings']]);

    foreach ($collections as $collectionInfo) {
        echo 'Collection: '.$collectionInfo->getName()."\n\n";

        $options = $collectionInfo->getOptions();

        if (isset($options['validator'])) {
            echo "Validator:\n";
            print_r($options['validator']);
        } else {
            echo "No validator configured\n";
        }

        if (isset($options['validationLevel'])) {
            echo "\nValidation Level: ".$options['validationLevel']."\n";
        }

        if (isset($options['validationAction'])) {
            echo 'Validation Action: '.$options['validationAction']."\n";
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
