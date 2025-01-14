<?php
header("Content-Type: application/json");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

// Get required POST data
$user_id = $_POST['user_id'] ?? null;
$language = $_POST['language'] ?? null;
$chunk_id = $_POST['chunk_id'] ?? null;
$recordingName = $_POST['recording_name'] ?? null;

// Validate required fields
if (empty($user_id) || empty($language) || empty($chunk_id) || empty($recordingName)) {
    echo json_encode(["error" => "Missing required fields: user_id, language, chunk_id, or recording_name"]);
    exit;
}

// Sanitize the recording name
$recordingName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $recordingName);
$recordingName = substr($recordingName, 0, 255);

// Check if recording exists in the database
$stmt = $mysqli->prepare("SELECT status FROM recordings WHERE recording_name = ?");
$stmt->bind_param("s", $recordingName);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Recording exists
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();
    $mysqli->close();

    echo json_encode([
        "message" => "Recording already exists. Please call the status API to see the progress.",
        "status" => $status
    ]);
    exit;
}
$stmt->close();

// Check for uploaded file
if (!isset($_FILES["audio_file"]) || $_FILES["audio_file"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "No file uploaded or file upload error"]);
    exit;
}

// Validate file extension
$allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac'];
$originalFilename = $_FILES['audio_file']['name'];
$extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExtensions)) {
    echo json_encode(["error" => "Invalid file type. Allowed types: " . implode(', ', $allowedExtensions)]);
    exit;
}

// Prepare uploads folder
$uploadsDir = __DIR__ . "/uploads/";
if (!file_exists($uploadsDir) && !mkdir($uploadsDir, 0777, true)) {
    echo json_encode(["error" => "Failed to create uploads directory"]);
    exit;
}

// Construct target filename
$targetFilename = $recordingName . '.' . $extension;
$targetFilePath = $uploadsDir . $targetFilename;

// Move uploaded file securely
if (!move_uploaded_file($_FILES["audio_file"]["tmp_name"], $targetFilePath)) {
    echo json_encode(["error" => "Failed to move uploaded file"]);
    exit;
}

// Insert new recording into the database
$stmt = $mysqli->prepare("
    INSERT INTO recordings (user_id, recording, recording_name, language, chunk_id, file_path, status)
    VALUES (?, ?, ?, ?, ?, ?, 'queued')
");
$stmt->bind_param("isssss", $user_id, $originalFilename, $recordingName, $language, $chunk_id, $targetFilePath);
$stmt->execute();
$insertId = $stmt->insert_id;
$stmt->close();

// Add the recording to the processing queue (You can implement actual queueing mechanisms like RabbitMQ, Redis, etc. For simplicity, we'll simulate it here)

// Start asynchronous processing (process_recording.php)
$processingScript = __DIR__ . '/process_recording.php';
$command = "php " . escapeshellarg($processingScript) . " " . escapeshellarg($insertId) . " > /dev/null 2>&1 &";
exec($command);

// Calculate queue position
$queueStmt = $mysqli->prepare("SELECT COUNT(*) FROM recordings WHERE status = 'queued' AND id < ?");
$queueStmt->bind_param("i", $insertId);
$queueStmt->execute();
$queueStmt->bind_result($queuePosition);
$queueStmt->fetch();
$queueStmt->close();

$queuePosition += 1; // Position in the queue

// Return response
echo json_encode([
    "message" => "Recording uploaded successfully and queued for transcription.",
    "recording_id" => $insertId,
    "queue_position" => $queuePosition
]);

$mysqli->close();
?>
