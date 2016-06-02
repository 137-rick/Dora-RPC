<?php
namespace DoraRPC;

class Packet
{

    public static function packFormat($msg = "OK", $code = 0, $data = array())
    {
        $pack = array(
            "code" => $code,
            "msg" => $msg,
            "data" => $data,
        );

        return $pack;
    }

    public static function packEncode($data)
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

    public static function packDecode($str)
    {
        $header = substr($str, 0, 4);
        $len = unpack("Nlen", $header);
        $len = $len["len"];

        if (DoraConst::SW_DATASIGEN_FLAG == true) {

            $signedcode = substr($str, 4, 4);
            $result = substr($str, 8);

            //check signed
            if (pack("N", crc32($result . DoraConst::SW_DATASIGEN_SALT)) != $signedcode) {
                return self::packFormat("Signed check error!", 100010);
            }

            $len = $len - 4;

        } else {
            $result = substr($str, 4);
        }
        if ($len != strlen($result)) {
            //结果长度不对
            echo "error length...\n";

            return self::packFormat("packet length invalid 包长度非法", 100007);
        }
        $result = unserialize($result);

        return self::packFormat("OK", 0, $result);
    }
}