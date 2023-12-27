<?php
require_once "../../constants.php";
$response = array();

if ($_FILES['myFile']) {
    $uploadDir = '../../images/';

    $fileInfo = pathinfo($_FILES['myFile']['name']);
    $fileExtension = $fileInfo['extension'];

    $uniqueFileName = $_POST['name'] . '.' . $fileExtension;

    $uploadFilePath = $uploadDir . $uniqueFileName;
    error_log($uploadFilePath);

    if (move_uploaded_file($_FILES['myFile']['tmp_name'], $uploadFilePath)) {
        $response['success'] = true;
        $uploadDir = '../images/';
        $uploadFilePath = $uploadDir . $uniqueFileName;
        $response['filePath'] = './'.$uploadFilePath;
        $response['fileName'] = $uniqueFileName;
    } else {
        error_log("error, upload file failed");
    }
} else {
    error_log("no file");
}
header('Content-Type: application/json');
error_log(json_encode($response));
echo json_encode($response);

?>
