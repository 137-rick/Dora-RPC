#Dora RPC
----------
##更新历史(ChangeLog)

> * 2015-06-23 修复client链接多个ip或端口导致的错误(#2)
> * 2015-06-24 客户端服务端都增加了SW_DATASIGEN_FLAG及SW_DATASIGEN_SALT参数，如果开启则支持消息数据签名，可以强化安全性，打开会有一点性能损耗，建议SALT每个人自定义一个

----------

> * 2015-06-23 Repair client link multiple ip or port error(#2);
> * 2015-06024 Client Server have added SW_DATASIGEN_FLAG and SW_DATASIGEN_SALT parameters, if enabled supports message data signature, can strengthen security, there will increase a little performance loss, it is recommended everyone to customize a SALT

##简介(Introduction)

> * 是一款基础于Swoole定长包头通讯协议的最精简的RPC
> * 目前只提供PHP语言代码
> * 后续有什么bug或者问题请提交Issue

----------

> * Dora RPC is an Basic Swoole Fixed Header TCP Proctol tiny RPC
> * Now support an simple PHP version
> * If you find something wrong,please submit an issue

----------

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

----------

##请安装依赖(depend)
> * Swoole 1.7.17+
> * PHP 5.4+

##Installation
```
pecl install swoole
```

----------

##文件功能简介(File)
###swclient.php
> * 使用最简单的方式实现的客户端
> * an simple client

###swserver.php
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
$obj = new DoraRPCClient();
for ($i = 0; $i < 100000; $i++) {
    //single && sync
    $ret = $obj->singleAPI("abc", array(234, $i), true);
    var_dump($ret);

    //multi && rsync
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, false);
    var_dump($ret);
}
```

----------

###服务端(Server)
```
    //拷贝自 @果然 的测试代码
    //copy from a frined demo code
    include "swserver.php";
    
    class Server extends DoraRPCServer {
    	function dowork($a){
    		return array("hehe"=>"ohyes");
    	}
    	
    	function initTask(){
    	    //require_once() 你要加载的处理方法函数等 what's you want load (such as framework init)
    	}
    }
    
    $res = new Server();
```

----------

###以上代码测试方法
include以上两个文件，使用命令行启动即可（客户端支持在apache nginx fpm内执行，服务端只支持命令行启动）
> * php swclient.php
> * php swserver.php

----------

##错误码及含义(Error Code)
> * 0 Success work
> * 100001 async task success
> * 100002 unknow task type
> * 100003 you must fill the api parameter on you request
> * 100007 socket error the recive packet length is wrong
> * 100008 the return guid wrong may be the socket trasfer wrong data

----------

##性能(Performance)
> * Mac I7 Intel 2.2Mhz 
> * Vagrant with Vm 1 Core
> * 1G Memory
> * with example code (loop forever)

----------
###Result
> * TPS 2100
> * Response Time:0.02~0.04/sec
> * CPU 10~25%
