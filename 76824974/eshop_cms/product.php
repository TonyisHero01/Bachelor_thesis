<?php
class Product {
    public $id;
    public $name;
    public $kategory;
    public $description;
    public $number_in_stock;
    public $image_url;
    public $add_time;
    public $width;
    public $height;
    public $length;
    public $weight;
    public $material;
    public $color;
    public $price;
    function __construct($name, $number_in_stock, $add_time, $price) {
        $this->name = $name;
        $this->number_in_stock = $number_in_stock;
        $this->add_time = $add_time;
        $this->price = $price;
    }
    function set_id($id) {
        $this->id = $id;
    }
    function set_kategory($kategory) {
        $this->kategory = $kategory;
    }
    function set_description($description) {
        $this->description = $description;
    }
    function set_image_url($image_url) {
        $this->image_url = $image_url;
    }
    function set_width($width) {
        $this->width = $width;
    }
    function set_height($height) {
        $this->height = $height;
    }
    function set_length($length) {
        $this->length = $length;
    }
    function set_weight($weight) {
        $this->weight = $weight;
    }
    function set_color($color) {
        $this->color = $color;
    }
    function set_material($material) {
        $this->material = $material;
    }
    function set_all_params($kategory, $description, $image_url, $width, $height, $length, $weight, $material, $color) {
        $this->set_kategory($kategory);
        $this->set_description($description);
        $this->set_image_url($image_url);
        $this->set_width($width);
        $this->set_height($height);
        $this->set_length($length);
        $this->set_weight($weight);
        $this->set_color($color);
        $this->set_material($material);
    }
}