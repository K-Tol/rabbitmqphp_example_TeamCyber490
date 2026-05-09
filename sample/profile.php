<?php
    require_once('path.inc');
    require_once('get_host_info.inc');
    require_once('rabbitMQLib.inc');

    try{
        if (!isset($_POST)) {
            echo json_encode(["ok" => false, "message" => "NO POST MESSAGE SET, POLITELY FUCK OFF"]);
            exit(0);
        }
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            echo json_encode(["ok" => false, "message" => "Only type POST allowed"]);
            exit(0);
        }
    } catch (Exception $e) {
        echo $e->getMessage();
}
?>