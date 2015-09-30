<?php
namespace DoraRPC;

class DoraConst
{
    const SW_SYNC_SINGLE = 'SSS';
    const SW_RSYNC_SINGLE = 'SRS';

    const SW_SYNC_MULTI = 'SSM';
    const SW_RSYNC_MULTI = 'SRM';

    const SW_CONTROL_CMD = 'SC';

    //timeout limit when recive second
    //接收数据的超时时长，超过了就会断开 单位秒
    const SW_RECIVE_TIMEOUT = 3.0;

    //a flag to sure check the crc32
    //是否开启数据签名，服务端客户端都需要打开，打开后可以强化安全，但会降低一点性能
    const SW_DATASIGEN_FLAG = false;

    //salt to mixed the crc result
    //上面开关开启后，用于加密串混淆结果，请保持客户端和服务端一致
    const SW_DATASIGEN_SALT = "=&$*#@(*&%(@";
}