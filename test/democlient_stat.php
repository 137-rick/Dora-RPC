<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/client.php";

$config = array(
    array("ip" => "127.0.0.1", "port" => 9567),
);

$obj = new \DoraRPC\Client($config);
$ret = $obj->getStat();
var_dump($ret);
