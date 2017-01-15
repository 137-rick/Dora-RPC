<?php
namespace DoraRPC;

/**
 * Class Server
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */
abstract class BackEndServer
{

    private $tcpserver = null;
    private $server = null;
    private $taskInfo = array();

    private $serverIP;
    private $serverPort;

    private $monitorProcess = null;

    private $table = null;

    protected $httpConfig = array(
        'dispatch_mode' => 3,

        'package_max_length' => 2097152, // 1024 * 1024 * 2,
        'buffer_output_size' => 3145728, //1024 * 1024 * 3,
        'pipe_buffer_size' => 33554432, //1024 * 1024 * 32,
        'open_tcp_nodelay' => 1,

        'heartbeat_check_interval' => 5,
        'heartbeat_idle_time' => 10,
        'open_cpu_affinity' => 1,

        //'reactor_num' => 32,//建议设置为CPU核数 x 2 新版会自动设置 cpu个数
        'worker_num' => 40,
        'task_worker_num' => 20,//生产环境请加大，建议1000

        'max_request' => 0, //必须设置为0，否则会导致并发任务超时,don't change this number
        'task_max_request' => 4000,

        'log_level' => 2, //swoole 日志级别 Info
        'backlog' => 3000,
        'log_file' => '/tmp/sw_server.log',//swoole 系统日志，任何代码内echo都会在这里输出
        'task_tmpdir' => '/dev/shm/swtask/',//task 投递内容过长时，会临时保存在这里，请将tmp设置使用内存
    );

    protected $tcpConfig = array(
        'open_length_check' => 1,
        'package_length_type' => 'N',
        'package_length_offset' => 0,
        'package_body_offset' => 4,

        'package_max_length' => 2097152, // 1024 * 1024 * 2,
        'buffer_output_size' => 3145728, //1024 * 1024 * 3,
        'pipe_buffer_size' => 33554432, // 1024 * 1024 * 32,

        'open_tcp_nodelay' => 1,

        'backlog' => 3000,
    );

    protected $doraConfig = array(
        //自定义配置
        'pid_path' => '/tmp/',//dora 自定义变量，用来保存pid文件
        //'response_header' => array('Content_Type' => 'application/json; charset=utf-8'),
        'master_pid' => 'doramaster.pid', //dora master pid 保存文件
        'manager_pid' => 'doramanager.pid',//manager pid 保存文件
        'log_level' => DoraConst::LOG_TYPE_INFO,//设置默认日志等级
        'log_dump_type' => 'file',//file|logserver
        'log_path' => '/tmp/bizlog/', //业务日志 dump path

        //const MASTER_PID = './dorarpc.pid';
        //const MANAGER_PID = './dorarpcmanager.pid';
    );

    abstract public function initServer($server);

    final public function __construct($ip = "0.0.0.0", $port = 9567, $httpport = 9566)
    {
        $this->server = new \swoole_http_server($ip, $httpport);
        //tcp server
        $this->tcpserver = $this->server->addListener($ip, $port, \SWOOLE_TCP);
        //tcp只使用这个事件
        $this->tcpserver->on('Receive', array($this, 'onReceive'));
        //init http server
        $this->server->on('Start', array($this, 'onStart'));
        $this->server->on('ManagerStart', array($this, 'onManagerStart'));
        $this->server->on('ManagerStop', array($this, 'onManagerStop'));

        $this->server->on('Request', array($this, 'onRequest'));
        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->server->on('WorkerError', array($this, 'onWorkerError'));
        $this->server->on('Task', array($this, 'onTask'));
        $this->server->on('Finish', array($this, 'onFinish'));

        //invoke the start
        $this->initServer($this->server);

        //store current ip port
        $this->serverIP = $ip;
        $this->serverPort = $port;

    }

    /**
     * Configuration Server.必须在start之前执行
     *
     * @param array $config
     * @return $this
     */
    public function configure(array $config)
    {
        if (isset($config['http'])) {
            //if (isset($config['http']['response_header'])) {
            //    $config['http']['response_header'] = array_merge($this->httpConfig['response_header'], $config['http']['response_header']);
            //}

            $this->httpConfig = array_merge($this->httpConfig, $config['http']);
        }

        if (isset($config['tcp'])) {
            $this->tcpConfig = array_merge($this->tcpConfig, $config['tcp']);
        }

        if (isset($config['dora'])) {
            $this->doraConfig = array_merge($this->doraConfig, $config['dora']);
        }
        return $this;
    }

