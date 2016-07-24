<?php
include "../src/DoraConst.php";
include "../src/Packet.php";
include "../src/Client.php";

$config = array(
    array("ip" => "2.0.0.1", "port" => 9567),
);
//获取服务器的状态
//会使用getstat指定的ip进行工作
//define the mode
$mode = array("type" => 2, "ip" => "1.0.0.1", "port" => 9567);

$obj = new \DoraRPC\Client($config);
$obj->changeMode($mode);

$ret = $obj->getStat("127.0.0.1", 9567);
var_dump($ret);
