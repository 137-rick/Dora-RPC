<?php
namespace DoraRPC;

/**
 * Class Server
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */
abstract class Server
{

    private $server = null;
    private $taskInfo = array();

    private $reportConfig = array();

    private $serverIP;
    private $serverPort;

    private $groupConfig;

    //for extends class overwrite default config
    //用于继承类覆盖默认配置
    protected $externalConfig = array();

    abstract public function initServer($server);

    final public function __construct($ip = "0.0.0.0", $port = 9567, $groupConfig = array(), $reportConfig = array())
    {
        $this->server = new \swoole_server($ip, $port);
        $config = array(
            'open_length_check' => 1,
            'dispatch_mode' => 3,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_offset' => 4,
            'package_max_length' => 1024 * 1024 * 2,
            'buffer_output_size' => 1024 * 1024 * 3,
            'pipe_buffer_size' => 1024 * 1024 * 32,
            'open_tcp_nodelay' => 1,
            'heartbeat_check_interval' => 5,
            'heartbeat_idle_time' => 10,

            'reactor_num' => 32,
            'worker_num' => 40,
            'task_worker_num' => 20,

            'max_request' => 0, //必须设置为0否则并发任务容易丢,don't change this number
            'task_max_request' => 4000,

            'backlog' => 2000,
            'log_file' => '/tmp/sw_server.log',
            'task_tmpdir' => '/tmp/swtasktmp/',

            'daemonize' => 1,
        );

        //merge config
        if (!empty($this->externalConfig)) {
            $config = array_merge($config, $this->externalConfig);
        }

        $this->server->set($config);

        $this->server->on('connect', array($this, 'onConnect'));
        $this->server->on('workerstart', array($this, 'onWorkerStart'));
        $this->server->on('receive', array($this, 'onReceive'));
        $this->server->on('workererror', array($this, 'onWorkerError'));
        $this->server->on('task', array($this, 'onTask'));
        $this->server->on('close', array($this, 'onClose'));
        $this->server->on('finish', array($this, 'onFinish'));

        //invoke the start
        $this->initServer($this->server);

        //store current ip port
        $this->serverIP = $ip;
        $this->serverPort = $port;

        //store current server group
        $this->groupConfig = $groupConfig;
        //if user set the report config will start report
        if (count($reportConfig) > 0) {
            echo "Found Report Config... Start Report Process" . PHP_EOL;
            $this->reportConfig = $reportConfig;
            //use this report the state
            $process = new \swoole_process(array($this, "monitorReport"));
            $this->server->addProcess($process);
        }

        $this->server->start();
    }

    //////////////////////////////server monitor start/////////////////////////////
    //server report
    final public function monitorReport(\swoole_process $process)
    {
        static $_redisObj;

        while (true) {
            echo "Report Node for Discovery" . PHP_EOL;
            //register group and server
            $redisconfig = $this->reportConfig;
            //register this node server info to redis
            foreach ($redisconfig as $redisitem) {

                //validate redis ip and port
                if (trim($redisitem["ip"]) && $redisitem["port"] > 0) {
                    $key = $redisitem["ip"] . "_" . $redisitem["port"];
                    try {
                        if (!isset($_redisObj[$key])) {
                            //if not connect
                            $_redisObj[$key] = new \Redis();
                            $_redisObj[$key]->connect($redisitem["ip"], $redisitem["port"]);
                        }
                        //register this server
                        $_redisObj[$key]->sadd("dora.serverlist", json_encode(array("node" => array("ip" => $this->serverIP, "port" => $this->serverPort), "group" => $this->groupConfig["list"])));
                        //set time out
                        $_redisObj[$key]->set("dora.servertime." . $this->serverIP . "." . $this->serverPort . ".time", time());

                        echo "Report to Server:" . $redisitem["ip"] . ":" . $redisitem["port"] . PHP_EOL;

                    } catch (\Exception $ex) {
                        $_redisObj[$key] = null;
                        echo "report to server error:" . $redisitem["ip"] . ":" . $redisitem["port"] . PHP_EOL;
                    }
                }
            }

            sleep(10);
            //sleep 10 sec and report again
        }
    }

    final public function onConnect($serv, $fd)
    {
        $this->taskInfo[$fd] = array();
    }

    final public function onWorkerStart($server, $worker_id)
    {
        $istask = $server->taskworker;
        if (!$istask) {
            //worker
            swoole_set_process_name("phpworker|{$worker_id}");
        } else {
            //task
            swoole_set_process_name("phptask|{$worker_id}");
            $this->initTask($server, $worker_id);
        }

    }

    abstract public function initTask($server, $worker_id);

    final public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        $reqa = Packet::packDecode($data);
        #decode error
        if ($reqa["code"] != 0) {
            $req = Packet::packEncode($reqa);
            $serv->send($fd, $req);

            return true;
        } else {
            $req = $reqa["data"];
        }

        #api not set
        if (!is_array($req["api"]) && count($req["api"])) {
            $pack = Packet::packFormat("param api is empty", 100003);
            $pack["guid"] = $req["guid"];
            $pack = Packet::packEncode($pack);
            $serv->send($fd, $pack);

            return true;
        }

