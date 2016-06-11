<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/client.php";

$config = array(
    "group1" => array(
        array("ip" => "127.0.0.1", "port" => 9567),
        //array("ip"=>"127.0.0.1","port"=>9567), you can set more ,the client will random select one,to increase High availability
    ),
);
//define the mode
$mode = array("type" => 2, "ip" => "127.0.0.1", "port" => 9567);

$obj = new \DoraRPC\Client($config);
$obj->changeMode($mode);

for ($i = 0; $i < 100000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), \DoraRPC\DoraConst::SW_MODE_WAITRESULT, 1);
    var_dump($ret);
    //multi && async
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, \DoraRPC\DoraConst::SW_MODE_WAITRESULT, 1, "127.1.0.1", 9567);
    var_dump($ret);
}
