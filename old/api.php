<?php
// Set headers for a JSON-based REST API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

require '../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

// 1. Connect to the database
try {
    $client = new Client("mongodb://mongo:27017");
    $collection = $client->toyapp->tasks;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// 2. Parse the request URL and Method
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'];

// Strip the script name (e.g., "/app/app.php") from the URL if it exists
if (strpos($requestUri, $scriptName) === 0) {
    $requestUri = substr($requestUri, strlen($scriptName));
}

// Extract the ID from the URL if present (e.g., from "/64b5f8...")
$idString = trim($requestUri, '/');
$id = null;

// Validate and create the BSON ObjectId if an ID was passed
if ($idString !== '') {
    try {
        $id = new ObjectId($idString);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid MongoDB ID format"]);
        exit;
    }
}

// Get the JSON body payload for POST/PUT requests
$input = json_decode(file_get_contents('php://input'), true);

// 3. Handle REST Operations
switch ($method) {
    case 'GET':
        if ($id) {
            // READ ONE: GET /{id}
            $task = $collection->findOne(['_id' => $id]);
            if ($task) {
                echo json_encode(formatDocument($task));
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Task not found"]);
            }
        } else {
            // READ ALL: GET /
            // Find all tasks, sorted newest first
            $tasks = $collection->find([], ['sort' => ['_id' => -1]]);
            $result = [];
            foreach ($tasks as $task) {
                $result[] = formatDocument($task);
            }
            echo json_encode($result);
        }
        break;

    case 'POST':
        // CREATE: POST /
        if (empty($input['task'])) {
            http_response_code(400);
            echo json_encode(["error" => "Task description is required"]);
            exit;
        }
        
        $insertResult = $collection->insertOne([
            'task' => $input['task'],
            'completed' => false,
            'created_at' => new UTCDateTime()
        ]);
        
        http_response_code(201); // 201 Created
        echo json_encode([
            "message" => "Task created successfully", 
            "id" => (string) $insertResult->getInsertedId()
        ]);
        break;

    case 'PUT':
        // UPDATE: PUT /{id}
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID is required for updates"]);
            exit;
        }
        
        $updateFields = [];
        if (isset($input['task'])) $updateFields['task'] = $input['task'];
        if (isset($input['completed'])) $updateFields['completed'] = (bool)$input['completed'];
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(["error" => "No valid fields provided to update"]);
            exit;
        }

        $updateResult = $collection->updateOne(
            ['_id' => $id],
            ['$set' => $updateFields]
        );

        if ($updateResult->getMatchedCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Task not found"]);
        } else {
            echo json_encode(["message" => "Task updated successfully"]);
        }
        break;

    case 'DELETE':
        // DELETE: DELETE /{id}
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID is required for deletion"]);
            exit;
        }

        $deleteResult = $collection->deleteOne(['_id' => $id]);
        
        if ($deleteResult->getDeletedCount() === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Task not found"]);
        } else {
            echo json_encode(["message" => "Task deleted successfully"]);
        }
        break;

    default:
        http_response_code(405); // 405 Method Not Allowed
        echo json_encode(["error" => "HTTP Method not allowed"]);
        break;
}

/**
 * Helper function to clean up MongoDB BSON objects for standard JSON output.
 * It converts BSON ObjectIds to plain strings and BSON Dates to ISO strings.
 */
function formatDocument($doc) {
    // Convert the MongoDB BSONDocument object to a standard PHP array
    $array = $doc->getArrayCopy();
    
    // Map the unique _id to a clean string
    $array['id'] = (string) $array['_id']; 
    unset($array['_id']); 
    
    // Format the date cleanly
    if (isset($array['created_at']) && $array['created_at'] instanceof UTCDateTime) {
        $array['created_at'] = $array['created_at']->toDateTime()->format('c');
    }
    
    return $array;
}