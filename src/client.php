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

    //current using client obj key on static client array
    private $currentClientKey = "";

    //client config array
    private $serverConfig = array();

    //when connect fail will block error config
    private $serverConfigBlock = array();

    public function __construct($serverConfig)
    {
        if (count($serverConfig) == 0) {
            echo "cant found config on the Dora RPC..";
            throw new \Exception("please set the config param on init Dora RPC", -1);
        }
        $this->serverConfig = $serverConfig;
    }

    //random get config key
    private function getConfigObjKey($ip = "", $port = "")
    {

        // if there is no config can use clean up the block list
        if (count($this->serverConfig) <= count($this->serverConfigBlock)) {
            //clean up the block list
            $this->serverConfigBlock = array();
        }

        //if not specified the ip and port random get one
        if ($ip == "" || $port == "") {
            do {
                //get one config by random
                $key = array_rand($this->serverConfig);

                //if not on the block list.
                if (!isset($this->serverConfigBlock[$key])) {
                    return $key;
                }

            } while (count($this->serverConfig) > count($this->serverConfigBlock));

        } else {
            //search the config and find out the key
            foreach ($this->serverConfig as $k => $configitem) {
                if ($configitem["ip"] == $ip && $configitem["port"] == $port) {
                    return $k;
                }
            }

            //ok insert
            $this->serverConfig[] = array("ip" => $ip, "port" => $port);

            //found again T_T..
            foreach ($this->serverConfig as $k => $configitem) {
                if ($configitem["ip"] == $ip && $configitem["port"] == $port) {
                    return $k;
                }
            }
        }
        throw new \Exception("there is no one server can connect", 100010);

    }

    //get current client
    private function getClientObj($ip = "", $port = "")
    {
        $key = $this->getConfigObjKey($ip, $port);
        $clientKey = $this->serverConfig[$key]["ip"] . "_" . $this->serverConfig[$key]["port"];
        //set the current client key
        $this->currentClientKey = $clientKey;

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

            if (!$client->connect($this->serverConfig[$key]["ip"], $this->serverConfig[$key]["port"], DoraConst::SW_RECIVE_TIMEOUT)) {
                //connect fail
                $errorCode = $client->errCode;
                if ($errorCode == 0) {
                    $msg = "connect fail.check host dns.";
                    $errorCode = -1;
                } else {
                    $msg = socket_strerror($errorCode);
                }

                //put the fail connect config to block list
                $this->serverConfigBlock[$key] = 1;
                throw new \Exception($msg, $errorCode);
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
        $guid = md5(uniqid() . microtime(true) . rand(1, 1000000));
        $packet = array(
            'guid' => $guid,
            'api' => array(
                "cmd" => array(
                    'name' => "getStat",
                    'param' => array(),
                ),
            ),
            'type' => DoraConst::SW_CONTROL_CMD,
        );

        $sendData = $this->packEncode($packet);
        $result = $this->doRequest($sendData, $ip, $port);

        if ($result["code"] == "0" && $guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wront please retry..", 100100, $result);
        }

        return $result;
    }

    /**
     * 单api请求
     * @param  string $name api地址
     * @param  array $param 参数
     * @param  bool $sync 阻塞等待结果
     * @param  int $retry 通讯错误时重试次数
     * @param  string $ip 要连得ip地址，如果不指定从现有配置随机个
     * @param  string $port 要连得port地址，如果不指定从现有配置找一个
     * @return mixed  返回单个请求结果
     */
    public function singleAPI($name, $param, $sync = true, $retry = 0, $ip = "", $port = "")
    {
        $guid = md5(uniqid() . microtime(true) . rand(1, 1000000));
        $packet = array(
            'guid' => $guid,
            'api' => array(
                "one" => array(
                    'name' => $name,
                    'param' => $param,
                )
            ),
        );

        if ($sync) {
            $packet["type"] = DoraConst::SW_SYNC_SINGLE;
        } else {
            $packet["type"] = DoraConst::SW_ASYNC_SINGLE;
        }

        $sendData = $this->packEncode($packet);

        $result = $this->doRequest($sendData, $ip, $port);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData, $ip, $port);
            $retry--;
        }

        if ($result["code"] == "0" && $guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wront please retry..", 100100, $result);
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
     * @param  bool $sync 阻塞等待所有结果
     * @param  int $retry 通讯错误时重试次数
     * @param  string $ip 要连得ip地址，如果不指定从现有配置随机个
     * @param  string $port 要连得port地址，如果不指定从现有配置找一个
     * @return mixed 返回指定key结果
     */
    public function multiAPI($params, $sync = true, $retry = 0, $ip = "", $port = "")
    {

        $guid = md5(uniqid() . microtime(true) . rand(1, 1000000));
        $packet = array(
            'guid' => $guid,
            'api' => $params,
        );

        if ($sync) {
            $packet["type"] = DoraConst::SW_SYNC_MULTI;
        } else {
            $packet["type"] = DoraConst::SW_ASYNC_MULTI;
        }

        $sendData = $this->packEncode($packet);

        $result = $this->doRequest($sendData, $ip, $port);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData, $ip, $port);
            $retry--;
        }

        if ($result["code"] == "0" && $guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wrong please retry..", 100008, $result);
        }

        return $result;
    }

    private function doRequest($sendData, $ip = "", $port = "")
    {
        //get client obj
        try {
            $client = $this->getClientObj($ip, $port);
        } catch (\Exception $e) {
            return $this->packFormat($e->getMessage(), $e->getCode());
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
                $packet = $this->packFormat($msg, $errorcode);
            } else {
                $msg = socket_strerror($errorcode);
                $packet = $this->packFormat($msg, $errorcode);
            }

            return $packet;
        }

        //recive the response
        $result = $client->recv();
        //recive error check
        if ($result !== false) {
            return $this->packDecode($result);
        } else {
            return $this->packFormat("the recive wrong or timeout", 100009);
        }

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

    private function packEncode($data)
    {
        $sendStr = serialize($data);
        if (DoraConst::SW_DATASIGEN_FLAG == true) {
            $signedcode = pack('N', crc32($sendStr . DoraConst::SW_DATASIGEN_SALT));
            $sendStr = pack('N', strlen($sendStr) + 4) . $signedcode . $sendStr;
        } else {
            $sendStr = pack('N', strlen($sendStr)) . $sendStr;
        }

        return $sendStr;
    }

    private function packDecode($str)
    {
        $header = substr($str, 0, 4);

        if (DoraConst::SW_DATASIGEN_FLAG == true) {

            $signedcode = substr($str, 4, 4);
            $result = substr($str, 8);

            //check signed
            if (pack("N", crc32($result . DoraConst::SW_DATASIGEN_SALT)) != $signedcode) {
                return $this->packFormat("Signed check error!", 100010);
            }
        } else {
            $result = substr($str, 4);
        }

        $len = unpack("Nlen", $header);
        $result = unserialize($result);

        return $this->packFormat("OK", 0, $result);
    }

    public function __destruct()
    {

    }
}
