<?php
try {
    $database->delete($page["id"]);
} catch (MissingIdException $mie) {
    http_response_code(404);
}