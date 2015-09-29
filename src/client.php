<?php
namespace DoraRPC\Client;

/**
 * Class Client
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */
class Client
{
    const SW_SYNC_SINGLE = 'SSS';
    const SW_RSYNC_SINGLE = 'SRS';

    const SW_SYNC_MULTI = 'SSM';
    const SW_RSYNC_MULTI = 'SRM';

    const SW_CONTROL_CMD = 'SC';

    //timeout limit when recive
    //接收数据的超时时长，超过了就会断开
    const SW_RECIVE_TIMEOUT = 3.0;

    //a flag to sure check the crc32
    //是否开启数据签名，服务端客户端都需要打开，打开后可以强化安全，但会降低一点性能
    const SW_DATASIGEN_FLAG = false;

    //salt to mixed the crc result
    //上面开关开启后，用于加密串混淆结果，请保持客户端和服务端一致
    const SW_DATASIGEN_SALT = "=&$*#@(*&%(@";

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
    private function getConfigObjKey()
    {

        // if there is no config can use clean up the block list
        if (count($this->serverConfig) <= count($this->serverConfigBlock)) {
            //clean up the block list
            $this->serverConfigBlock = array();
        }

        do {
            //get one config by random
            $key = array_rand($this->serverConfig);

            //if not on the block list.
            if (!isset($this->serverConfigBlock[$key])) {
                return $key;
            }

        } while (count($this->serverConfig) > count($this->serverConfigBlock));

        throw new \Exception("there is no one server can connect", 100010);
    }

    //get current client
    private function getClientObj()
    {
        $key = $this->getConfigObjKey();
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

            if (!$client->connect($this->serverConfig[$key]["ip"], $this->serverConfig[$key]["port"], self::SW_RECIVE_TIMEOUT)) {
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
     * 单api请求
     * @param  string $name  api地址
     * @param  array  $param 参数
     * @param  bool   $sync  阻塞等待结果
     * @param  int    $retry 通讯错误时重试次数
     * @return mixed  返回单个请求结果
     */
    public function singleAPI($name, $param, $sync = true, $retry = 0)
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
            $packet["type"] = self::SW_SYNC_SINGLE;
        } else {
            $packet["type"] = self::SW_RSYNC_SINGLE;
        }

        $sendData = $this->packEncode($packet);

        $result = $this->doRequest($sendData);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData);
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
     * @param  bool  $sync   阻塞等待所有结果
     * @param  int   $retry  通讯错误时重试次数
     * @return mixed 返回指定key结果
     */
    public function multiAPI($params, $sync = true, $retry = 0)
    {

        $guid = md5(uniqid() . microtime(true) . rand(1, 1000000));
        $packet = array(
            'guid' => $guid,
            'api' => $params,
        );

        if ($sync) {
            $packet["type"] = self::SW_SYNC_MULTI;
        } else {
            $packet["type"] = self::SW_RSYNC_MULTI;
        }

        $sendData = $this->packEncode($packet);

        $result = $this->doRequest($sendData);

        //retry when the send fail
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData);
            $retry--;
        }

        if ($result["code"] == "0" && $guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wrong please retry..", 100008, $result);
        }

        return $result;
    }

    private function doRequest($sendData)
    {
        //get client obj
        try {
            $client = $this->getClientObj();
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

        if (self::SW_DATASIGEN_FLAG == true) {

            $signedcode = substr($str, 4, 4);
            $result = substr($str, 8);

            //check signed
            if (pack("N", crc32($result . self::SW_DATASIGEN_SALT)) != $signedcode) {
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