    /**
     * 启动服务发现服务
     * @param array $group
     * @param array $report
     */
    public function discovery(array $group, array $report)
    {
        $self = $this;
        $this->monitorProcess = new \swoole_process(function () use ($group, $report, $self) {
            while (true) {
                // 上报的服务器IP
                $reportServerIP = $self->getLocalIp();
                swoole_set_process_name("dora: monitor (" . $reportServerIP . ")");

                foreach ($report as $discovery) {
                    foreach ($discovery as $config) {
                        if (trim($config["ip"]) && $config["port"] > 0) {
                            $key = $config["ip"] . "_" . $config["port"];
                            try {
                                if (!isset($_redisObj[$key])) {
                                    //if not connect
                                    $_redisObj[$key] = new \Redis();
                                    $_redisObj[$key]->connect($config["ip"], $config["port"]);
                                }
                                //register this server
                                $_redisObj[$key]->sadd("dora.serverlist", json_encode(array(
                                    "node" => array(
                                        "ip" => $reportServerIP,
                                        "port" => $self->serverPort
                                    ),
                                    "group" => $group,
                                )));
                                //set time out
                                $_redisObj[$key]->set("dora.servertime." . $reportServerIP . "." . $self->serverPort . ".time", time());
                                echo "Reported Service Discovery:" . $config["ip"] . ":" . $config["port"] . PHP_EOL;

                            } catch (\Exception $ex) {
                                $_redisObj[$key] = null;
                                echo "connect to Service Discovery error:" . $config["ip"] . ":" . $config["port"] . PHP_EOL;
                            }
                        }

                        sleep(10);
                        //sleep 10 sec and report again
                    }// config foreach
                }//discover foreach
            }
        });
        $this->server->addProcess($this->monitorProcess);

    }

    /**
     * Start Server.
     *
     * @return void;
     */
    public function start()
    {
        //config the server config
        $this->server->set($this->httpConfig);
        $this->tcpserver->set($this->tcpConfig);

        $this->table = new \swoole_table(1024);
        $this->table->column('value', \swoole_table::TYPE_STRING, 64);
        $this->table->create();

        //log agent init first
        LogAgent::init($this->doraConfig["log_path"], $this->table);
        LogAgent::setLogLevel($this->doraConfig["log_level"]);

        $this->server->addProcess(new \swoole_process(function () {
            LogAgent::threadDumpLog();
        }));


        $this->server->start();
    }

    //http request process
    final public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        //return the json
        $response->header('Content_Type', 'application/json; charset=utf-8');

        //forever http 200 ,when the error json code decide
        $response->status(200);

        //chenck post error
        if (!isset($request->post["params"]) || !isset($request->post["guid"])) {
            $response->end(json_encode(Packet::packFormat($request->post["guid"], "Parameter was not set or wrong", 100003)));
            return;
        }
        //get the post parameter
        $params = $request->post;
        $params = json_decode($params["params"], true);

        //check the parameter need field
        if (!isset($params["guid"]) || !isset($params["api"]) || count($params["api"]) == 0) {
            $response->end(json_encode(Packet::packFormat($params["guid"], "Parameter was not set or wrong", 100004)));
            return;
        }

        //task base info
        $task = array(
            "guid" => $params["guid"],
            "fd" => $request->fd,
            "protocol" => "http",
        );

        $url = trim($request->server["request_uri"], "\r\n/ ");

