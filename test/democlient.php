<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/client.php";

$config = array(
    array("ip" => "127.0.0.1", "port" => 9567),
    //array("ip"=>"127.0.0.1","port"=>9567), you can set more ,the client will random select one,to increase High availability
);

$maxrequest = 0;

$obj = new \DoraRPC\Client($config);
for ($i = 0; $i < 10000; $i++) {
    //echo $i . PHP_EOL;

    $time = microtime(true);
    //single && sync
    $ret = $obj->singleAPI("abc" . $i, array(234, $i), true, 1);
//    var_dump($ret);

    //single call && async
    $ret = $obj->singleAPI("abc" . $i, array(234, $i), false, 1);
    //var_dump($ret);

    //multi && sync
    $data = array(
        "oak" => array("name" => "oakdf" . $i, "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff" . $i, "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, false, 1);
    //var_dump($ret);

    //multi && async
    $data = array(
        "oak" => array("name" => "oakdf" . $i, "param" => array("dsaf" => "32111321")),
        "cd" => array("name" => "oakdfff" . $i, "param" => array("codo" => "f11ds")),
    );
    $ret = $obj->multiAPI($data, true, 1);
    //var_dump($ret);
    $time = bcsub(microtime(true), $time, 5);
    if ($time > $maxrequest) {
        $maxrequest = $time;
    }
    echo $i . " cost:" . $time . PHP_EOL;
    //var_dump($ret);
}
echo "max:".$maxrequest.PHP_EOL;
