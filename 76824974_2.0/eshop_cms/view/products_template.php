<body>
    <h1>PRODUCT LIST TITLE</h1>
    <table>
        <?php
        $products = $database->get_all_product_based_info();
        $product_ids = [];
        foreach ($products as $product) {
            $product_ids[] = intval($product->id);
            ?>
            <tr id="<?php echo $product->id?>">
                <td class="product-name"><?php echo $product->name?></td>
                <td class="number-in-stock"><?php echo $product->number_in_stock . "ks"?></td>
                <td class="add-time"><?php echo $product->add_time?></td>
                <td class="price"><?php echo $product->price?></td>
                <td class="button"><span class="edit-button" type="button" onclick="edit(<?php echo $product->id?>)">EDIT BUTTON TITLE</span></td>
                <td class="button"><span class="delete-button" type="button" onclick="delete_(<?php echo $product->id?>)">DELETE BUTTON TITLE</span></td>
            </tr>
            <?php
        }
        ?>
    </table>
    <div id="productIds" data-product-ids=<?php echo json_encode($product_ids) ?>></div>
    <div id="NAME_MAX_LENGTH" data-name-max-length=<?php echo $config->NAME_MAX_LENGTH ?>></div>
    <div id="CONTENT_MAX_LENGTH" data-content-max-length=<?php echo $config->CONTENT_MAX_LENGTH ?>></div>
    <div id="APP_DIRECTORY" data-app-directory=<?php echo $config->APP_DIRECTORY ?>></div>
    <div id="MAX_ARTICLES_COUNT_PER_PAGE" data-articles-count-per-page=<?php echo $config->MAX_ARTICLES_COUNT_PER_PAGE ?>></div>
    <div class="buttons">
        <button id="previousPageButton" type="button" onclick="previousPage()">PREVIOUS BUTTON TITLE</button>
        <button id="nextPageButton" type="button" onclick="nextPage()">NEXT BUTTON TITLE</button>
        <span id="pageCount">Page Count </span>
        <button class="popup" onclick="openCreateForm()">CREATE PRODUCT BUTTON TITLE</button>
    </div>

    <div class="popuptext" id="myPopup" style="display: none;">
        <form action="#" oninput="enableCreateButton()">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required maxlength="<?php echo $config->NAME_MAX_LENGTH ?>"><br>
            <label for="number_in_stock">Number in stock</label>
            <input type="text" id="number_in_stock" name="number_in_stock">
            <label for="price">Price in CZK</label>
            <input type="text" id="price" name="price">
            <button type="button" id="createButton" onclick="create()" disabled>CREATE BUTTON TITLE</button>
            <button id="cancelButton" type="button" onclick="cancelCreateForm()" style="display: none;">CANCEL BUTTON TITLE</button>
        </form>
    </div>
    <script type="text/javascript" src="<?php echo $config->APP_DIRECTORY ?>/js/products.js"></script>
</body>
</html>