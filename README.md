#Dora RPC
 
[![Build Status](https://travis-ci.org/xcl3721/Dora-RPC.svg?branch=master)](https://travis-ci.org/xcl3721/Dora-RPC) [![Latest Stable Version](https://poser.pugx.org/xcl3721/dora-rpc/v/stable)](https://packagist.org/packages/xcl3721/dora-rpc) [![Latest Unstable Version](https://poser.pugx.org/xcl3721/dora-rpc/v/unstable)](https://packagist.org/packages/xcl3721/dora-rpc) [![License](https://poser.pugx.org/xcl3721/dora-rpc/license)](https://packagist.org/packages/xcl3721/dora-rpc)
## 简介(Introduction)

Dora RPC 是一款基础于Swoole定长包头通讯协议的最精简的RPC, 用于复杂项目前后端分离，分离后项目都通过API工作可更好的跟踪、升级、维护及管理。

问题提交: [Issue](https://github.com/xcl3721/Dora-RPC/issues)

For complex projects separation, the project can be better maintained by the API project management.
> * Dora RPC is an Basic Swoole Fixed Header TCP Proctol tiny RPC
> * Now support an simple PHP version 
> * If you find something wrong,please submit an issue
> * add the http protocol and KeepAlive for the other program language


#设计思路(Design)
> * http://blog.sina.com.cn/s/blog_54ef39890102vs3h.html 架构设计图
> * http://blog.sina.com.cn/s/blog_54ef39890102w8ff.html 端午升级介绍
> * http://wenku.baidu.com/view/ae8adf90e518964bce847c43.html Dora-RPC 思想介绍

#功能支持(Function)
> * 支持单API调用，多API并发调用
> * 支持同步调用，异步任务下发不等待结果，异步任务下发统一拿回结果
> * 其他相关知识请参考Swoole扩展
> * 客户端长链接，请求完毕后仍旧保留，减少握手消耗
> * guid收发一致性检测，避免发送和接收数据不一致
> * 基于redis制作的服务发现

> * Single API RPC \ Multi API Concurrent RPC
> * Asynchronous，synchronization no need result， synchronization get result by manual
> * Please visit Swoole official for further infomation
> * keep the connection of client after the request finishe
> * check the guid when the send<->recive
> * service discovery.
> * base on Redis. Service discovery for High available

##请安装依赖(depend)
> * Swoole 1.8.x+
> * PHP 5.4+
> * zlib for compress packet

##Installation
```
composer require "xcl3721/dora-rpc"
```

##文件功能简介(File)
###dora-rpc/src/Client.php
> * 使用最简单的方式实现的客户端，通过这个框架可以轻松实现PHP的伪多线程，通过分布式加快接口响应速度及高可用
> * an simple client,it's easy adn simply to implement the multi fake thread,you can speed up you API by this distribute RPC

###dora-rpc/src/BackEndServer.php
> * API服务端
> * 目前需要继承才能使用，继承后请实现dowork，这个函数是实际处理任务的函数参数为提交参数
> * 做这个只是为了减少大家启用RPC的开发时间
> * 开启服务发现功能，服务端在启动的时候，如果指定redis配置则会自动将当前服务器信息注册到Redis上
> * 返回结果是一个数组 分两部分，第一层是通讯状态code，第二层是处理状态 code

> * a powerful API server
> * you must extends the swserver and implement dowork function
> * it's use for decrease the dev cycle
> * when you setup the redis config the server will register this server to the redis for service discovery
> * the result will be a two-level arrayfirst is communicate state 'code field' ,second is dowork state

###dora-rpc/src/Monitor.php
> * 服务发现客户端，通过扫描Redis获取到所有可用后端服务列表，并生成配置到指定路径
> * an discovery controller client that:scan all the redis and get the list of available service and general config file to special path

###dora-rpc/src/groupclient.php (combined to client.php)
> * 服务发现monitor进程产生的配置可以用这个客户端直接引用，请求时可以指定使用哪个组的服务
> * an client for service discovery （monitor general the config from redis） that you can use the config directly 

##使用方法(Example)
###任务下发模式介绍(task deploy mode)
> * 0 sync wait result 同步下发任务阻塞等待结果返回
> * 1 async no need result 下发异步任务，下发成功返回下发成功提示，不等待任务处理结果
> * 2 async get result by getAsyncData function 下发异步任务，下发成功返回下发成功提示，可以在后续调用getAsyncData 获取所有下发的异步结果

###TCP客户端(TCP Client)
```PHP

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

    //single
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

    //multi

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
}
echo "max:" . $maxrequest . PHP_EOL;

```

###HTTP客户端(Http Client)

http protocol for the other language use performance is common.suggest used tcp client
```PHP

for ($i = 0; $i < 10000; $i++) {
    $time = microtime(true);

    //mutil call sync wait result
    $data = array(
        "guid" => md5(mt_rand(1000000, 9999999) . mt_rand(1000000, 9999999) . microtime(true)),

        "api" => array(
            "oak" => array("name" => "/module_d/oakdf", "param" => array("dsaf" => "32111321")),
            "cd" => array("name" => "/module_e/oakdfff", "param" => array("codo" => "f11ds")),
        )
    ,
    );

    $data_string = "params=" . urlencode(json_encode($data));

    $ch = curl_init('http://127.0.0.1:9566/api/multisync');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive',
            'Keep-Alive: 300',
        )
    );

    $result = curl_exec($ch);
    var_dump(json_decode($result, true));


    //multi call no wait result
    $data = array(
        "guid" => md5(mt_rand(1000000, 9999999) . mt_rand(1000000, 9999999) . microtime(true)),

        "api" => array(
            "oak" => array("name" => "/module_d/oakdf", "param" => array("dsaf" => "32111321")),
            "cd" => array("name" => "/module_e/oakdfff", "param" => array("codo" => "f11ds")),
        )
    ,
    );

    $data_string = "params=" . urlencode(json_encode($data));

    $ch = curl_init('http://127.0.0.1:9566/api/multinoresult');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive',
            'Keep-Alive: 300',
        )
    );

    $result = curl_exec($ch);
    var_dump(json_decode($result, true));


    $time = bcsub(microtime(true), $time, 5);
    if ($time > $maxrequest) {
        $maxrequest = $time;
    }
    echo $i . " cost:" . $time . PHP_EOL;
    //var_dump($ret);
}
echo "max:" . $maxrequest . PHP_EOL;

```

###服务端(Server)
```PHP

class Server extends DoraRPCServer {

    //all of this config for optimize performance
    //以下配置为优化服务性能用，请实际压测调试
    protected  $externalConfig = array(

        //to improve the accept performance ,suggest the number of cpu X 2
        //如果想提高请求接收能力，更改这个，推荐cpu个数x2
        'reactor_num' => 32,

        //packet decode process,change by condition
        //包处理进程，根据情况调整数量
        'worker_num' => 40,

        //the number of task logical process progcessor run you business code
        //实际业务处理进程，根据需要进行调整
        'task_worker_num' => 20,
    );

    function initServer($server){
        //the callback of the server init 附加服务初始化
        //such as swoole atomic table or buffer 可以放置swoole的计数器，table等
    }
    function doWork($param){
        //process you logical 业务实际处理代码仍这里
        //return the result 使用return返回处理结果
        return array("hehe"=>"ohyes");
    }

    function initTask($server, $worker_id){
        //require_once() 你要加载的处理方法函数等 what's you want load (such as framework init)
    }
}

$res = new Server();
```
###客户端监控器(Client Local Monitor)
```PHP
include "src/Doraconst.php";
include "src/Packet.php";
include "src/Monitor.php";


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
//this server will auto get the node server list from redis and general the client config on special path
```

###以上代码测试方法
include以上两个文件，使用命令行启动即可（客户端支持在apache nginx fpm内执行，服务端只支持命令行启动）
> * php democlient.php
> * php demoserver.php

##错误码及含义(Error Code)
> * 0 Success work
> * 100001 async task success
> * 100002 unknow task type
> * 100003 you must fill the api parameter on you request
> * 100005 Signed check error
> * 100006 Pack decode type wrong
> * 100007 socket error the recive packet length is wrong
> * 100008 the return guid wrong may be the socket trasfer wrong data
> * 100009 the recive wrong or timeout
> * 100010 there is no server can connect
> * 100011 unknow cmd of controlle
> * 100012 Get Async Result Fail: Client Closed.
> * 100099 unknow communicate mode have been set
> * 100100 guid wront please retry..

##性能(Performance)
> * Mac I7 Intel 2.2Mhz 
> * Vagrant with Vm 1 Core
> * 1G Memory
> * with example code (loop forever)

###测试结果Result
> * Network Cost:0.002~0.004/sec Per Request
> * CPU 10~25%
以上还有很大优化空间
There is still a lot of optimization space

###Optimize performance性能优化
```
vim demoserver.php
to see $externalConfig var
and swoole offcial document

如果想优化性能请参考以上文件的$externalConfig配置
```

###Server Config Optimize
> * http://wiki.swoole.com/wiki/page/p-server/sysctl.html

###License授权
Apache

###QQ Group
QQ Group:346840633
