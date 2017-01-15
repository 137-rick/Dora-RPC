<?php
include "../src/DoraConst.php";
include "../src/Packet.php";
include "../src/BackEndServer.php";
include "../src/LogAgent.php";

class APIServer extends \DoraRPC\BackEndServer
{

    function initServer($server)
    {
        //the callback of the server init 附加服务初始化
        //such as swoole atomic table or buffer 可以放置swoole的计数器，table等
    }

    function doWork($param)
    {
        //process you logical 业务实际处理代码仍这里
        //return the result 使用return返回处理结果
        //throw new Exception("asbddddfds",1231);
        \DoraRPC\LogAgent::recordLog(\DoraRPC\DoraConst::LOG_TYPE_INFO, "dowork", __FILE__, __LINE__, array("esfs"));
        return array("hehe" => "ohyes123");
    }

    function initTask($server, $worker_id)
    {
        //require_once() 你要加载的处理方法函数等 what's you want load (such as framework init)
    }
}

//ok start server
$server = new APIServer("0.0.0.0", 9567, 9566);

$server->configure(array(
    'tcp' => array(),
    'http' => array(
        //to improve the accept performance ,suggest the number of cpu X 2
        //如果想提高请求接收能力，更改这个，推荐cpu个数x2
        'reactor_num' => 8,

        //packet decode process,change by condition
        //包处理进程，根据情况调整数量，推荐cpu个数x2
        'worker_num' => 16,

        //the number of task logical process progcessor run you business code
        //实际业务处理进程，根据需要进行调整
        'task_worker_num' => 100,

        'daemonize' => false,

        'log_file' => '/tmp/sw_server.log',

        'task_tmpdir' => '/tmp/swtasktmp/',

    ),
    'dora' => array(
        'pid_path' => '/tmp/',//dora 自定义变量，用来保存pid文件
        //'response_header' => array('Content_Type' => 'application/json; charset=utf-8'),
        'master_pid' => 'doramaster.pid', //dora master pid 保存文件
        'manager_pid' => 'doramanager.pid',//manager pid 保存文件
        'log_path' => '/tmp/bizlog/', //业务日志
    ),
));

//redis for service discovery register
//when you on product env please prepare more redis to registe service for high available
$server->discovery(
    array(
        'group1', 'group2'
    ),
    array(

        array(
            array(//first reporter
                "ip" => "127.0.0.1",
                "port" => "6379",
            ),
            array(//next reporter
                "ip" => "127.0.0.1",
                "port" => "6379",
            ),
        ),
    ));

$server->start();
