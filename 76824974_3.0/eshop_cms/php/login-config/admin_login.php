<?php
require_once "constants.php";
require_once "./config/db_config.php";
require_once "index.php";
function generateLanguageOption($language) {
    ob_start();
    if ($language == "en") {
        ?>
        <option value="<?php echo $language?>" selected><?php echo $language?></option>
        <?php
    } else {
    ?>
    <option value="<?php echo $language?>"><?php echo $language?></option>
    <?php
    }
    return ob_get_clean();
}
try {
    $html = file_get_contents("./html/admin_login_template.html");
    $html = str_replace("{{APP_DIRECTORY}}", APP_DIRECTORY, $html);
    $folderPath = "./locale";
    $files = scandir($folderPath);
    $files = array_filter($files, function ($files) use ($folderPath) {
        return is_file($folderPath . '/' . $files);
    });
    $html_options = '';
    foreach ($files as $file) {
        if (!is_file($file)) {
            $language = substr($file, 0, -5);
            $html_options .= generateLanguageOption($language);
        }
    }

    
    $html = str_replace("{{LANGUAGE_OPTIONS}}", $html_options, $html);

    echo $html;
}
catch (Exception $e) {
    error_log($e->getMessage());
}
?>
