#Dora RPC
----------
##简介(Introduction)
用于复杂项目前后端分离，分离后项目都通过API工作可更好维护管理。
> * 是一款基础于Swoole定长包头通讯协议的最精简的RPC
> * 目前只提供PHP语言代码
> * 后续有什么bug或者问题请提交Issue
> * 基础redis制作的服务发现

----------
For complex projects separation, the project can be better maintained by the API project management.
> * Dora RPC is an Basic Swoole Fixed Header TCP Proctol tiny RPC
> * Now support an simple PHP version
> * If you find something wrong,please submit an issue
> * base on Redis. Service discovery for High available

----------
#设计思路(Design)
http://blog.sina.com.cn/s/blog_54ef39890102vs3h.html

#功能支持(Function)
> * 支持单API调用，多API并发调用
> * 支持同步调用，异步任务下发
> * 其他相关知识请参考Swoole扩展
> * 客户端长链接，请求完毕后仍旧保留，减少握手消耗
> * guid收发一致性检测，避免发送和接收数据不一致

----------

> * Single API RPC \ Multi API Concurrent RPC
> * Asynchronous synchronization
> * Please visit Swoole official for further infomation
> * keep the connection of client after the request finishe
> * check the guid when the send<->recive
> * service discovery.

----------

##请安装依赖(depend)
> * Swoole 1.7.17+
> * PHP 5.4+

##Installation
```
pecl install swoole

vim composer.json
added:
{
    "name": "xx",
    "description": "xxx",
    "require": {
        "xcl3721/dora-rpc": ">=0.3.4"
    }
}

:wq
composer update

```

----------

##文件功能简介(File)
###dora-rpc/client.php
> * 使用最简单的方式实现的客户端
> * an simple client

###dora-rpc/server.php
> * 使用最简单的方式实现的服务端
> * 目前需要继承才能使用，继承后请实现dowork，这个函数是实际处理任务的函数参数为提交参数
> * 做这个只是为了减少大家启用RPC的开发时间
> * 返回结果是一个数组 分两部分，第一层是通讯状态（code），第二层是处理状态（code）

----------

> * a simple server
> * you must extends the swserver and implement dowork function
> * it's use for decrease the dev cycle
> * the result will be a two-level arrayfirst is communicate state(code field),second is dowork state

----------

##使用方法(Example)

###客户端(Client)
```
include "dora-rpc/client.php";

//app server config 
$config = array(
    array("ip"=>"127.0.0.1","port"=>9567),
    //array("ip"=>"127.0.0.1","port"=>9567), you can set more ,the client will random select one,to increase High availability
);

$obj = new DoraRPCClient($config);
for ($i = 0; $i < 100000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), false,1);
    var_dump($ret);

    //multi && async
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, true,1);
    var_dump($ret);
}
```

----------

###服务端(Server)
```
include "dora-rpc/server.php";

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

----------

###以上代码测试方法
include以上两个文件，使用命令行启动即可（客户端支持在apache nginx fpm内执行，服务端只支持命令行启动）
> * php democlient.php
> * php demoserver.php

----------

##错误码及含义(Error Code)
> * 0 Success work
> * 100001 async task success
> * 100002 unknow task type
> * 100003 you must fill the api parameter on you request
> * 100007 socket error the recive packet length is wrong
> * 100008 the return guid wrong may be the socket trasfer wrong data
> * 100009 the recive wrong or timeout
> * 100010 there is no server can connect
> * 100011 unknow cmd of controlle

----------

##性能(Performance)
> * Mac I7 Intel 2.2Mhz 
> * Vagrant with Vm 1 Core
> * 1G Memory
> * with example code (loop forever)

----------
###测试结果Result
> * TPS 2100
> * Response Time:0.02~0.04/sec
> * CPU 10~25%
以上还有很大优化空间
There is still a lot of optimization space

----------
###Optimize performance性能优化
```
vim demoserver.php
to see $externalConfig var
and swoole offcial document

如果想优化性能请参考以上文件的$externalConfig配置
```

----------
###License授权
Apache

###QQ Group
QQ Group:346840633
