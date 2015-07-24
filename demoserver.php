<?php
include "dora-rpc/server.php";

class Server extends DoraRPCServer {
    function initServer($server){
        //the callback of the server init 附加服务初始化
        //such as swoole atomic table or buffer 可以放置swoole的计数器，table等
    }
    function dowork($param){
        //process you logical 业务实际处理代码仍这里
        //return the result 使用return返回处理结果
        return array("hehe"=>"ohyes");
    }

    function initTask($server, $worker_id){
        //require_once() 你要加载的处理方法函数等 what's you want load (such as framework init)
    }
}

$res = new Server();