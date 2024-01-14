<body>
    <div id="body">
    <form action="#" enctype="multipart/form-data">
        <label for="language">Language: </label>
        <input type="text" id="language" name="language" value="  LANGUAGE  "><br>
        <label for="username">Username</label><br>
        <input type="text" id="username" name="username" value="  USERNAME  "><br>
        <label for="password">Password</label><br>
        <input type="text" id="password" name="password" value="  PASSWORD  "><br>
        <label for="product_name">Product Name</label><br>
        <input type="text" id="product_name" name="product_name" value="  PRODUCT_NAME  "><br>
        <!--<label for="SKU">SKU</label><br>
        <input type="text" id="SKU" name="SKU" value="  SKU  "><br>-->
        <label for="add_time">Add Time</label><br>
        <input type="add_time" id="add_time" name="add_time" value="  ADD_TIME  "><br>
        <label for="kategory">Kategory</label><br>
        <input type="kategory" id="kategory" name="kategory" value="  KATEGORY  "><br>
        <label for="description">Description</label><br>
        <input type="description" id="description" name="description" value="  DESCRIPTION  "><br>
        <label for="number_in_stock">Number in Stock</label><br>
        <input type="number_in_stock" id="number_in_stock" name="number_in_stock" value="  NUMBER_IN_STOCK  "><br>
        <label for="image">Image</label><br>
        <input type="text" id="image" name="image" value="  IMAGE  "><br>
        <label for="width">Width</label><br>
        <input type="width" id="width" name="width" value="  WIDTH  "><br>
        <label for="height">Height</label>
        <input type="text" id="height" name="height" value="  HEIGHT  ">
        <label for="length">Length</label>
        <input type="text" id="length" name="length" value="  LENGTH  "><br>
        <label for="weight">Weight</label>
        <input type="text" id="weight" name="weight" value="  WEIGHT  "><br>
        <label for="material">Material</label>
        <input type="text" id="material" name="material" value="  MATERIAL  "><br>
        <label for="color">Color</label>
        <input type="text" id="color" name="color" value="  COLOR  "><br>
        <label for="price">Price</label>
        <input type="text" id="price" name="price" value="  PRICE  "><br>
        <label for="edit">Edit</label>
        <input type="text" id="edit" name="edit" value="  EDIT  "><br>
        <label for="delete">Delete</label>
        <input type="text" id="delete" name="delete" value="  DELETE  "><br>
        <label for="create_product">Create Product</label>
        <input type="text" id="create_product" name="create_product" value="  CREATE_PRODUCT  "><br>
        <label for="create">Create</label>
        <input type="text" id="create" name="create" value="  CREATE  "><br>
        <label for="cancel">Cancel</label>
        <input type="text" id="cancel" name="cancel" value="CANCEL"><br>
        <label for="next">Next</label>
        <input type="text" id="next" name="next" value="NEXT"><br>
        <label for="previous">Previous</label>
        <input type="text" id="previous" name="previous" value="PREVIOUS"><br>
        <label for="save">Save</label>
        <input type="text" id="save" name="save" value="SAVE"><br>
        <label for="back_to_product_list">Back to Product List</label>
        <input type="text" id="back_to_product_list" name="back_to_product_list" value="BACK_TO_PRODUCT_LIST"><br>
        <label for="login">Login</label>
        <input type="text" id="login" name="login" value="LOGIN"><br>
        <label for="product_list">Product List</label>
        <input type="text" id="product_list" name="product_list" value="PRODUCT_LIST"><br>
        <div class="buttons">
        <button type="button" id="saveButton" onclick="save_()">Save</button>
        <button type="button" id="backButton" onclick="backToLanguageList()">Back to Language List</button>
        </div>
    </form>
    <div id="APP_DIRECTORY" data-app-directory="  APP_DIRECTORY  "></div>
    <script type="text/javascript" src="<?php echo $config->APP_DIRECTORY?>/js/backend-content-translation.js"></script>
</div></body>
