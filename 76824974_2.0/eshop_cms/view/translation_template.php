<body>
    <h1>Language List</h1>
    <table>
        <?php

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
        echo $language_list_html;
        ?>
    </table>
    <div class="buttons">
        <button id="previousPageButton" type="button" onclick="previousPage()">Previous</button>
        <button id="nextPageButton" type="button" onclick="nextPage()">Next</button>
        <span id="pageCount">Page Count </span>
        <button class="popup" onclick="createNewLanguage()">Add Language</button>
    </div>
    <div id="APP_DIRECTORY" data-app-directory='<?php echo $config->APP_DIRECTORY?>'></div>
    <div id="MAX_ARTICLES_COUNT_PER_PAGE" data-articles-count-per-page='<?php echo $config->MAX_ARTICLES_COUNT_PER_PAGE?>'></div>
    <div id="languageIds" data-language-ids='<?php echo $language_ids?>'></div>
    <div class="buttons">
    <script type="text/javascript" src="./js/translation.js"></script>
</body>