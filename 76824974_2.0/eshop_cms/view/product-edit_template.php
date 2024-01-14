<body>
    <div id="body">
    <form action="#"  enctype="multipart/form-data">
        <label for="name">NAME TITLE</label>
        <input type="hidden" id="add_time"  value="PRODUCT ADD TIME">
        <input type="text" id="name" required maxlength="<?php echo $config->NAME_MAX_LENGTH?>" value="<?php echo $product->name ?>"><br>
        <p id="last modified">ADD TIME TITLE: <?php echo $product->add_time ?></p>
        <label for="kategory">KATEGORY TITLE</label><br>
        <input type="text" id="kategory" name="kategory" value="<?php echo $product->kategory ?>"><br>
        <label for="description">DESCRIPTION TITLE</label><br>
        <textarea id="description" name="description" rows="15" value="<?php echo $product->description?>">PRODUCT DESCRIPTION TITLE</textarea><br>
        <label for="number_in_stock">NUMBER IN STOCK TITLE</label><br>
        <input type="text" id="number_in_stock" name="number_in_stock" value="<?php echo $product->number_in_stock?>"><br>
        <label for="image_url">IMAGE TITLE</label><br>
        <img id="image" src="<?php echo $product->image_url?>" alt="<?php echo $product->name ?>" id="image" width="304" height="228"><br>
        <input id="image_url" type="file">
        <input type="hidden" id="image_path">
        <label for="width">WIDTH TITLE</label><br>
        <input type="text" id="width" name="width" value="<?php echo $product->width?>"><br>
        <label for="height">HEIGHT TITLE</label>
        <input type="text" id="height" name="height" value="<?php echo $product->height?>">
        <label for="length">LENGTH_TITLE</label>
        <input type="text" id="length" name="length" value="<?php echo $product->length?>"><br>
        <label for="weight">WEIGHT TITLE</label>
        <input type="text" id="weight" name="weight" value="<?php echo $product->weight?>"><br>
        <label for="material">MATERIAL TITLE</label>
        <input type="text" id="material" name="material" value="<?php echo $product->material?>"><br>
        <label for="color">COLOR TITLE</label>
        <input type="text" id="color" name="color" value="<?php echo $product->color?>"><br>
        <label for="price">PRICE TITLE</label>
        <input type="text" id="price" name="price" value="<?php echo $product->price?>"><br>
        <div class="buttons">
        <button type="button" id="saveButton" onclick="save_()">SAVE BUTTON TITLE</button>
        <button type="button" id="backButton" onclick="backToProducts()">BACK TO PRODUCTS TITLE</button>
        </div>
    </form>
    <div id="APP_DIRECTORY" data-app-directory='<?php echo $config->APP_DIRECTORY?>'></div>
    <div id="PAGE_ID" data-page-id='<?php echo $page["id"]?>'></div>
    <script type="text/javascript" src="<?php echo $config->APP_DIRECTORY?>/js/product-edit.js"></script>
</body>
</html>