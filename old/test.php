<?php require "vendor/autoload.php";

$client = new MongoDB\Client("mongodb://mongo:27017");


$db = $client->testdb;

$collection = $db->userlist;


$data = ["message"=>"Hello World!"];

$result = $collection->insertOne($data);



echo "ID della risorsa:" .$result->getInsertedID() . "<br>";

?>