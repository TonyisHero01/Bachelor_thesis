<?php
require_once "constants.php";
require_once "./config/db_config.php";
require_once "index.php";

$html = file_get_contents("./html/admin_login_template.html");
$html = str_replace("{{APP_DIRECTORY}}", APP_DIRECTORY, $html);

echo $html;
?>
