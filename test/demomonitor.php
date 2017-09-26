<?php
include "../src/DoraConst.php";
include "../src/Packet.php";
include "../src/Monitor.php";

$config = array(
    //redis for service discovery register
    //when you on product env please prepare more redis to registe service for high available
    //此功能用于服务发现的客户端，这个进程会定期从指定的redis内获取所有可用app服务的列表，并定期更新可用组的服务器列表
    //会自动剔除超过一定时间没有上报状态的服务器，后期这个服务将会增加分布式日志收集服务
    "discovery" => array(
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
    "config" => "./client.conf.php",

    //log monitor path
    "log" => array(
        "tag1" => array("tag" => "", "path" => "./log/"),
        "tag2" => array("tag" => "", "path" => "./log2/"),
    ),
);

//ok start server
$monitor = new \DoraRPC\Monitor("0.0.0.0", 9569, $config);

$monitor->start();
