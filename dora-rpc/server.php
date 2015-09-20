<?php
namespace DoraRPC\Server;

/**
 * Class DoraRPCServer
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */
abstract class server
{
    const SW_SYNC_SINGLE = 'SSS';
    const SW_RSYNC_SINGLE = 'SRS';

    const SW_SYNC_MULTI = 'SSM';
    const SW_RSYNC_MULTI = 'SRM';

    const SW_CONTROL_CMD = 'SC';

    //a flag to sure check the crc32
    //是否开启数据签名，服务端客户端都需要打开，打开后可以强化安全，但会降低一点性能
    const SW_DATASIGEN_FLAG = false;

    //salt to mixed the crc result
    //上面开关开启后，用于加密串混淆结果，请保持客户端和服务端一致
    const SW_DATASIGEN_SALT = "=&$*#@(*&%(@";

    private $server = null;
    private $taskInfo = array();

    //for extends class overwrite default config
    //用于继承类覆盖默认配置
    protected $externalConfig = array();

    abstract public function initServer($server);

    final public function __construct($ip = "0.0.0.0", $port = 9567)
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

        $this->server->start();
    }

    final public function onConnect($serv, $fd)
    {
        $this->taskInfo[$fd] = array();
    }

    final public function onWorkerStart($server, $worker_id)
    {
        $istask = $server->taskworker;
        if ($istask) {
            //worker
            swoole_set_process_name("swworker|{$worker_id}");
            $this->initTask($server, $worker_id);
        } else {
            //task
            swoole_set_process_name("swtask|{$worker_id}");
        }

    }

    abstract public function initTask($server, $worker_id);

    final public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        $reqa = $this->packDecode($data);
        #decode error
        if ($reqa["code"] != 0) {
            $req = $this->packEncode($reqa);
            $serv->send($fd, $req);

            return true;
        } else {
            $req = $reqa["data"];
        }

        #api not set
        if (!is_array($req["api"]) && count($req["api"])) {
            $pack = $this->packFormat("param api is empty", 100003);
            $pack = $this->packEncode($pack);
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

            case self::SW_SYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $taskid = $serv->task($task);

                $this->taskInfo[$fd]["task"][$taskid] = "one";

                return true;
                break;
            case self::SW_RSYNC_SINGLE:
                $task["api"] = $this->taskInfo[$fd]["api"]["one"];
                $serv->task($task);

                $pack = $this->packFormat("已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = $this->packEncode($pack);
                $serv->send($fd, $pack);

                unset($this->taskInfo[$fd]);

                return true;

                break;

            case self::SW_SYNC_MULTI:
                foreach ($req["api"] as $k => $v) {
                    $task["api"] = $this->taskInfo[$fd]["api"][$k];
                    $taskid = $serv->task($task);
                    $this->taskInfo[$fd]["task"][$taskid] = $k;
                }

                return true;
                break;
            case self::SW_RSYNC_MULTI:
                foreach ($req["api"] as $k => $v) {
                    $task["api"] = $this->taskInfo[$fd]["api"][$k];
                    $serv->task($task);
                }
                $pack = $this->packFormat("已经成功投递", 100001);
                $pack["guid"] = $task["guid"];
                $pack = $this->packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);

                return true;
                break;
            default:
                $pack = $this->packFormat("未知类型任务", 100002);
                $pack = $this->packEncode($pack);

                $serv->send($fd, $pack);
                unset($this->taskInfo[$fd]);

                return true;
        }

        return true;
    }

    final public function onTask($serv, $task_id, $from_id, $data)
    {
        //$data["result"] = array("yes" => "ok");
        swoole_set_process_name("phptask|{$task_id}|" . $data["api"]["name"] . "");
        try {
            $data["result"] = $this->doWork($data);
        } catch (\Exception $e) {
            $data["result"] = $this->packFormat($e->getMessage(), $e->getCode());

            return $data;
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

        if (!isset($this->taskInfo[$fd]) || !$data["result"]) {
            unset($this->taskInfo[$fd]);

            return true;
        }

        $key = $this->taskInfo[$fd]["task"][$task_id];
        $this->taskInfo[$fd]["result"][$key] = $data["result"];

        unset($this->taskInfo[$fd]["task"][$task_id]);

        switch ($data["type"]) {

            case self::SW_SYNC_SINGLE:
                $packet = $this->packFormat("OK", 0, $data["result"]);
                $packet["guid"] = $this->taskInfo[$fd]["guid"];
                $packet = $this->packEncode($packet);

                //sys_get_temp_dir
                $serv->send($fd, $packet);
                unset($this->taskInfo[$fd]);

                return true;
                break;

            case self::SW_SYNC_MULTI:
                if (count($this->taskInfo[$fd]["task"]) == 0) {
                    $packet = $this->packFormat("OK", 0, $this->taskInfo[$fd]["result"]);
                    $packet["guid"] = $this->taskInfo[$fd]["guid"];
                    $packet = $this->packEncode($packet);
                    $serv->send($fd, $packet);
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


    private function packEncode($data)
    {
        $sendStr = serialize($data);

        if (self::SW_DATASIGEN_FLAG == true) {
            $signedcode = pack('N', crc32($sendStr . self::SW_DATASIGEN_SALT));
            $sendStr = pack('N', strlen($sendStr) + 4) . $signedcode . $sendStr;
        } else {
            $sendStr = pack('N', strlen($sendStr)) . $sendStr;
        }

        return $sendStr;
    }

    private function packDecode($str)
    {
        $header = substr($str, 0, 4);
        $len = unpack("Nlen", $header);
        $len = $len["len"];

        if (self::SW_DATASIGEN_FLAG == true) {

            $signedcode = substr($str, 4, 4);
            $result = substr($str, 8);

            //check signed
            if (pack("N", crc32($result . self::SW_DATASIGEN_SALT)) != $signedcode) {
                return $this->packFormat("Signed check error!", 100010);
            }

            $len = $len - 4;

        } else {
            $result = substr($str, 4);
        }

        if ($len != strlen($result)) {
            //结果长度不对
            echo "error length...\n";

            return $this->packFormat("包长度非法", 100007);
        }
        $result = unserialize($result);

        return $this->packFormat("OK", 0, $result);
    }

    private function packFormat($msg = "OK", $code = 0, $data = array())
    {
        $pack = array(
            "code" => $code,
            "msg" => $msg,
            "data" => $data,
        );

        return $pack;
    }

    final public function __destruct()
    {
        $this->server->shutdown();
    }

}
