<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/client.php";

/*
$config = array(
    "group1" => array(
        array("ip" => "127.0.0.1", "port" => 9567),
        //array("ip"=>"127.0.0.1","port"=>9567), you can set more ,the client will random select one,to increase High availability
    ),
);*/
//or
$config = include("client.conf.php");

//define the mode
$mode = array("type" => 1, "group" => "group1");

$maxrequest = 0;

//new obj
$obj = new \DoraRPC\Client($config);

//change connect mode
$obj->changeMode($mode);

for ($i = 0; $i < 10000; $i++) {
    //echo $i . PHP_EOL;

    //---------single
    $time = microtime(true);

    //single && sync
    $ret = $obj->singleAPI("/module_a/abc" . $i, array("mark" => 234, "foo" => $i), \DoraRPC\DoraConst::SW_MODE_WAITRESULT, 1);
    var_dump("single sync", $ret);

    //single call && async
    $ret = $obj->singleAPI("/module_b/abc" . $i, array("yes" => 21321, "foo" => $i), \DoraRPC\DoraConst::SW_MODE_NORESULT, 1);
    var_dump("single async", $ret);

    //single call && async
    $ret = $obj->singleAPI("/module_c/abd" . $i, array("yes" => 233, "foo" => $i), \DoraRPC\DoraConst::SW_MODE_ASYNCRESULT, 1);
    var_dump("single async result", $ret);

    //---------multi

    //multi && sync
    $data = array(
        "oak" => array("name" => "/module_c/dd" . $i, "param" => array("uid" => "ff")),
        "cd" => array("name" => "/module_f/ef" . $i, "param" => array("pathid" => "fds")),
    );
    $ret = $obj->multiAPI($data, \DoraRPC\DoraConst::SW_MODE_WAITRESULT, 1);
    var_dump("multi sync", $ret);

    //multi && async
    $data = array(
        "oak" => array("name" => "/module_d/oakdf" . $i, "param" => array("dsaf" => "32111321")),
        "cd" => array("name" => "/module_e/oakdfff" . $i, "param" => array("codo" => "f11ds")),
    );
    $ret = $obj->multiAPI($data, \DoraRPC\DoraConst::SW_MODE_NORESULT, 1);
    var_dump("multi async", $ret);

    //multi && async
    $data = array(
        "oak" => array("name" => "/module_a/oakdf" . $i, "param" => array("dsaf" => "11")),
        "cd" => array("name" => "/module_b/oakdfff" . $i, "param" => array("codo" => "f11ds")),
    );
    $ret = $obj->multiAPI($data, \DoraRPC\DoraConst::SW_MODE_ASYNCRESULT, 1);
    var_dump("multi async result", $ret);

    //get all the async result
    $data = $obj->getAsyncData();
    var_dump("allresult", $data);
    //compare each request
    $time = bcsub(microtime(true), $time, 5);
    if ($time > $maxrequest) {
        $maxrequest = $time;
    }
    echo $i . " cost:" . $time . PHP_EOL;
    //var_dump($ret);
}
echo "max:" . $maxrequest . PHP_EOL;
