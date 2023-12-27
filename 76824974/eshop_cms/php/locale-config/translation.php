<?php
require_once "constants.php";
require_once "./config/db_config.php";
require_once "index.php";

$folderPath = './locale';

$fileNames = array_filter(scandir($folderPath), function($item) use ($folderPath) {
    return is_file($folderPath . '/' . $item);
});

function generateLanguageRow($filename,$folderPath) {
    ob_start();?>
    <tr id="<?php echo $filename?>">
        <td class="filename"><?php echo $filename?></td>
        <td class="lastModifiedTime"><?php echo getLastModifiedTime($folderPath . "/" . $filename . ".json")?></td>
        <td class="button"><span class="edit-button" type="button" onclick='edit(<?php echo json_encode($filename)?>)'>Edit</span></td>
        <td class="button"><span class="delete-button" type="button" onclick='delete_(<?php echo json_encode($filename)?>)'>Delete</span></td>
    </tr>
    <?php
    return ob_get_clean();
}
function getLastModifiedTime($filePath) {
    $lastModifiedTime = filemtime($filePath);
    return date('Y-m-d H:i:s', $lastModifiedTime);
}
$language_ids = [];
$language_ids_str = "";
$language_list_html = '';
foreach ($fileNames as $fileName) {
    $fileName = substr($fileName, 0, -5);
    $language_ids[] = $fileName;
    $language_ids_str .= "." . $fileName . ".";
    $language_list_html .= generateLanguageRow($fileName, $folderPath);
}

try {
    $html = file_get_contents('./html/translation_template.html');
    $html = str_replace('{{LANGUAGE_LIST}}', $language_list_html, $html);
    $html = str_replace('{{APP_DIRECTORY}}', APP_DIRECTORY, $html);
    $html = str_replace('{{MAX_ARTICLES_COUNT_PER_PAGE}}', MAX_ARTICLES_COUNT_PER_PAGE, $html);
    $html = str_replace('{{LANGUAGE_IDS}}', json_encode($language_ids), $html);
    echo $html;
} catch (Exception $e){
    echo "An error occurred: " . $e->getMessage();
}
