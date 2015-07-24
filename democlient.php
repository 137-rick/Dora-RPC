<?php
include "dora-rpc/client.php";

$obj = new DoraRPCClient( "127.0.0.1", 9567);
for ($i = 0; $i < 100000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), false,1);
    var_dump($ret);

    //multi && rsync
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, true,1);
    var_dump($ret);
}