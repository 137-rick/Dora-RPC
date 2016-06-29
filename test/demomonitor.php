<?php
include "../src/Doraconst.php";
include "../src/Packet.php";
include "../src/Monitor.php";

$config = array(
    //redis for service discovery register
    //when you on product env please prepare more redis to registe service for high available
    "Discovery" => array(
        //first reporter
        array(
            "ip" => "127.0.0.1",
            "port" => "6379",
        ),
        //next reporter
        array(
            "ip" => "127.0.0.1",
            "port" => "6379",
        ),
    ),
    //general config path for client
    "Config" => "./client.conf.php",

    //log monitor path
    "Log" => array(
        "tag1" => array("tag" => "", "path" => "./log/"),
        "tag2" => array("tag" => "", "path" => "./log2/"),
    ),
);

//ok start server
$res = new \DoraRPC\Monitor("0.0.0.0", 9569, $config);