        switch ($url) {
            case "api/multisync":
                $task["type"] = DoraConst::SW_MODE_WAITRESULT_MULTI;
                foreach ($params["api"] as $k => $v) {
                    $task["api"] = $params["api"][$k];
                    $taskid = $this->server->task($task, -1, function ($serv, $task_id, $data) use ($response) {
                        $this->onHttpFinished($serv, $task_id, $data, $response);
                    });
                    $this->taskInfo[$task["fd"]][$task["guid"]]["taskkey"][$taskid] = $k;
                }
                break;
            case "api/multinoresult":
                $task["type"] = DoraConst::SW_MODE_NORESULT_MULTI;

                foreach ($params["api"] as $k => $v) {
                    $task["api"] = $params["api"][$k];
                    $this->server->task($task);
                }
                $pack = Packet::packFormat($task["guid"], "transfer success.已经成功投递", 100001);
                $response->end(json_encode($pack));

                break;
            case "server/cmd":
                $task["type"] = DoraConst::SW_CONTROL_CMD;

                if ($params["api"]["cmd"]["name"] == "getStat") {
                    $pack = Packet::packFormat($params["guid"], "OK", 0, array("server" => $this->server->stats(), "logqueue" => LogAgent::getQueueStat()));
                    $pack["guid"] = $task["guid"];
                    $response->end(json_encode($pack));
                    return;
                }
                if ($params["api"]["cmd"]["name"] == "reloadTask") {
                    $pack = Packet::packFormat($params["guid"], "OK", 0, array('server' => $this->server->stats(), "logqueue" => LogAgent::getQueueStat()));
                    $this->server->reload(true);
                    $pack["guid"] = $task["guid"];
                    $response->end(json_encode($pack));
                    return;
                }
                break;
            default:
                $response->end(json_encode(Packet::packFormat($params["guid"], "unknow task type.未知类型任务", 100002)));
                unset($this->taskInfo[$task["fd"]]);
                return;
        }

    }

    //application server first start
    final public function onStart(\swoole_server $serv)
    {
        swoole_set_process_name("dora: master");

        echo "MasterPid={$serv->master_pid}\n";
        echo "ManagerPid={$serv->manager_pid}\n";
        echo "Server: start.Swoole version is [" . SWOOLE_VERSION . "]\n";

        file_put_contents($this->doraConfig["pid_path"] . "/" . $this->doraConfig["master_pid"], $serv->master_pid);
        file_put_contents($this->doraConfig["pid_path"] . "/" . $this->doraConfig["manager_pid"], $serv->manager_pid);

    }

    //application server first start
    final public function onManagerStart(\swoole_server $serv)
    {
        swoole_set_process_name("dora: manager");
    }

    final public function onManagerStop(\swoole_server $serv)
    {
        //echo "Manager Stop , shutdown server\n";
        //$serv->shutdown();
    }

    //worker and task init
    final public function onWorkerStart($server, $worker_id)
    {
        $istask = $server->taskworker;
        if (!$istask) {
            //worker
            swoole_set_process_name("dora: worker {$worker_id}");
        } else {
            //task
            swoole_set_process_name("dora: task {$worker_id}");
            $this->initTask($server, $worker_id);
        }

    }

    abstract public function initTask($server, $worker_id);

    //tcp request process
    final public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        $requestInfo = Packet::packDecode($data);

        #decode error
        if ($requestInfo["code"] != 0) {
            $req = Packet::packEncode($requestInfo);
            $serv->send($fd, $req);

            return true;
        } else {
            $requestInfo = $requestInfo["data"];
        }

        #api was not set will fail
        if (!is_array($requestInfo["api"]) && count($requestInfo["api"] == 0)) {
            $pack = Packet::packFormat($requestInfo["guid"], "param api is empty", 100003);
            $pack = Packet::packEncode($pack);
            $serv->send($fd, $pack);

            return true;
        }
        $guid = $requestInfo["guid"];

        //prepare the task parameter
        $task = array(
            "type" => $requestInfo["type"],
            "guid" => $requestInfo["guid"],
            "fd" => $fd,
            "protocol" => "tcp",
        );

        //different task type process
        switch ($requestInfo["type"]) {

            case DoraConst::SW_MODE_WAITRESULT_SINGLE:
                $task["api"] = $requestInfo["api"]["one"];
                $taskid = $serv->task($task);

                //result with task key
                $this->taskInfo[$fd][$guid]["taskkey"][$taskid] = "one";

                return true;
                break;
            case DoraConst::SW_MODE_NORESULT_SINGLE:
                $task["api"] = $requestInfo["api"]["one"];
                $serv->task($task);

                //return success deploy
                $pack = Packet::packFormat($guid, "transfer success.已经成功投递", 100001);
                $pack = Packet::packEncode($pack);
                $serv->send($fd, $pack);

                return true;

                break;

            case DoraConst::SW_MODE_WAITRESULT_MULTI:
                foreach ($requestInfo["api"] as $k => $v) {
                    $task["api"] = $requestInfo["api"][$k];
                    $taskid = $serv->task($task);
                    $this->taskInfo[$fd][$guid]["taskkey"][$taskid] = $k;
                }

                return true;
                break;
            case DoraConst::SW_MODE_NORESULT_MULTI:
                foreach ($requestInfo["api"] as $k => $v) {
                    $task["api"] = $requestInfo["api"][$k];
                    $serv->task($task);
                }

                $pack = Packet::packFormat($guid, "transfer success.已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);

                return true;
                break;
            case DoraConst::SW_CONTROL_CMD:
                switch ($requestInfo["api"]["cmd"]["name"]) {
                    case "getStat":
                        $pack = Packet::packFormat($guid, "OK", 0, array("server" => $serv->stats(), "logqueue" => LogAgent::getQueueStat()));
                        $pack = Packet::packEncode($pack);
                        $serv->send($fd, $pack);
                        return true;

                        break;
                    case "reloadTask":
                        $pack = Packet::packFormat($guid, "OK", 0, array("server" => $serv->stats(), "logqueue" => LogAgent::getQueueStat()));
                        $pack = Packet::packEncode($pack);
                        $serv->send($fd, $pack);
                        $serv->reload(true);
                        return true;

                        break;
                    default:
                        $pack = Packet::packFormat($guid, "unknow cmd", 100011);
                        $pack = Packet::packEncode($pack);

                        $serv->send($fd, $pack);
                        unset($this->taskInfo[$fd]);
                        break;
                }
                break;

            case DoraConst::SW_MODE_ASYNCRESULT_SINGLE:
                $task["api"] = $requestInfo["api"]["one"];
                $taskid = $serv->task($task);
                $this->taskInfo[$fd][$guid]["taskkey"][$taskid] = "one";

                //return success
                $pack = Packet::packFormat($guid, "transfer success.已经成功投递", 100001);
                $pack = Packet::packEncode($pack);
                $serv->send($fd, $pack);

                return true;
                break;
            case DoraConst::SW_MODE_ASYNCRESULT_MULTI:
                foreach ($requestInfo["api"] as $k => $v) {
                    $task["api"] = $requestInfo["api"][$k];
                    $taskid = $serv->task($task);
                    $this->taskInfo[$fd][$guid]["taskkey"][$taskid] = $k;
                }

                //return success
                $pack = Packet::packFormat($guid, "transfer success.已经成功投递", 100001);
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);
                break;
            default:
                $pack = Packet::packFormat($guid, "unknow task type.未知类型任务", 100002);
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);
                //unset($this->taskInfo[$fd]);

                return true;
        }

        return true;
    }

    final public function onTask($serv, $task_id, $from_id, $data)
    {
        try {
            $data["result"] = Packet::packFormat($data["guid"], "OK", 0, $this->doWork($data));
        } catch (\Exception $e) {
            $data["result"] = Packet::packFormat($data["guid"], $e->getMessage(), $e->getCode());
        }

        return $data;
    }

    abstract public function doWork($param);


    final public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        //using the swoole error log output the error this will output to the swtmp log
        var_dump("workererror", array($this->taskInfo, $serv, $worker_id, $worker_pid, $exit_code));
        LogAgent::recordLog(DoraConst::LOG_TYPE_ERROR, "worker_error", __FILE__, __LINE__, array($this->taskInfo, $serv, $worker_id, $worker_pid, $exit_code));
    }

    /**
     * 获取当前服务器ip，用于服务发现上报IP
     *
     * @return string
     */
    protected function getLocalIp()
    {
        if ($this->serverIP == '0.0.0.0' || $this->serverIP == '127.0.0.1') {
            $serverIps = swoole_get_local_ip();
            $patternArray = array(
                '10\.',
                '172\.1[6-9]\.',
                '172\.2[0-9]\.',
                '172\.31\.',
                '192\.168\.'
            );
            foreach ($serverIps as $serverIp) {
                // 匹配内网IP
                if (preg_match('#^' . implode('|', $patternArray) . '#', $serverIp)) {
                    return $serverIp;
                }
            }
        }

        return $this->serverIP;
    }

    //task process finished
    final public function onFinish($serv, $task_id, $data)
    {
        $fd = $data["fd"];
        $guid = $data["guid"];

        //if the guid not exists .it's mean the api no need return result
        if (!isset($this->taskInfo[$fd][$guid])) {
            return true;
        }

        //get the api key
        $key = $this->taskInfo[$fd][$guid]["taskkey"][$task_id];

        //save the result
        $this->taskInfo[$fd][$guid]["result"][$key] = $data["result"];

        //remove the used taskid
        unset($this->taskInfo[$fd][$guid]["taskkey"][$task_id]);

        switch ($data["type"]) {

            case DoraConst::SW_MODE_WAITRESULT_SINGLE:
                $packet = Packet::packFormat($guid, "OK", 0, $data["result"]);
                $packet = Packet::packEncode($packet, $data["protocol"]);

                $serv->send($fd, $packet);
                unset($this->taskInfo[$fd][$guid]);

                return true;
                break;

            case DoraConst::SW_MODE_WAITRESULT_MULTI:
                if (count($this->taskInfo[$fd][$guid]["taskkey"]) == 0) {
                    $packet = Packet::packFormat($guid, "OK", 0, $this->taskInfo[$fd][$guid]["result"]);
                    $packet = Packet::packEncode($packet, $data["protocol"]);
                    $serv->send($fd, $packet);
                    //$serv->close($fd);
                    unset($this->taskInfo[$fd][$guid]);

                    return true;
                } else {
                    //multi call task
                    //not finished
                    //waiting other result
                    return true;
                }
                break;

            case DoraConst::SW_MODE_ASYNCRESULT_SINGLE:
                $packet = Packet::packFormat($guid, "OK", 0, $data["result"]);
                //flag this is result
                $packet["isresult"] = 1;
                $packet = Packet::packEncode($packet, $data["protocol"]);

                //sys_get_temp_dir
                $serv->send($fd, $packet);
                unset($this->taskInfo[$fd][$guid]);

                return true;
                break;
            case DoraConst::SW_MODE_ASYNCRESULT_MULTI:
                if (count($this->taskInfo[$fd][$guid]["taskkey"]) == 0) {
                    $packet = Packet::packFormat($guid, "OK", 0, $this->taskInfo[$fd][$guid]["result"]);
                    $packet["isresult"] = 1;
                    $packet = Packet::packEncode($packet, $data["protocol"]);
                    $serv->send($fd, $packet);

                    unset($this->taskInfo[$fd][$guid]);

                    return true;
                } else {
                    //multi call task
                    //not finished
                    //waiting other result
                    return true;
                }
                break;
            default:
                //
                return true;
                break;
        }

    }

    //http task finished process
    final public function onHttpFinished($serv, $task_id, $data, $response)
    {
        $fd = $data["fd"];
        $guid = $data["guid"];

        //if the guid not exists .it's mean the api no need return result
        if (!isset($this->taskInfo[$fd][$guid])) {
            return true;
        }

        //get the api key
        $key = $this->taskInfo[$fd][$guid]["taskkey"][$task_id];

        //save the result
        $this->taskInfo[$fd][$guid]["result"][$key] = $data["result"];

        //remove the used taskid
        unset($this->taskInfo[$fd][$guid]["taskkey"][$task_id]);

        switch ($data["type"]) {
            case DoraConst::SW_MODE_WAITRESULT_MULTI:
                //all task finished
                if (count($this->taskInfo[$fd][$guid]["taskkey"]) == 0) {
                    $packet = Packet::packFormat($guid, "OK", 0, $this->taskInfo[$fd][$guid]["result"]);
                    $packet = Packet::packEncode($packet, $data["protocol"]);
                    unset($this->taskInfo[$fd][$guid]);
                    $response->end($packet);
                    return true;
                } else {
                    //multi call task
                    //not finished
                    //waiting other result
                    return true;
                }
                break;
            default:

                return true;
                break;
        }
    }

    final public function __destruct()
    {
        echo "Server Was Shutdown..." . PHP_EOL;
        //shutdown
        $this->server->shutdown();
    }

}
