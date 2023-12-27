<?php

$inputJSON = file_get_contents('php://input');

$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    error_log("Invalid JSON data");
    exit;
}
$fileName = $input["fileName"];
array_shift($input);
$filePath = "./locale/" . $fileName;
if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        http_response_code(500); // Internal Server Error
        echo json_encode(array("error" => "Failed to delete existing file"));
        exit;
    }
}

$directoryPath = APP_DIRECTORY . "/locale/";
if (!file_exists($directoryPath)) {
    mkdir($directoryPath, 0755, true);

    if (!file_exists($directoryPath)) {
        http_response_code(500); // Internal Server Error
        echo json_encode(array("error" => "Failed to create directory"));
        exit;
    }
}

$file = fopen($filePath, "w");
if ($file === false) {
    http_response_code(500); // Internal Server Error
    echo json_encode(array("error" => "Failed to open file for writing"));
    exit;
}

if (fwrite($file, json_encode($input, JSON_UNESCAPED_UNICODE)) === false) {
    http_response_code(500); // Internal Server Error
    echo json_encode(array("error" => "Failed to write to file"));
    fclose($file);
    exit;
}

fclose($file);