<?php
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);
$name = $input["name"] ?? "";
$kategory = $input["kategory"] ?? "";
$description = $input["description"] ?? "";
$number_in_stock = $input["number_in_stock"] ?? "";
$image_url = $input["image_url"] ?? "";
$add_time = $input["add_time"] ?? "";
$width = $input["width"] ?? "";
$height = $input["height"] ?? "";
$length = $input["length"] ?? "";
$weight = $input["weight"] ?? "";
$material = $input["material"] ?? "";
$color = $input["color"] ?? "";
$price = $input["price"] ?? "";

$product = new Product($name, $number_in_stock, $add_time.'', $price);
$product->set_all_params($kategory, $description, $image_url, $width, $height, $length, $weight, $material, $color);
$product->set_id($page["id"]);

try {
    $database->edit($product);
    echo "edit";
}
catch (MissingIdException $mie) {
    echo "404";
    http_response_code(404);
} 
catch (WrongFormatException $wfe) {
    echo "400";
    http_response_code(400);
}