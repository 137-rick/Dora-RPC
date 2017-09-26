##更新历史(ChangeLog)

---------
> * 2017-01-15 支持channel归并日志到同一个process内落地，改guid到json外面，防止解序列化失败导致guid丢失
> * 2016-07-24 黄总支援了帮忙改进了配置的传递方式及服务启动方式
> * 2016-06-11 增加group连接方式，将原有groupclient方式整合到client内，不再提供非分组配置客户端
> * 2016-06-08 增加新的异步调用方式，并且修复低几率调用参数覆盖问题，groupclient还未更新，请稍后
> * 2016-06-06 客户端增加分组配置客户端，可以用客户端调用多组隔离开的业务。另外之前加了服务发现在test/demomonitor .去掉unique函数服务性能翻倍,gzcompress包压缩
> * 2015-09-30 客户端增加ip和port选项，常量放到一个文件内统一管理
> * 2015-09-29 added to composer.
> * 2015-09-20 psr2检测，去掉非规范写法,增加namespace为composer做准备,将demo移到test内.修复task返回结果超过8k导致超时问题
> * 2015-07-27 客户端单个连接配置扩展为多个，每次初始化客户端的时候会自动从配置内随机选一个配置进行连接，如果连接失败自动切换另外一个，用于提高高可用。
> * 2015-07-24 增加两个抽象函数 initTask 当task进程启动的时候初始化使用 ,initServer 服务启动前附加启动时会调用这个，用于一些服务的初始化.增加请求失败重试指定次数功能
> * 2015-06-23 修复client链接多个ip或端口导致的错误(#2)
> * 2015-06-24 客户端服务端都增加了SW_DATASIGEN_FLAG及SW_DATASIGEN_SALT参数，如果开启则支持消息数据签名，可以强化安全性，打开会有一点性能损耗，建议SALT每个人自定义一个

----------
> * 2017-01-15 support use channel for collect the log and dump by process,change guid outside packet to solve the guid lost when unserialize fail
> * 2016-07-24 JanHuang optimize the config define,and server startup
> * 2016-06-11 remove the groupclient.php and combine groupclient function to the client.php now only support group config client
> * 2016-06-08 add new async result get and fixed the running stack overwrite bug，groupclient was not update under construction
> * 2016-06-06 client have new group config client that for test/demomonitor .(and service discovery demomonitor)。performance 2X when remove unique。gzcompress packet compress
> * 2015-09-30 client can set ip and port you want
> * 2015-09-29 added to composer.
> * 2015-09-20 psr2 leve check and add namespace for composer,move the demo to test folder,fixed when task result 8k+ was timeout bug
> * 2015-07-27 client support multi config item.each of item is the server infomation.to improve the high availability.when the connect fail will try another config item
> * 2015-07-24 add two abstract function: server start init(fn initServer) . task threads start init(fn initTask).and add retry parameter on the request
> * 2015-06-23 Repair client link multiple ip or port error(#2);
> * 2015-06024 Client Server have added SW_DATASIGEN_FLAG and SW_DATASIGEN_SALT parameters, if enabled supports message data signature, can strengthen security, there will increase a little performance loss, it is recommended everyone to customize a SALT
