<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/monitor.php";


//redis for service discovery register
//when you on product env please prepare more redis to registe service for high available
$redisconfig = array(
    array(//first reporter
        "ip" => "127.0.0.1",
        "port" => "6379",
    ),
    array(//next reporter
        "ip" => "127.0.0.1",
        "port" => "6379",
    ),
);

//ok start server
$res = new \DoraRPC\Monitor("0.0.0.0", 9569, $redisconfig, "./client.conf.php");
