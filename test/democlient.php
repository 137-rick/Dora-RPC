<?php
include "../dora-rpc/client.php";

$config = array(
    array("ip" => "127.0.0.1", "port" => 9567),
    //array("ip"=>"127.0.0.1","port"=>9567), you can set more ,the client will random select one,to increase High availability
);

$obj = new \DoraRPC\Client\Client($config);
for ($i = 0; $i < 100000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), true, 1);
    var_dump($ret);

    //multi && rsync
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, true, 1);
    var_dump($ret);
}
