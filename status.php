<?php
header("Content-Type: application/json");

// Database connection settings
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'Athabasca@123';
$dbName = 'notetakers';

// Connect to the database
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $mysqli->connect_error]);
    exit;
}

// Validate and sanitize GET parameters
if (isset($_GET['recording_name']) && !empty($_GET['recording_name'])) {
    $recordingName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $_GET['recording_name']);
    $recordingName = substr($recordingName, 0, 255);
} else {
    echo json_encode(["error" => "Recording name is required"]);
    exit;
}

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $userId = intval($_GET['user_id']); // Sanitize as an integer
} else {
    echo json_encode(["error" => "User ID is required"]);
    exit;
}

if (isset($_GET['chunk_id']) && !empty($_GET['chunk_id'])) {
    $chunkId = preg_replace('/[^A-Za-z0-9_\-]/', '_', $_GET['chunk_id']);
    $chunkId = substr($chunkId, 0, 255);
} else {
    echo json_encode(["error" => "Chunk ID is required"]);
    exit;
}

// Fetch the recording details
$stmt = $mysqli->prepare("SELECT id, status, transcription FROM recordings WHERE recording_name = ? AND user_id = ? AND chunk_id = ?");
$stmt->bind_param("sis", $recordingName, $userId, $chunkId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    // Recording not found
    $stmt->close();
    $mysqli->close();
    echo json_encode(["error" => "Recording not found"]);
    exit;
}

$stmt->bind_result($recordingId, $status, $transcription);
$stmt->fetch();
$stmt->close();

$response = [
    "recording_name" => $recordingName,
    "user_id" => $userId,
    "chunk_id" => $chunkId,
    "status" => $status
];

if ($status === 'completed') {
    // Return the transcription
    $response["transcription"] = $transcription;
} elseif ($status === 'queued' || $status === 'processing') {
    // Calculate queue position for queued or processing recordings
    $queueStmt = $mysqli->prepare("SELECT COUNT(*) FROM recordings WHERE status = 'queued' AND id < ?");
    $queueStmt->bind_param("i", $recordingId);
    $queueStmt->execute();
    $queueStmt->bind_result($queuePosition);
    $queueStmt->fetch();
    $queueStmt->close();

    $response["queue_position"] = $queuePosition + 1; // Position in the queue
} elseif ($status === 'failed') {
    $response["error"] = "There was an error processing your recording.";
}

// Return the response in JSON format
echo json_encode($response);

$mysqli->close();
?>
