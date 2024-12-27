<?php
header("Content-Type: application/json");

// 1) Only allow POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["error" => "Only POST requests are allowed"]);
    exit;
}

// 2) Check for uploaded file
if (!isset($_FILES["audio_file"]) || $_FILES["audio_file"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["error" => "No file uploaded or file upload error"]);
    exit;
}

// 3) Prepare uploads folder
$uploadsDir = __DIR__ . "/uploads/";
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// 4) Move uploaded file
$targetFile = $uploadsDir . basename($_FILES["audio_file"]["name"]);
if (!move_uploaded_file($_FILES["audio_file"]["tmp_name"], $targetFile)) {
    echo json_encode(["error" => "Failed to move uploaded file"]);
    exit;
}

// 5) Build shell command
//    Change this path to your venv's python, e.g. /var/www/html/whisper_api/venv/bin/python
$venvPythonPath = "/var/www/html/whisper_api/venv/bin/python";

// Escape arguments for safety
$escapedFilePath = escapeshellarg($targetFile);
$escapedPythonScript = escapeshellarg(__DIR__ . "/transcribe.py");

// Capture both stdout and stderr (2>&1)
$command = $venvPythonPath . " " . $escapedPythonScript . " " . $escapedFilePath . " 2>&1";

// 6) Execute and capture output
$output = shell_exec($command);

// 7) Handle empty/failed execution
if ($output === null || $output === "") {
    echo json_encode([
        "error" => "No output from transcription (possibly missing dependencies or permission issue).",
        "raw_output" => $output
    ]);
    exit;
}

// 8) Try to decode JSON
$result = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "error" => "Failed to parse JSON output from transcription script.",
        "raw_output" => $output
    ]);
    exit;
}

// 9) If transcription succeeded, return it
if (isset($result["transcription"])) {
    echo json_encode([
        "transcribed_text" => $result["transcription"]
    ]);
} else {
    // 10) Otherwise, pass through any error
    echo json_encode([
        "error" => "Failed to transcribe or parse result",
        "raw_output" => $output
    ]);
}
