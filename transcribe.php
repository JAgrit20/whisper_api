<?php
header("Content-Type: application/json");

// Allow only POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

// Check if a file was actually sent
if (!isset($_FILES["audio_file"]) || $_FILES["audio_file"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "No file uploaded or file upload error"]);
    exit;
}

// Create an uploads folder or ensure it's writable
$uploadsDir = __DIR__ . "/uploads/";
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Move the uploaded file to the uploads folder
$targetFile = $uploadsDir . basename($_FILES["audio_file"]["name"]);
if (!move_uploaded_file($_FILES["audio_file"]["tmp_name"], $targetFile)) {
    echo json_encode(["error" => "Failed to move uploaded file"]);
    exit;
}

// Call the Python script to transcribe the file
$escapedFilePath = escapeshellarg($targetFile);  // Escape for safety
$command = "/usr/bin/env python3 " . __DIR__ . "/transcribe.py $escapedFilePath";
$output = shell_exec($command);

// You can decode the JSON printed by Python if you like:
$result = json_decode($output, true);

if ($result && isset($result["transcription"])) {
    echo json_encode([
        "transcribed_text" => $result["transcription"]
    ]);
} else {
    echo json_encode([
        "error" => "Failed to transcribe or parse result",
        "raw_output" => $output
    ]);
}
