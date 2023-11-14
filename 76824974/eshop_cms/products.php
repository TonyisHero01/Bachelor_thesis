<?php
require_once "constants.php";
require_once "db_config.php";
require_once "index.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>product list</title>
    <link rel="stylesheet" type="text/css" href="<?php echo APP_DIRECTORY ?>style.css">
</head>
<body>
    <h1>Product List</h1>
    <table>
        <?php
            $products = $database->get_all_product_based_info();
            $product_ids = [];
            foreach ($products as $product) {
                $product_ids[] = $product->id;
                ?>
                <tr id="<?php echo $product->id?>">
                    <td class="product-name"><?php echo $product->name?></td>
                    <td class="number-in-stock"><?php echo $product->number_in_stock . "ks"?></td>
                    <td class="add-time"><?php echo $product->add_time?></td>
                    <td class="price"><?php echo $product->price?></td>
                    <td class="button"><span class="edit-button" type="button" onclick="edit(<?php echo $product->id?>)">Edit</span></td>
                    <td class="button"><span class="delete-button" type="button" onclick="delete_(<?php echo $product->id?>)">Delete</span></td>
                </tr>
            <?php
            }
        ?>
    </table>
    <div class="buttons">
        <button id="previousPageButton" type="button" onclick="previousPage()">Previous</button>
        <button id="nextPageButton" type="button" onclick="nextPage()">Next</button>
        <span id="pageCount">Page Count </span>
        <button class="popup" onclick="openCreateForm()">Create Product</button>
    </div>

    <div class="popuptext" id="myPopup" style="display: none;">
        <form action="#" oninput="enableCreateButton()">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required maxlength="<?php echo NAME_MAX_LENGTH?>"><br>
            <label for="number_in_stock">Number in stock</label>
            <input type="text" id="number_in_stock" name="number_in_stock">
            <label for="price">Price in CZK</label>
            <input type="text" id="price" name="price">
            <button type="button" id="createButton" onclick="create()" disabled>Create</button>
            <button id="cancelButton" type="button" onclick="cancelCreateForm()" style="display: none;">Cancel</button>
        </form>
    </div>

    <script>
        var pageNumber = 0;
        var pageCountText = document.getElementById("pageCount");
        var ids = [<?php 
            foreach($product_ids as $a_id) {
                echo $a_id . ",";
            }
            ?>];
    
        var previousPageButton = document.getElementById("previousPageButton");
        var nextPageButton = document.getElementById("nextPageButton");
        
        showPage();
        function nextPage() {
            pageNumber++;
            showPage();
        }
        function previousPage() {
            pageNumber--;
            showPage();
        }
        
        function showPage() {
            var lastPageNumber = Math.trunc((ids.length-1) / <?php echo MAX_ARTICLES_COUNT_PER_PAGE?>);
            pageCountText.textContent = "Page Count " + (lastPageNumber+1);
            if (pageNumber == lastPageNumber+1) {
                pageNumber--;
            }
            let start = <?php echo MAX_ARTICLES_COUNT_PER_PAGE?> * pageNumber;
            let end = start + <?php echo MAX_ARTICLES_COUNT_PER_PAGE?>;
            for (let i = 0; i < ids.length; i++) {
                let tableRow = document.getElementById(ids[i]);
                if (i >= start && i < end) {
                    //tableRow.removeAttribute("style");
                    tableRow.style.removeProperty("display");
                }
                else {
                    //tableRow.setAttribute("style", "display: none;");
                    tableRow.style.display = "none";
                }
            }
            if (pageNumber == 0) {
                previousPageButton.style.visibility = "hidden";
            }
            else {
                previousPageButton.style.visibility = "visible";
            }
            if (pageNumber == lastPageNumber) {
                nextPageButton.style.visibility = "hidden";
            }
            else {
                nextPageButton.style.visibility = "visible";
            }
        }
        var popup = document.getElementById("myPopup");
        var cancelButton = document.getElementById("cancelButton");
        function openCreateForm() {
            popup.removeAttribute("style");
            cancelButton.removeAttribute("style");
        }
        
        function cancelCreateForm() {
            console.log("cancel");
            popup.setAttribute("style", "display: none;");
            cancelButton.setAttribute("style", "display: none;");
            //window.location.href = "<?php echo APP_DIRECTORY?>products;
        }
        var nameElement = document.getElementById("name");
        var numberInStockElement = document.getElementById("number_in_stock");
        var priceElement = document.getElementById("price");
        function enableCreateButton () {
            console.log("called func");
            
            var submitElement = document.getElementById("createButton");
            if (nameElement.value != "" && nameElement.value.length <= 32 & numberInStockElement != "" & priceElement != "") {
                submitElement.removeAttribute("disabled");
            }
            else {
                submitElement.setAttribute("disabled", "disabled");
            }
        }
        async function create() {
            console.log("funguje create");
            console.log("<?php echo APP_DIRECTORY?>product-create/");
            var response = await fetch("<?php echo APP_DIRECTORY?>product-create/",{
                method: "POST",
                headers: {
                'content-type' : 'application/json'
                },
                body: JSON.stringify({
                "name" : nameElement.value,
                "number_in_stock" : numberInStockElement.value,
                "add_time" : "<?php echo date("H:i:s d.m.Y") ?>",
                "price" : priceElement.value
                })
            });
            console.log("<?php echo date("H:i:s d.m.Y") ?>");
            //console.log(response.status);
            //console.log(await response);
            
            try {
                var responseData = await response.json();
                console.log(responseData);
                var id = responseData.id;
                console.log("ID:", id);
                window.location.href = "<?php echo APP_DIRECTORY?>product-edit/" + id;
            } catch (error) {
                console.error("Error parsing JSON response:", error);
            }
        }

        function show(id) {
            window.location.href = "<?php echo APP_DIRECTORY?>product/" + id;
        }
        function edit(id) {
            window.location.href = "<?php echo APP_DIRECTORY?>product-edit/" + id;
        }
        function delete_(id) {
            var response = fetch("<?php echo APP_DIRECTORY?>product-delete/" + id, {
                method: "DELETE"
            });
            var row = document.getElementById(id);
            row.remove();
            ids.splice(ids.indexOf(id), 1);
            showPage();
        }
    </script>

</body>
</html>