<?php
$html = file_get_contents("./html/backend-content-translation_template.html");

if (isset($page["id"])) {
    $filename = $page["id"] . ".json";

    $filePath = "locale/" . $filename;

    if (file_exists($filePath)) {
        // 文件存在，继续读取和处理
        $jsonData = file_get_contents($filePath);

        // ...
    } else {
        // 文件不存在，记录错误
        error_log("File does not exist: " . $filePath);
        // 处理文件不存在的情况
    }

    // 读取 JSON 文件内容
    $jsonData = file_get_contents($filePath);

    // 解码 JSON 数据
    $decodedData = json_decode($jsonData, true); // 设置第二个参数为 true 表示解码为关联数组

    // 检查解码是否成功
    if ($decodedData === null && json_last_error() !== JSON_ERROR_NONE) {
        echo 'Error decoding JSON: ' . json_last_error_msg();
        exit;
    }
    $html = str_replace("{{APP_DIRECTORY}}", APP_DIRECTORY, $html);
    for ($i = 0; $i < count(ELEMENT_IDS); $i++) {
        $element_id = ELEMENT_IDS[$i];
        $element_content = null;
        error_log($i);
        if ($i == 0) {
            $element_content = substr($filename, 0, -5);
        } else if ($i >= 1 && $i <= 2) {
            $element_content = $decodedData["login"][$element_id];
        } else if ($i >= 3 && $i <= 15) {
            $element_content = $decodedData["product_manage"]["product_property"][$element_id];
        } else if ($i >= 16 && $i <= 25) {
            $element_content = $decodedData["function"][$element_id];
        } else {
            $element_content = $decodedData["title"][$element_id];
        }
        //error_log(HTML_TRANSLATION_ELEMENTS_TO_CHANGE[$i]);
        $html = str_replace(HTML_TRANSLATION_ELEMENTS_TO_CHANGE[$i], $element_content, $html);
    }
} else {
    $html = str_replace("{{APP_DIRECTORY}}", APP_DIRECTORY, $html);
    foreach (HTML_TRANSLATION_ELEMENTS_TO_CHANGE as $translation_element) {
        $html = str_replace($translation_element, "", $html);
    }
}


echo $html;
