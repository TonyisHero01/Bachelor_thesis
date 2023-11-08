<?php
require_once "constants.php";
$response = array();

if ($_FILES['myFile']) {
    $uploadDir = 'images/';

    $fileInfo = pathinfo($_FILES['myFile']['name']);
    $fileExtension = $fileInfo['extension'];

    $uniqueFileName = $_POST['name'] . '.' . $fileExtension;

    $uploadFilePath = $uploadDir . $uniqueFileName;

    if (move_uploaded_file($_FILES['myFile']['tmp_name'], $uploadFilePath)) {
        $response['success'] = true;
        $response['filePath'] = '../'.$uploadFilePath;
    } else {
        echo "error, upload file failed";
    }
} else {
    echo "no file";
}
header('Content-Type: application/json');
echo json_encode($response);

?>
