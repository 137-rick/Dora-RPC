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

    private static $client;

    private $ip;
    private $port;

    function __construct($ip = "127.0.0.1", $port = 9567)
    {
        if (!self::$client) {
            self::$client = new swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            self::$client->set(array(
                'open_length_check' => 1,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
                'open_tcp_nodelay' => 1,
            ));
        }

        $this->ip = $ip;
        $this->port = $port;

        $ret = $this->connect();

        if ($ret["code"] != 0) {
            return $ret;
        } else {
            return $this->packFormat();
        }
    }

    private function connect()
    {
        if (!self::$client->connect($this->ip, $this->port, 3.0)) {
            $errorcode = self::$client->errCode;
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
        #success
        return $this->packFormat();
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

        $ret = self::$client->send($sendData);
        if (!$ret) {
            $this->connect();
            $ret = self::$client->send($sendData);
        }

        if (!$ret) {
            $errorcode = self::$client->errCode;
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

        $result = self::$client->recv();
        $result = $this->packDecode($result);

        if ($guid != $result["data"]["guid"]) {
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

        $this->connect();
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

        $ret = self::$client->send($sendData);

        if (!$ret) {
            $this->connect();
            $ret = self::$client->send($sendData);
        }

        if (!$ret) {
            $errorcode = self::$client->errCode;
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

        $result = self::$client->recv();

        $result = $this->packDecode($result);

        if ($guid != $result["data"]["guid"]) {
            return $this->packFormat("guid wront please retry..", 100008, $result);
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
        $sendStr = pack('N', strlen($sendStr)) . $sendStr;
        return $sendStr;
    }

    private function packDecode($str)
    {
        $header = substr($str, 0, 4);
        $result = substr($str, 4);

        $len = unpack("Nlen", $header);
        $result = unserialize($result);

        return $this->packFormat("OK", 0, $result);
    }


    function __destruct()
    {

    }
}
