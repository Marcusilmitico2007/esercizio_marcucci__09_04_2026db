<?php
// composer require mongodb/mongodb
// php -S 0.0.0.0:8000
require 'vendor/autoload.php';

// Notice the host is 'mongo' instead of 'localhost'
$client = new MongoDB\Client("mongodb://mongo:27017");

$db = $client->testdb;
$collection = $db->testcollection;

$result = $collection->insertOne(['status' => 'Dev container is working!']);
echo "Inserted ID: " . $result->getInsertedId() . "\n";
?>