        $this->taskInfo[$fd] = $req;

        $task = array(
            "type" => $this->taskInfo[$fd]["type"],
            "guid" => $this->taskInfo[$fd]["guid"],
            "fd" => $fd,
        );

        switch ($this->taskInfo[$fd]["type"]) {

            case DoraConst::SW_SYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $taskid = $serv->task($task);

                $this->taskInfo[$fd]["task"][$taskid] = "one";

                return true;
                break;
            case DoraConst::SW_ASYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $serv->task($task);

                $pack = Packet::packFormat("transfer success.已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = Packet::packEncode($pack);
                $serv->send($fd, $pack);

                unset($this->taskInfo[$fd]);

                return true;

                break;

            case DoraConst::SW_SYNC_MULTI:
                foreach ($req["api"] as $k => $v) {
                    $task["api"] = $this->taskInfo[$fd]["api"][$k];
                    $taskid = $serv->task($task);
                    $this->taskInfo[$fd]["task"][$taskid] = $k;
                }

                return true;
                break;
            case DoraConst::SW_ASYNC_MULTI:
                foreach ($req["api"] as $k => $v) {
                    $task["api"] = $this->taskInfo[$fd]["api"][$k];
                    $serv->task($task);
                }
                $pack = Packet::packFormat("transfer success.已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);

                return true;
                break;
            case DoraConst::SW_CONTROL_CMD:
                if ($this->taskInfo[$fd]["api"]["cmd"]["name"] == "getStat") {
                    $pack = Packet::packFormat("OK", 0, array("server" => $serv->stats()));
                    $pack["guid"] = $task["guid"];
                    $pack = Packet::packEncode($pack);
                    $serv->send($fd, $pack);
                    unset($this->taskInfo[$fd]);
                    return true;
                }

                //no one process
                $pack = Packet::packFormat("unknow cmd", 100011);
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);
                break;
            default:
                $pack = Packet::packFormat("unknow task type.未知类型任务", 100002);
                $pack = Packet::packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);

                return true;
        }

        return true;
    }

    final public function onTask($serv, $task_id, $from_id, $data)
    {
        swoole_set_process_name("phptask|{$task_id}|" . $data["api"]["name"] . "");
        try {
            $data["result"] = $this->doWork($data);
        } catch (\Exception $e) {
            $data["result"] = Packet::packFormat($e->getMessage(), $e->getCode());
        }

        //fixed the result more than 8k timeout bug
        $data = serialize($data);
        if (strlen($data) > 8000) {
            $temp_file = tempnam(sys_get_temp_dir(), 'swmore8k');
            file_put_contents($temp_file, $data);
            return '$$$$$$$$' . $temp_file;
        } else {
            return $data;
        }
    }

    abstract public function doWork($param);


    final public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        $this->log("WorkerError", array($this->taskInfo, $serv, $worker_id, $worker_pid, $exit_code));
    }

    private function log($type, $content, $file = "sw_error.log")
    {
        $result = date("Y-m-d H:i:s") . "|$type|" . json_encode($content) . "\r\n";
        file_put_contents("/tmp/" . $file, $result, FILE_APPEND);
    }

    final public function onFinish($serv, $task_id, $data)
    {
        //fixed the result more than 8k timeout bug
        if (strpos($data, '$$$$$$$$') === 0) {
            $tmp_path = substr($data, 8);
            $data = file_get_contents($tmp_path);
            unlink($tmp_path);
        }
        $data = unserialize($data);

        $fd = $data["fd"];

        if (!isset($this->taskInfo[$fd]) ) {
            unset($this->taskInfo[$fd]);

            return true;
        }

        $key = $this->taskInfo[$fd]["task"][$task_id];
        $this->taskInfo[$fd]["result"][$key] = $data["result"];

        unset($this->taskInfo[$fd]["task"][$task_id]);

        switch ($data["type"]) {

            case DoraConst::SW_SYNC_SINGLE:
                $Packet = Packet::packFormat("OK", 0, $data["result"]);
                $Packet["guid"] = $this->taskInfo[$fd]["guid"];
                $Packet = Packet::packEncode($Packet);

                //sys_get_temp_dir
                $serv->send($fd, $Packet);
                unset($this->taskInfo[$fd]);

                return true;
                break;

            case DoraConst::SW_SYNC_MULTI:
                if (count($this->taskInfo[$fd]["task"]) == 0) {
                    $Packet = Packet::packFormat("OK", 0, $this->taskInfo[$fd]["result"]);
                    $Packet["guid"] = $this->taskInfo[$fd]["guid"];
                    $Packet = Packet::packEncode($Packet);
                    $serv->send($fd, $Packet);
                    unset($this->taskInfo[$fd]);

                    return true;
                } else {
                    return true;
                }
                break;

            default:
                unset($this->taskInfo[$fd]);

                return true;
                break;
        }

    }

    final public function onClose(\swoole_server $server, $fd, $from_id)
    {
        unset($this->taskInfo[$fd]);
    }


    final public function __destruct()
    {
        $this->server->shutdown();
    }

}
