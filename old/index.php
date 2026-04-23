<?php
require 'vendor/autoload.php';

// 1. CONNECT to the MongoDB container (we use 'mongo' as the host, defined in docker-compose.yml)
$client = new MongoDB\Client("mongodb://mongo:27017");

// Select the database ('toyapp') and collection ('tasks'). 
// MongoDB creates these automatically the first time you insert data.
$collection = $client->toyapp->tasks;

// Handle CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // CREATE: Insert a new document
    if ($_POST['action'] === 'add' && !empty($_POST['task_name'])) {
        $collection->insertOne([
            'task' => htmlspecialchars($_POST['task_name']),
            'completed' => false,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        header('Location: /');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    // MongoDB uses a specific BSON ObjectId format for primary keys
    $id = new MongoDB\BSON\ObjectId($_GET['id']);

    // UPDATE: Modify an existing document using the $set operator
    if ($_GET['action'] === 'complete') {
        $collection->updateOne(
            ['_id' => $id],
            ['$set' => ['completed' => true]]
        );
    }
    // DELETE: Remove a document based on its unique _id
    elseif ($_GET['action'] === 'delete') {
        $collection->deleteOne(['_id' => $id]);
    }

    header('Location: /');
    exit;
}

// READ: Fetch all tasks, sorting by newest first (-1)
$tasks = $collection->find([], ['sort' => ['created_at' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>MongoDB PHP Toy App</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            background: #f4f4f9;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-top: 0;
            color: #333;
        }

        form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        input[type="text"] {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            padding: 8px 12px;
            background: #00684A;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #004d37;
        }

        ul {
            list-style: none;
            padding: 0;
        }

        li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .completed {
            text-decoration: line-through;
            color: #888;
        }

        .actions a {
            text-decoration: none;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .btn-complete {
            color: #28a745;
        }

        .btn-delete {
            color: #dc3545;
        }
    </style>
</head>

<body>

    <div class="card">
        <h2>MongoDB To-Do List</h2>

        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="text" name="task_name" placeholder="What do you need to do?" required>
            <button type="submit">Add Task</button>
        </form>

        <ul>
            <?php foreach ($tasks as $task): ?>
                <li>
                    <span class="<?= $task['completed'] ? 'completed' : '' ?>">
                        <?= $task['task'] ?>
                    </span>
                    <div class="actions">
                        <?php if (!$task['completed']): ?>
                            <a href="?action=complete&id=<?= $task['_id'] ?>" class="btn-complete">✓ Complete</a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?= $task['_id'] ?>" class="btn-delete">✗ Delete</a>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (empty((array)$tasks)): ?>
                <li style="justify-content: center; color: #888;">No tasks yet!</li>
            <?php endif; ?>
        </ul>
    </div>

</body>

</html>