<?php
namespace DoraRPC;

/**
 * Class Client
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */

class Client
{

    //client obj pool
    private static $client = array();

    //for the async task list
    private static $asynclist = array();

    //for the async task result
    private static $asynresult = array();

    //client config array
    private $serverConfig = array();

    //when connect fail will block error config
    private $serverConfigBlock = array();

    //this request guid
    private $guid;

    //1 random from specify group,2 specify by ip port
    //1 随机从指定group名称内选择客户端，2 指定ip进行连接
    private $connectMode = 0;

    //是否使用上一次已连接connectclient
    //用于减少单机长链接数
    //private $connectReuse = true;

    //current connect ip port
    private $connectIp = "";
    private $connectPort = 0;

    //config group name
    private $connectGroup = "";

    //current using client obj key on static client array
    private $currentClientKey = "";

    public function __construct($serverConfig)
    {
        if (count($serverConfig) == 0) {
            echo "cant found config on the Dora RPC..";
            throw new \Exception("please set the config param on init Dora RPC", -1);
        }
        $this->serverConfig = $serverConfig;
    }

    //$param = array("type"=>1,"group"=>"group1");
    //$param = array("type"=>2,"ip"=>"127.0.0.1","port"=>9567);

    /**
     * 更换连接模式，用于指定ip请求和普通请求切换
     * @param $param
     * @throws \Exception unknow mode parameter
     */
    public function changeMode($param)
    {
        if ($param["type"] == 1) {
            if ($param["type"] == "" || $param["group"] == "") {
                throw new \Exception("change mode parameter is wrong", -1);
            }
            $this->connectMode = 1;
            $this->connectGroup = $param["group"];
            $this->connectIp = "";
            $this->connectPort = "";
            $this->currentClientKey = "";
        } else if ($param["type"] == 2) {

            if ($param["type"] == "" || $param["ip"] == "" || $param["port"] == "") {
                throw new \Exception("change mode parameter is wrong", -1);
            }
            $this->connectMode = 2;
            $this->connectGroup = "default";
            $this->connectIp = $param["ip"];
            $this->connectPort = $param["port"];
            $this->currentClientKey = "";

        } else {
            throw new \Exception("change mode parameter is wrong", -1);
        }
    }

    /**
     * 返回当前连接模式及相关信息
     * @return array
     * @throws \Exception unknow mode
     */
    public function getConnectMode()
    {
        if ($this->connectMode == 1) {
            return array("type" => 1, "group" => $this->connectGroup, "ip" => $this->connectIp, "port" => $this->connectPort);
        } else if ($this->connectMode == 2) {
            return array("type" => 2, "group" => "", "ip" => $this->connectIp, "port" => $this->connectPort);
        } else {
            throw new \Exception("current connect mode is unknow", -1);
        }
    }

    //random get config key
    private function getConfigObjKey()
    {

        if (!isset($this->serverConfig[$this->connectGroup])) {
            throw new \Exception("there is no one server can connect", 100010);
        }
        // if there is no config can use clean up the block list
        if (isset($this->serverConfigBlock[$this->connectGroup]) &&
            count($this->serverConfig[$this->connectGroup]) <= count($this->serverConfigBlock[$this->connectGroup])
        ) {
            //clean up the block list
            $this->serverConfigBlock[$this->connectGroup] = array();
        }

        //if not specified the ip and port random get one
        do {
            //get one config by random
            $key = array_rand($this->serverConfig[$this->connectGroup]);

            //if not on the block list.
            if (!isset($this->serverConfigBlock[$this->connectGroup][$key])) {
                return $key;
            }

        } while (count($this->serverConfig[$this->connectGroup]) > count($this->serverConfigBlock));

        throw new \Exception("there is no one server can connect", 100010);

    }

