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
$escapedFilePath = escapeshellarg($targetFile);

// IMPORTANT: if you have a virtual environment, use its python path here, for example:
// $python = "/var/www/html/whisper_api/venv/bin/python";
// Otherwise, try system python:
$python = "/usr/bin/env python3";

// Append '2>&1' to capture any errors on stderr
$command = $python . " " . escapeshellarg(__DIR__ . "/transcribe.py") . " $escapedFilePath 2>&1";
$output = shell_exec($command);

// If shell_exec failed or returned nothing, handle that first
if ($output === null || $output === "") {
    echo json_encode([
        "error" => "No output from transcription (possibly a missing dependency or permission issue).",
        "raw_output" => $output
    ]);
    exit;
}

// Attempt to parse JSON
$result = json_decode($output, true);

// Check for JSON parse errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "error" => "Failed to parse JSON output from transcription script.",
        "raw_output" => $output
    ]);
    exit;
}

// If we got a valid transcription field, respond with it
if (isset($result["transcription"])) {
    echo json_encode([
        "transcribed_text" => $result["transcription"]
    ]);
} else {
    // If Python returned an error or some other structure
    echo json_encode([
        "error" => "Failed to transcribe or parse result",
        "raw_output" => $output
    ]);
}
