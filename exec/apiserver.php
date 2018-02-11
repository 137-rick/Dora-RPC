<?php

require_once "init.php";

use DoraRPC\Layout\BackEnd\APIServer;

$server = new APIServer("127.0.0.1", 9501);
$server->loadConfig("apiserver.conf");
$server->start();