    //get current client
    private function getClientObj()
    {
        //config obj key
        $key = "";

        //if not spc will random
        if ($this->connectMode == 1) {
            $key = $this->getConfigObjKey();
            $clientKey = $this->serverConfig[$this->connectGroup][$key]["ip"] . "_" . $this->serverConfig[$this->connectGroup][$key]["port"];
            //set the current client key
            $this->currentClientKey = $clientKey;
            $connectHost = $this->serverConfig[$this->connectGroup][$key]["ip"];
            $connectPort = $this->serverConfig[$this->connectGroup][$key]["port"];
        } else if ($this->connectMode == 2) {
            //using spec
            $clientKey = trim($this->connectIp) . "_" . trim($this->connectPort);
            //set the current client key
            $this->currentClientKey = $clientKey;
            $connectHost = $this->connectIp;
            $connectPort = $this->connectPort;
        } else {
            throw new \Exception("current connect mode is unknow", -1);
        }

        if (!isset(self::$client[$clientKey])) {
            $client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            $client->set(array(
                'open_length_check' => 1,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
                'open_tcp_nodelay' => 1,
            ));

            if (!$client->connect($connectHost, $connectPort, DoraConst::SW_RECIVE_TIMEOUT)) {
                //connect fail
                $errorCode = $client->errCode;
                if ($errorCode == 0) {
                    $msg = "connect fail.check host dns.";
                    $errorCode = -1;
                } else {
                    $msg = socket_strerror($errorCode);
                }

                if ($key !== "") {
                    //put the fail connect config to block list
                    $this->serverConfigBlock[$this->connectGroup][$key] = 1;
                }

                throw new \Exception($msg . " " . $clientKey, $errorCode);
            }

            self::$client[$clientKey] = $client;
        }

        //success
        return self::$client[$clientKey];
    }

    /**
     * 获取应用服务器信息 get the backend service stat
     * @param string $ip
     * @param string $port
     * @return array
     */

    public function getStat($ip = "", $port = "")
    {
        $beformode = $this->getConnectMode();

        if ($ip != "" && $port != "") {
            $mode = array("type" => 2, "ip" => $ip, "port" => $port);
            $this->changeMode($mode);
        }

        $this->guid = $this->generateGuid();

        $Packet = array(
            'guid' => $this->guid,
            'api' => array(
                "cmd" => array(
                    'name' => "getStat",
                    'param' => array(),
                ),
            ),
            'type' => DoraConst::SW_CONTROL_CMD,
        );

        $sendData = Packet::packEncode($Packet);
        $result = $this->doRequest($sendData, DoraConst::SW_MODE_WAITRESULT_SINGLE);

        if ($this->guid != $result["guid"]) {
            return Packet::packFormat("guid wront please retry..", 100100, $result["data"]);
        }

        //revert befor connect mode
        if ($ip != "" && $port != "") {
            //revert befor mode
            $this->changeMode($beformode);
        }
        return $result["data"];
    }

    public function reloadServerTask($ip = "", $port = "")
    {
        $beformode = $this->getConnectMode();

        if ($ip != "" && $port != "") {
            $mode = array("type" => 2, "ip" => $ip, "port" => $port);
            $this->changeMode($mode);
        }

        $this->guid = $this->generateGuid();

        $Packet = array(
            'guid' => $this->guid,
            'api' => array(
                "cmd" => array(
                    'name' => "reloadTask",
                    'param' => array(),
                ),
            ),
            'type' => DoraConst::SW_CONTROL_CMD,
        );

        $sendData = Packet::packEncode($Packet);
        $result = $this->doRequest($sendData, DoraConst::SW_MODE_WAITRESULT_SINGLE);

        if ($this->guid != $result["guid"]) {
            return Packet::packFormat("guid wront please retry..", 100100, $result["data"]);
        }

        //revert befor connect mode
        if ($ip != "" && $port != "") {
            //revert befor mode
            $this->changeMode($beformode);
        }
        return $result["data"];
    }

