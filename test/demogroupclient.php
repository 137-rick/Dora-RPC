<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/groupclient.php";

$config = "client.conf.php";

$obj = new \DoraRPC\GroupClient($config);

$ret = $obj->singleAPI("abc", array(123, 123), "group1", true, 1);
var_dump($ret);

for ($i = 0; $i < 1000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), "group1", true, 1);
    var_dump($ret);

    //single call && async
    $ret = $obj->singleAPI("abc", array(234, $i), "group1", false, 1);
    var_dump($ret);

    //multi && sync
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, "group1", false, 1);
    var_dump($ret);

    //multi && async
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "32111321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "f11ds")),
    );
    $ret = $obj->multiAPI($data, "group1", true, 1);
    var_dump($ret);
}
