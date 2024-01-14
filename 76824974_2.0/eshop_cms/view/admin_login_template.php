<body>
    SELECT LANGUAGE:
    <form action="">
        <select id="languages" name="languages">
            <?php
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
            echo $html_options;
            ?>
        </select>
    </form>
    <form action="#" enctype="multipart/form-data">
        <label for="username">Username: </label>
        <input type="text" id="username" name="username">
        <input type="password" id="password" name="password">
        <button type="button" id="loginButton" onclick="login_()">Login</button>
    </form>
    <div id="APP_DIRECTORY" data-app-directory='<?php echo $config->APP_DIRECTORY?>'></div>
    <div id="PRODUCT_MANAGER"data-product-manager='<?php echo $config->PRODUCT_MANAGER?>'></div>
    <script type="text/javascript" src="./js/admin_login.js"></script>
</body>
