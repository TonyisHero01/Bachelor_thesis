<?php
$fileName = $page["id"] . ".json";
$filePath = "./locale/" . $fileName;
if (file_exists($filePath)) {
    $res = unlink($filePath);
    if (!$res) {
        http_response_code(500); // Internal Server Error
        error_log(json_encode(array("error" => "Failed to delete existing file")));
        exit;
    }
}
else {
    error_log(json_encode(array("error" => "File does not exist")));
}