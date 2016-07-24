<?php
namespace DoraRPC;

class Monitor
{
    protected $_server = null;

    protected $_ip = "0.0.0.0";
    protected $_port = 9569;

    protected $_config;

    //server report
    final public function discovery(array $config)
    {
        $self = $this;

        $this->_server->addProcess(new \swoole_process(function () use ($config, $self) {

            swoole_set_process_name("php monitor service discovery");

            static $_redisObj = array();

            while (true) {
                //for result list
                $server_list_result = array();

                //get redis config
                $redisconfig = $self->_config["redis"];

                //connect all redis
                foreach ($redisconfig as $redisitem) {
                    //validate redis ip and port
                    if (trim($redisitem["ip"]) && $redisitem["port"] > 0) {
                        $key = $redisitem["ip"] . "_" . $redisitem["port"];
                        try {
                            //connecte redis
                            if (!isset($_redisObj[$key])) {
                                //if not connect
                                $_redisObj[$key] = new \Redis();
                                $_redisObj[$key]->connect($redisitem["ip"], $redisitem["port"]);
                            }

                            //get register node server
                            $server_list = $_redisObj[$key]->smembers("dora.serverlist");
                            if ($server_list) {
                                foreach ($server_list as $sitem) {
                                    $info = json_decode($sitem, true);
                                    //decode success
                                    if ($info) {
                                        //get lsta report time
                                        $lasttimekey = "dora.servertime." . $info["node"]["ip"] . "." . $info["node"]["port"] . ".time";
                                        $lastupdatetime = $_redisObj[$key]->get($lasttimekey);

                                        //timeout ignore
                                        if (time() - $lastupdatetime > 20) {
                                            continue;
                                        }

                                        if (is_array($info["group"])) {
                                            foreach ($info["group"] as $groupname) {
                                                $clientkey = $info["node"]["ip"] . "_" . $info["node"]["port"];
                                                $server_list_result[$groupname][$clientkey] = array("ip" => $info["node"]["ip"], "port" => $info["node"]["port"]);
                                                $server_list_result[$groupname][$clientkey]["updatetime"] = $lastupdatetime;
                                            }
                                        }
                                        //foreach group and record this info
                                    }//decode info if
                                }// foreach
                            }//if got server list from redis

                        } catch (\Exception $ex) {
                            //var_dump($ex);
                            $_redisObj[$key] = null;
                            echo "get redis server error" . PHP_EOL;
                        }
                    }
                }

                if (count($server_list_result) > 0) {

                    $configString = var_export($server_list_result, true);
                    $ret = file_put_contents($this->_config["export_path"], "<?php" . PHP_EOL . "//This is generaled by client monitor" . PHP_EOL . "return " . $configString . ";");
                    if (!$ret) {
                        echo "Error save the config to file..." . PHP_EOL;
                    } else {
                        echo "General config file to:" . $this->_config["export_path"] . PHP_EOL;
                    }
                } else {
                    echo "Error there is no Config get..." . PHP_EOL;
                }

                //sleep 10 sec
                sleep(10);
            }
        }));
    }

    public function __construct($ip = "0.0.0.0", $port = 9569, $config = array())
    {
        //record ip:port
        $this->_ip = $ip;
        $this->_port = $port;

        //create server object
        $this->_server = new \swoole_server($ip, $port, \SWOOLE_PROCESS, \SWOOLE_SOCK_UDP);
        //set config
        $this->_server->set(array(
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

            'reactor_num' => 1,
            'worker_num' => 2,
            'task_worker_num' => 0,

            'max_request' => 0, //必须设置为0否则并发任务容易丢,don't change this number
            'task_max_request' => 4000,

            'backlog' => 2000,
            'log_file' => '/tmp/sw_monitor.log',
            'task_tmpdir' => '/tmp/swmonitor/',
            'daemonize' => 0,//product env is 1
        ));

        //register the event
        $this->_server->on('Packet', array($this, 'onPacket'));

        echo "Start Init Server udp://" . $ip . ":" . $port . PHP_EOL;

        //store the list of redis
        $this->_config["redis"] = $config["discovery"];

        //store the avaliable node list to this config file
        $this->_config["export_path"] = $config["config"];

        //log monitor path
        $this->_config["log_path"] = $config["log"];

        $this->discovery($this->_config["redis"]);
    }

    public function start()
    {
        $this->_server->start();
    }

    public function onPacket(\swoole_server $server, $data, $client_info)
    {
        //$data = \DoraDRPC\Base\Packet::packDecode($data);
        //$server->sendto($client_info['address'], $client_info['port'], \DoraDRPC\Base\Packet::packEncode(array()));

        //var_dump($server, $data);
    }

}
