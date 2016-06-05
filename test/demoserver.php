<?php
include "../src/doraconst.php";
include "../src/packet.php";
include "../src/server.php";

class Server extends \DoraRPC\Server
{

    //all of this config for optimize performance
    //以下配置为优化服务性能用，请实际压测调试
    protected $externalConfig = array(

        //to improve the accept performance ,suggest the number of cpu X 2
        //如果想提高请求接收能力，更改这个，推荐cpu个数x2
        'reactor_num' => 16,

        //packet decode process,change by condition
        //包处理进程，根据情况调整数量
        'worker_num' => 20,

        //the number of task logical process progcessor run you business code
        //实际业务处理进程，根据需要进行调整
        'task_worker_num' => 200,

        'daemonize' => false,

        'log_file' => '/tmp/sw_server.log',

        'task_tmpdir' => '/tmp/swtasktmp/',
    );

    function initServer($server)
    {
        //the callback of the server init 附加服务初始化
        //such as swoole atomic table or buffer 可以放置swoole的计数器，table等
    }

    function doWork($param)
    {
        //process you logical 业务实际处理代码仍这里
        //return the result 使用return返回处理结果
        return array("hehe" => "ohyes");
    }

    function initTask($server, $worker_id)
    {
        //require_once() 你要加载的处理方法函数等 what's you want load (such as framework init)
    }
}

//this server belong which logical group
//different group different api(for Isolation)
$groupConfig = array(
    "list" => array(
        "group1",
        "group2",
    ),
);

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
$res = new Server("0.0.0.0", 9567, $groupConfig, $redisconfig);
