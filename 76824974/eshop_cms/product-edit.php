<?php
try {
    $product = $database->get_product_by_id($page["id"]);
} catch (MissingIdException $mie) {
    http_response_code(404);
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>article edit</title>
    <link rel="stylesheet" type="text/css" href="<?php echo APP_DIRECTORY ?>style.css">
</head>
<body>
    <div id="body">
    <form action="#"  enctype="multipart/form-data">
        <label for="name">Name: </label>
        <input type="hidden" id="add_time"  value="<?php echo $product->add_time?>">
        <input type="text" id="name" required maxlength="<?php echo NAME_MAX_LENGTH?>" value="<?php echo $product->name?>"><br>
        <p id="last modified">Added time: <?php echo $product->add_time?></p>
        <label for="kategory">Kategory</label><br>
        <input type="text" id="kategory" name="kategory" value="<?php echo $product->kategory?>"><br>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="15" value="<?php echo $product->description?>"><?php echo $product->description?></textarea><br>
        <label for="number_in_stock">Number in stock</label><br>
        <input type="text" id="number_in_stock" name="number_in_stock" value="<?php echo $product->number_in_stock?>"><br>
        <label for="image_url">Image</label><br>
        <!--<img border="0" src="<?php echo $product->image_url?>" alt="<?php echo $product->name?>" id="image" width="304" height="228"><br> -->
        <img src="<?php echo $product->image_url?>" alt="<?php echo $product->name?>" id="image" width="304" height="228"><br>
        <input id="image_url" type="file">
        <input type="hidden" id="image_path">
        <label for="width">Width</label><br>
        <input type="text" id="width" name="width" value="<?php echo $product->width?>"><br>
        <label for="height">Height</label>
        <input type="text" id="height" name="height" value="<?php echo $product->height?>">
        <label for="length">Length</label>
        <input type="text" id="length" name="length" value="<?php echo $product->length?>"><br>
        <label for="weight">Weight</label>
        <input type="text" id="weight" name="weight" value="<?php echo $product->weight?>"><br>
        <label for="material">Material</label>
        <input type="text" id="material" name="material" value="<?php echo $product->material?>"><br>
        <label for="color">Color</label>
        <input type="text" id="color" name="color" value="<?php echo $product->color?>"><br>
        <label for="price">Price</label>
        <input type="text" id="price" name="price" value="<?php echo $product->price?>"><br>
        <div class="buttons">
        <button type="button" id="saveButton" onclick="save_()">Save</button>
        <button type="button" id="backButton" onclick="backToProducts()">Back to products</button>
        </div>
    </form>
<script>    
    //console.log("tadyta stranka");
    var nameElement = document.getElementById("name");
    var kategoryElement = document.getElementById("kategory");
    var descriptionElement = document.getElementById("description");
    var numberInStockElement = document.getElementById("number_in_stock");
    var imageURLElement = document.getElementById("image_url");
    var addTimeElement = document.getElementById("add_time");
    var widthElement = document.getElementById("width");
    var heightElement = document.getElementById("height");
    var lengthElement = document.getElementById("length");
    var weightElement = document.getElementById("weight");
    var materialElement = document.getElementById("material");
    var colorElement = document.getElementById("color");
    var priceElement = document.getElementById("price");
    var imagePath =  document.getElementById("image_path");

    
    /*
    function enableCreateButton() {
        console.log("called func");
        
        var submitElement = document.getElementById("saveButton");
        //console.log("pred if");
        //if (nameElement.value != "" && nameElement.value.length <= <?php //echo NAME_MAX_LENGTH?>// && contentElement.value.length <= <?php //echo CONTENT_MAX_LENGTH?>//) {
            console.log("v podmince");
            submitElement.removeAttribute("disabled");
        }
        else {
            console.log("v else");
            submitElement.setAttribute("disabled", "disabled");
        }
    }
    */
    var date = new Date();
    /*
    // show file path
    document.getElementById('image_url').onchange = function () {
        alert('Selected file: ' + this.value);
    };
    */
    const handleImageUpload = event => {
    const files = event.target.files
    const formData = new FormData();

    formData.append('myFile', files[0]);
    formData.append("name",document.getElementById("name").value)
        console.log('form data: ',formData)
    fetch('./../images.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        //console.log("data: " + data.data);
        console.log("data---"+data.filePath)
        //download(data);
        // Usage
        //downloadBlob(data, './../images/1.jpg');

        data.name = "1.jpg";
        let path = data.filePath;
        console.log('path',path)
        document.getElementById("image").src = path;
        document.getElementById("image_path").value = path;
        data.lastModified = new Date();
    })
    .catch(error => {
        console.error(error)
    })
    }

    document.querySelector('#image_url').addEventListener('change', event => {
    handleImageUpload(event)
    });

    function download(downfile) {
        /*
        const tmpLink = document.createElement("a");
        const objectUrl = URL.createObjectURL(downfile);

        tmpLink.href = objectUrl;
        tmpLink.download = downfile.name;
        document.body.appendChild(tmpLink);
        tmpLink.click();

        document.body.removeChild(tmpLink);
        URL.revokeObjectURL(objectUrl);
        */
        browser.downloads.download({
            url: URL.createObjectURL(downfile),
            filename: "test/1.jpg",
            saveAs: false,
        })
    }
    function downloadBlob(blob, name = 'file.txt') {
    if (
      window.navigator && 
      window.navigator.msSaveOrOpenBlob
    ) return window.navigator.msSaveOrOpenBlob(blob);

    // For other browsers:
    // Create a link pointing to the ObjectURL containing the blob.
    const data = window.URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = data;
    link.download = name;

    // this is necessary as link.click() does not work on the latest firefox
    link.dispatchEvent(
      new MouseEvent('click', { 
        bubbles: true, 
        cancelable: true, 
        view: window 
      })
    );

    setTimeout(() => {
      // For Firefox it is necessary to delay revoking the ObjectURL
      window.URL.revokeObjectURL(data);
      link.remove();
    }, 100);
}


    

    //https://blog.51cto.com/zhezhebie/5445075 - can't name function as save()
    async function save_() {
        //console.log("addTimeElement,",addTimeElement.value)

        await fetch('<?php echo APP_DIRECTORY ?>product-save/<?php echo $page["id"]?>', {
            method: "POST",
            headers: {
                "content-type" : "application/json"
            },
            body: JSON.stringify({
                "name" : nameElement.value, 
                "kategory" : kategoryElement.value,
                "description" : descriptionElement.value,
                "number_in_stock" : numberInStockElement.value,
                "image_url" :imagePath.value ,
                /*"add_time" : date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds()+" "+date.getDate()+'-'+(date.getMonth()+1)+'-'+date.getFullYear(),*/
                'add_time' :addTimeElement.value+'',
                "width" : widthElement.value,
                "height" : heightElement.value,
                "length" : lengthElement.value,
                "weight" : weightElement.value,
                "material" : materialElement.value,
                "color" : colorElement.value,
                "price" : priceElement.value
            })
        });
        //debugger
        
        //console.log(addTime);
        window.location.href = "<?php echo APP_DIRECTORY ?>products";
                                        // "/~76824974/eshop_cms/products"
    }
    function backToProducts() {
        window.location.href = "<?php echo APP_DIRECTORY ?>products";
    }
</script>

</body>
</html>