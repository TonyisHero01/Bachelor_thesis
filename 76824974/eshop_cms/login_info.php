<?php
class Login_Info {
    public $username;
    public $password;
    public $position;
    public $is_client;
    function __construct($username, $password, $position, $is_client) {
        $this->username = $username;
        $this->password = $password;
        $this->position = $position;
        $this->is_client = $is_client;
    }

}