##更新历史(ChangeLog)
> * 2015-09-20 psr2检测，去掉非规范写法,增加namespace为composer做准备,将demo移到test内
> * 2015-07-27 客户端单个连接配置扩展为多个，每次初始化客户端的时候会自动从配置内随机选一个配置进行连接，如果连接失败自动切换另外一个，用于提高高可用。
> * 2015-07-24 增加两个抽象函数 initTask 当task进程启动的时候初始化使用 ,initServer 服务启动前附加启动时会调用这个，用于一些服务的初始化.增加请求失败重试指定次数功能
> * 2015-06-23 修复client链接多个ip或端口导致的错误(#2)
> * 2015-06-24 客户端服务端都增加了SW_DATASIGEN_FLAG及SW_DATASIGEN_SALT参数，如果开启则支持消息数据签名，可以强化安全性，打开会有一点性能损耗，建议SALT每个人自定义一个

----------
> * 2015-09-20 psr2 leve check and add namespace for composer,move the demo to test folder
> * 2015-07-27 client support multi config item.each of item is the server infomation.to improve the high availability.when the connect fail will try another config item
> * 2015-07-24 add two abstract function: server start init(fn initServer) . task threads start init(fn initTask).and add retry parameter on the request
> * 2015-06-23 Repair client link multiple ip or port error(#2);
> * 2015-06024 Client Server have added SW_DATASIGEN_FLAG and SW_DATASIGEN_SALT parameters, if enabled supports message data signature, can strengthen security, there will increase a little performance loss, it is recommended everyone to customize a SALT
