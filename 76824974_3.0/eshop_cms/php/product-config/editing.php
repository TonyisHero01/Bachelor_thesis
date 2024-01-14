<?php
class Database {
    public $tableName;
    private $conn;
    
    function __construct($tableName, $conn) {
        $this->tableName = $tableName;
        $this->conn = $conn;
    }
    function check_login($username, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM User WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        if ($result->num_rows == 1) {
            [$username, $password, $position, $is_client] = $result->fetch_row();
            return new Login_Info($username, $password, $position, $is_client);
        }
        return false;
    }
    
    function contains_id($id): bool {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM $this->tableName WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row()[0] == 1;
        $stmt->close();
        return $exists;
    }
    function contains_name($name): bool {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM $this->tableName WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row()[0] == 1;
        $stmt->close();
        return $exists;
    }
    public function get_all_product_based_info() {
        $query = $this->conn->query("SELECT id, name, number_in_stock, add_time, price FROM $this->tableName ORDER BY id DESC");
        while($row = $query->fetch_row()) {
            [$id, $name, $number_in_stock, $add_time, $price] = $row;
            $product = new Product($name, $number_in_stock, $add_time, $price);
            $product->set_id($id);
            yield $product;
        }
    }
    public function get_product_by_id($product_id): Product {
        if (!$this->contains_id($product_id)) {
            throw new MissingIdException();
        }
        $stmt = $this->conn->prepare("SELECT * FROM $this->tableName WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        [$id, $name, $kategory, $description, $number_in_stock, $image_url, $add_time, $width, $height, $length, $weight, $material, $color, $price] = $stmt->get_result()->fetch_row();
        $stmt->close();
        $product = new Product($name, $number_in_stock, $add_time, $price);
        $product->set_all_params($kategory, $description, $image_url, $width, $height, $length, $weight, $material, $color);
        $product->set_id($id);
        return $product;
    }
    public function get_product_by_name($product_name): Product {
        if (!$this->contains_name($product_name)) {
            throw new MissingIdException();
        }
        $stmt = $this->conn->prepare("SELECT * FROM $this->tableName WHERE name = ?");
        $stmt->bind_param("s", $product_name);
        $stmt->execute();
        [$id, $name, $kategory, $description, $number_in_stock, $image_url, $add_time, $width, $height, $length, $weight, $material, $color, $price] = $stmt->get_result()->fetch_row();
        $stmt->close();
        $product = new Product($name, $number_in_stock, $add_time, $price);
        $product->set_all_params($kategory, $description, $image_url, $width, $height, $length, $weight, $material, $color);
        $product->set_id($id);
        return $product;
    }
    public function create($name, $number_in_stock, $add_time, $price): int {
        if ($name == "" || strlen($name) > NAME_MAX_LENGTH) {
            throw new WrongFormatException();
        }
        $name = htmlspecialchars($name);
        $stmt = $this->conn->prepare("INSERT INTO $this->tableName (name, number_in_stock, add_time, price) VALUES (?, ?, '" . $add_time ."', ?)");
        $stmt->bind_param("ssi", $name, $number_in_stock, $price);
        $stmt->execute();
        $stmt->close();

        $query = "SELECT MAX(id) FROM $this->tableName";
        $result = $this->conn->query($query);
        return $result->fetch_row()[0];
    }
    public function edit($product) {
        if (!$this->contains_id($product->id)) {
            throw new MissingIdException();
        }
        elseif ($product->name == "" || strlen($product->name) > NAME_MAX_LENGTH || strlen($product->description) > CONTENT_MAX_LENGTH) {
            throw new WrongFormatException();
        }
        $name = htmlspecialchars($product->name);
        $description = htmlspecialchars($product->description);

        $stmt = $this->conn->prepare("UPDATE $this->tableName SET name = ?, kategory = ?, description = ?, number_in_stock = ?, image_url = ?, add_time = ?, width = ?, height = ?, length = ?, weight = ?, material = ?, color = ?, price = ? WHERE id = ?");
        $stmt->bind_param("sssissiiiissii", $name, $product->kategory, $description, $product->number_in_stock, $product->image_url, $product->add_time, $product->width, $product->height, $product->length, $product->weight, $product->material, $product->color, $product->price, $product->id);
        $stmt->execute();
        $stmt->close();
    }
    public function delete($id) {
        if (!$this->contains_id($id)) {
            throw new MissingIdException();
        }
        $stmt = $this->conn->prepare("DELETE FROM $this->tableName WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
}
class MissingIdException extends Exception {
    
}
class WrongFormatException extends Exception {

}