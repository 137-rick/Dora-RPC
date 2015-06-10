#Dora RPC
----------
##简介(Introduce)

> * 是一款基础于Swoole定长包头通讯协议的最精简的RPC
> * 目前只提供PHP语言代码
> * 后续有什么bug或者问题请提交Issue

> * Dora RPC is an Basic Swoole Fix Header TCP Proctol tiny RPC
> * Now support an simply PHP version
> * if you got something wrong please submit Issue

#功能支持(Function)
> * 支持单API调用，多API并发调用
> * 支持同步调用，异步任务下发
> * 其他相关知识请参考Swoole扩展

> * Single API RPC,Multi API Concurrent RPC
> * Asynchronous synchronization
> * other please visit Swoole official
----------
##请安装依赖(depend)
> * Swoole 1.7.17+
> * PHP 5.4+

##Install
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

> * a simple server
> * you must extends the swserver and implement dowork function
> * it's useful decrease the dev cycle
> * the result now have two leve array:first is communicate state(code field),second is dowork state
----------

##使用方法(eg)

###客户端(Client)
```
$obj = new DoraRPCClient();
for ($i = 0; $i < 100000; $i++) {
    #single
    $ret = $obj->singleAPI("abc", array(234, $i), true);
    var_dump($ret);

    #multi
    $data = array(
        "oak" => array("name" => "oakdf", "param" => array("dsaf" => "321321")),
        "cd" => array("name" => "oakdfff", "param" => array("codo" => "fds")),
    );
    $ret = $obj->multiAPI($data, false);
    var_dump($ret);
}
```


###服务端
```
    $server = new DoraRPCServer();//这里必须是DoraRPCServer继承类并实现dowork才可以工作
```

###以上代码测试方法
include以上两个文件，使用命令行启动即可（客户端支持在apache nginx fpm内执行，服务端只支持命令行启动）
> * php swclient.php
> * php swserver.php