    /*
     * mode 参数更改说明，以前版本只是sync参数不是mode
     * sync :
     *      true 代表是否阻塞等待结果，
     *      false 下发任务成功后就返回不等待结果，用于对接口返回没有影响的操作提速
     * 改版后----
     * mode :
     *      0 代表阻塞等待任务执行完毕拿到结果 ；
     *      1 代表下发任务成功后就返回不等待结果 ；
     *      2 代表下发任务成功后直接返回guid 然后稍晚通过调用阻塞接收函数拿到所有结果
     */
    /**
     * 单api请求
     * @param  string $name api地址
     * @param  array $param 参数
     * @param  int $mode
     * @param  int $retry 通讯错误时重试次数
     * @param  string $ip 要连得ip地址，如果不指定从现有配置随机个
     * @param  string $port 要连得port地址，如果不指定从现有配置找一个
     * @return mixed  返回单个请求结果
     * @throws \Exception unknow mode type
     */
    public function singleAPI($name, $param, $mode = DoraConst::SW_MODE_WAITRESULT, $retry = 0, $ip = "", $port = "")
    {
        //get guid
        $this->guid = $this->generateGuid();

        $Packet = array(
            'guid' => $this->guid,
            'api' => array(
                "one" => array(
                    'name' => $name,
                    'param' => $param,
                )
            ),
        );

        if ($mode == DoraConst::SW_MODE_WAITRESULT) {
            $Packet["type"] = DoraConst::SW_MODE_WAITRESULT_SINGLE;
        } elseif ($mode == DoraConst::SW_MODE_NORESULT) {
            $Packet["type"] = DoraConst::SW_MODE_NORESULT_SINGLE;
        } elseif ($mode == DoraConst::SW_MODE_ASYNCRESULT) {
            $Packet["type"] = DoraConst::SW_MODE_ASYNCRESULT_SINGLE;
        } else {
            throw new \Exception("unknow mode have been set", 100099);
        }

        $sendData = Packet::packEncode($Packet);

        $result = $this->doRequest($sendData, $Packet["type"]);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData, $Packet["type"]);
            $retry--;
        }

        if ($this->guid != $result["guid"]) {
            return Packet::packFormat("guid wront please retry..", 100100, $result["data"]);
        }

        return $result;
    }

    /**
     * 并发请求api，使用方法如
     * $params = array(
     *  "api_1117"=>array("name"=>"apiname1",“param”=>array("id"=>1117)),
     *  "api_2"=>array("name"=>"apiname2","param"=>array("id"=>2)),
     * )
     * @param  array $params 提交参数 请指定key好方便区分对应结果，注意考虑到硬件资源有限并发请求不要超过50个
     * @param  int $mode
     * @param  int $retry 通讯错误时重试次数
     * @param  string $ip 要连得ip地址，如果不指定从现有配置随机个
     * @param  string $port 要连得port地址，如果不指定从现有配置找一个
     * @return mixed 返回指定key结果
     * @throws \Exception unknow mode type
     */
    public function multiAPI($params, $mode = DoraConst::SW_MODE_WAITRESULT, $retry = 0, $ip = "", $port = "")
    {
        //get guid
        $this->guid = $this->generateGuid();

        $Packet = array(
            'guid' => $this->guid,
            'api' => $params,
        );

        if ($mode == DoraConst::SW_MODE_WAITRESULT) {
            $Packet["type"] = DoraConst::SW_MODE_WAITRESULT_MULTI;
        } else if ($mode == DoraConst::SW_MODE_NORESULT) {
            $Packet["type"] = DoraConst::SW_MODE_NORESULT_MULTI;
        } else if ($mode == DoraConst::SW_MODE_ASYNCRESULT) {
            $Packet["type"] = DoraConst::SW_MODE_ASYNCRESULT_MULTI;
        } else {
            throw new \Exception("unknow mode have been set", 100099);
        }

        $sendData = Packet::packEncode($Packet);

        $result = $this->doRequest($sendData, $Packet["type"]);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData, $Packet["type"]);
            $retry--;
        }

        if ($this->guid != $result["guid"]) {
            return Packet::packFormat("guid wront please retry..", 100100, $result["data"]);
        }

        return $result;
    }


    private function doRequest($sendData, $type)
    {
        //get client obj
        try {
            $client = $this->getClientObj();
        } catch (\Exception $e) {
            $data = Packet::packFormat($e->getMessage(), $e->getCode());
            $data["guid"] = $this->guid;
            return $data;
        }

        $ret = $client->send($sendData);

        //ok fail
        if (!$ret) {
            $errorcode = $client->errCode;

            //destroy error client obj to make reconncet
            unset(self::$client[$this->currentClientKey]);

            if ($errorcode == 0) {
                $msg = "connect fail.check host dns.";
                $errorcode = -1;
                $Packet = Packet::packFormat($msg, $errorcode);
            } else {
                $msg = socket_strerror($errorcode);
                $Packet = Packet::packFormat($msg, $errorcode);
            }

            return $Packet;
        }

        //if the type is async result will record the guid and client handle
        if ($type == DoraConst::SW_MODE_ASYNCRESULT_MULTI || $type == DoraConst::SW_MODE_ASYNCRESULT_SINGLE) {
            self::$asynclist[$this->guid] = $client;
        }

        //recive the response
        $data = $this->waitResult($client);
        $data["guid"] = $this->guid;
        return $data;
    }

    //for the loop find the right result
    //save the async result to the asyncresult static var
    //return the right guid request
    private function waitResult($client)
    {
        while (1) {
            $result = $client->recv();

            if ($result !== false && $result != "") {
                $data = Packet::packDecode($result);
                //if the async result first deploy success will
                if ($data["data"]["guid"] != $this->guid) {

                    // this data was not we want
                    //it's may the async result
                    //when the guid on the asynclist and have isresult =1  on data is async result
                    //when the guid on the asynclist not have isresult field ond data is firsy success deploy msg

                    if (isset(self::$asynclist[$data["data"]["guid"]]) && isset($data["data"]["isresult"]) && $data["data"]["isresult"] == 1) {

                        //ok recive an async result
                        //remove the guid on the asynclist
                        unset(self::$asynclist[$data["data"]["guid"]]);

                        //add result to async result
                        self::$asynresult[$data["data"]["guid"]] = $data["data"];
                        self::$asynresult[$data["data"]["guid"]]["fromwait"] = 1;
                    } else {
                        //not in the asynclist drop this packet
                        continue;
                    }
                } else {
                    //founded right data
                    return $data;
                }
            } else {
                //time out
                $packet = Packet::packFormat("the recive wrong or timeout", 100009);
                $packet["guid"] = $this->guid;
                return $packet;
            }
        }
    }

    public function getAsyncData()
    {
        //wait all the async result
        //when  timeout all the error will return
        //这里有个坑，我不知道具体哪个client需要recive
        while (1) {

            if (count(self::$asynclist) > 0) {
                foreach (self::$asynclist as $k => $client) {
                    if ($client->isConnected()) {
                        $data = $client->recv();
                        if ($data !== false && $data != "") {
                            $data = Packet::packDecode($data);

                            if (isset(self::$asynclist[$data["data"]["guid"]]) && isset($data["data"]["isresult"]) && $data["data"]["isresult"] == 1) {

                                //ok recive an async result
                                //remove the guid on the asynclist
                                unset(self::$asynclist[$data["data"]["guid"]]);

                                //add result to async result
                                self::$asynresult[$data["data"]["guid"]] = $data["data"];
                                self::$asynresult[$data["data"]["guid"]]["fromwait"] = 0;
                                continue;
                            } else {
                                //not in the asynclist drop this packet
                                continue;
                            }
                        } else {
                            //remove the result
                            unset(self::$asynclist[$k]);
                            self::$asynresult[$k] = Packet::packFormat("the recive wrong or timeout", 100009);
                            continue;
                        }
                    } else {
                        //remove the result
                        unset(self::$asynclist[$k]);
                        self::$asynresult[$k] = Packet::packFormat("Get Async Result Fail: Client Closed.", 100012);
                        continue;
                    }
                } // foreach the list
            } else {
                break;
            }
        }//while

        $result = self::$asynresult;
        self::$asynresult = array();
        return Packet::packFormat("OK", 0, $result);
    }

    //clean up the async list and result
    public function clearAsyncData()
    {
        self::$asynresult = array();
        self::$asynclist = array();
    }

    private function generateGuid()
    {
        //to make sure the guid is unique for the async result
        while (1) {
            $guid = md5(microtime(true) . mt_rand(1, 1000000) . mt_rand(1, 1000000));
            //prevent the guid on the async list
            if (!isset(self::$asynclist[$guid])) {
                return $guid;
            }
        }
    }


    public function __destruct()
    {

    }
}
