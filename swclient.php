<?php

/**
 * Class DoraRPCClient
 * https://github.com/xcl3721/Dora-RPC
 * by 蓝天 http://weibo.com/thinkpc
 */
class DoraRPCClient
{

    const SW_SYNC_SINGLE = 'SSS';
    const SW_RSYNC_SINGLE = 'SRS';

    const SW_SYNC_MULTI = 'SSM';
    const SW_RSYNC_MULTI = 'SRM';

    //a flag to sure check the crc32
    //是否开启数据签名，服务端客户端都需要打开，打开后可以强化安全，但会降低一点性能
    const SW_DATASIGEN_FLAG = false;

    //salt to mixed the crc result
    //上面开关开启后，用于加密串混淆结果，请保持客户端和服务端一致
    const SW_DATASIGEN_SALT = "=&$*#@(*&%(@";

    //client obj pool
    private static $client = array();

    //current obj ip port
    private $ip;
    private $port;

    //md5(ip+port)=obj_array_key cache
    private $objkey;

    function __construct($ip = "127.0.0.1", $port = 9567)
    {
        $this->ip = $ip;
        $this->port = $port;
    }

    //get current client obj
    private function getCurrentObjKey()
    {
        //to prevent wast add an key cache
        if ($this->objkey == "") {
            $ip = $this->ip;
            $port = $this->port;

            $key = $ip . "_" . $port;
            $this->objkey = $key;
        }

        return $this->objkey;
    }

    //destroy current client
    private function destroyCurrentObj()
    {
        $key = $this->getCurrentObjKey();
        unset(self::$client[$key]);
    }

    //get current client
    private function getClientObj()
    {
        $key = $this->getCurrentObjKey();

        if (!isset(self::$client[$key])) {
            $client = new swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            $client->set(array(
                'open_length_check' => 1,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
                'open_tcp_nodelay' => 1,
            ));

            if (!$client->connect($this->ip, $this->port, 3.0)) {
                //connect fail
                $errorcode = $client->errCode;
                if ($errorcode == 0) {
                    $msg = "connect fail.check host dns.";
                    $errorcode = -1;
                } else {
                    $msg = socket_strerror($errorcode);
                }

                throw new Exception($msg, $errorcode);
            }

            self::$client[$key] = $client;
        }

        //success
        return self::$client[$key];
    }

    /**
     * 单api请求
     * @param string $api api地址
     * @param array $param 参数
     * @param bool $sync 阻塞等待结果
     * @return mixed 返回单个请求结果
     */
    public function singleAPI($name, $param, $sync = true)
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

        //get client obj
        try {
            $client = $this->getClientObj();
        } catch (Exception $e) {
            return $this->packFormat($e->getMessage(), $e->getCode());
        }

        $ret = $client->send($sendData);

        //retry to reconnect && improve success percentage
        if (!$ret) {
            //clean up the broken client obj
            $this->destroyCurrentObj();

            //reconnect by get client obj
            try {
                $client = $this->getClientObj();
            } catch (Exception $e) {
                return $this->packFormat($e->getMessage(), $e->getCode());
            }
            //send again
            $ret = $client->send($sendData);
        }

        if (!$ret) {
            $errorcode = $client->errCode;
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

        $result = $client->recv();
        $result = $this->packDecode($result);

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
     * @param array $params 提交参数 请指定key好方便区分对应结果，注意考虑到硬件资源有限并发请求不要超过50个
     * @param bool $sync 阻塞等待所有结果
     * @return mixed 返回指定key结果
     * @throws exception 并发超过50 会报错
     */
    public function multiAPI($params, $sync = true)
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

        //get client obj
        try {
            $client = $this->getClientObj();
        } catch (Exception $e) {
            return $this->packFormat($e->getMessage(), $e->getCode());
        }

        $ret = $client->send($sendData);

        if (!$ret) {

            //destroy broken client
            $this->destroyCurrentObj();

            //reconnect
            try {
                $client = $this->getClientObj();
            } catch (Exception $e) {
                return $this->packFormat($e->getMessage(), $e->getCode());
            }

            //resend the request
            $ret = $client->send($sendData);
        }

        if (!$ret) {
            $errorcode = $client->errCode;
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

        $result = $client->recv();

        $result = $this->packDecode($result);

        if ($result["code"] == "0" && $guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wrong please retry..", 100008, $result);
        }
        return $result;
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


    function __destruct()
    {

    }
}
