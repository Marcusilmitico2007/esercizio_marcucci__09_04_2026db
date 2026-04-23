<?php
require 'vendor/autoload.php';

use MongoDB\Client;

// Connessione a MongoDB
$client = new Client("mongodb://localhost:27017");
$db = $client->artemis_db;
$collection = $db->artemis_crew;


header("Content-Type: application/json");


$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];


$path = explode("artemis.php", $request)[1] ?? "";
$pathParts = array_values(array_filter(explode("/", $path)));


// 1. POST /artemis.php

if ($method === "POST" && count($pathParts) === 0) {
    
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['name'], $data['role'], $data['agency'])) {
        http_response_code(400);
        echo json_encode(["error" => "Dati mancanti"]);
        exit;
    }

    $astronaut = [
        "name" => $data['name'],
        "role" => $data['role'],
        "agency" => $data['agency'],
        "status" => "In addestramento"
    ];

    $result = $collection->insertOne($astronaut);

    http_response_code(201);
    echo json_encode([
        "message" => "Astronauta creato",
        "id" => (string)$result->getInsertedId()
    ]);
    exit;
}

// ------------------------
// 2. GET /artemis.php
// ------------------------
if ($method === "GET" && count($pathParts) === 0) {
    
    $crew = $collection->find();
    $result = [];

    foreach ($crew as $doc) {
        $doc['_id'] = (string)$doc['_id'];
        $result[] = $doc;
    }

    echo json_encode($result);
    exit;
}

// ------------------------
// 3. POST /artemis.php/{id}/portrait
// ------------------------
if ($method === "POST" && count($pathParts) === 2 && $pathParts[1] === "portrait") {
    
    $id = $pathParts[0];

    if (!isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(["error" => "Nessun file caricato"]);
        exit;
    }

    $file = $_FILES['image'];

    // Crea cartella uploads se non esiste
    if (!is_dir("uploads")) {
        mkdir("uploads", 0777, true);
    }

    // Nome file univoco
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = uniqid() . "." . $ext;
    $pathFile = "uploads/" . $newName;

    move_uploaded_file($file['tmp_name'], $pathFile);

    // Aggiorna MongoDB
    $collection->updateOne(
        ["_id" => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ["image_path" => $pathFile]]
    );

    echo json_encode([
        "message" => "Immagine caricata",
        "path" => $pathFile
    ]);
    exit;
}

// ------------------------
// 4. DELETE /artemis.php/{id}
// ------------------------
if ($method === "DELETE" && count($pathParts) === 1) {
    
    $id = $pathParts[0];

    $astronaut = $collection->findOne([
        "_id" => new MongoDB\BSON\ObjectId($id)
    ]);

    if (!$astronaut) {
        http_response_code(404);
        echo json_encode(["error" => "Non trovato"]);
        exit;
    }

    // Cancella file se esiste
    if (isset($astronaut['image_path']) && file_exists($astronaut['image_path'])) {
        unlink($astronaut['image_path']);
    }

    $collection->deleteOne([
        "_id" => new MongoDB\BSON\ObjectId($id)
    ]);

    echo json_encode(["message" => "Astronauta eliminato"]);
    exit;
}

// ------------------------
http_response_code(404);
echo json_encode(["error" => "Endpoint non trovato"]);