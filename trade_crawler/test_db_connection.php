<?php

use MongoDB\Client;

$uri = 'mongodb+srv://kiyoonewton41:Ycj9OFUSkfFhjwJ3@trade-cluster.sinwibz.mongodb.net/?retryWrites=true&w=majority&appName=trade-cluster';

try {
    $client = new Client($uri);
    $db = $client->selectDatabase('<databaseName>');
    $count = $db->yourCollectionName->countDocuments();
    echo "Number of documents in yourCollectionName: {$count}\n";
} catch (Exception $e) {
    printf("Error: %s", $e->getMessage());
}
