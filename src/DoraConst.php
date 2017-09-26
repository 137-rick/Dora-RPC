<?php
namespace DoraRPC;

class DoraConst
{
    ///////////
    // 同步、异步不要结果、异步获取结果
    ///////////
    const SW_MODE_WAITRESULT = 0;
    const SW_MODE_NORESULT = 1;
    const SW_MODE_ASYNCRESULT = 2;

    //////////
    // 服务器调用方式类型
    //////////
    //sync wait task result
    //任务下发后阻塞等待结果
    const SW_MODE_WAITRESULT_SINGLE = 'W_S';
    const SW_MODE_WAITRESULT_MULTI = 'W_M';

    //async no need task result
    const SW_MODE_NORESULT_SINGLE = 'AN_S';
    const SW_MODE_NORESULT_MULTI = 'AN_M';

    //async send task and at end of code manual get result
    const SW_MODE_ASYNCRESULT_SINGLE = 'AM_S';
    const SW_MODE_ASYNCRESULT_MULTI = 'AM_M';

    //cmd for the server
    const SW_CONTROL_CMD = 'SC';

    //timeout limit when recive second
    //接收数据的超时时长，超过了就会断开 单位秒
    //目前只能做到统一超时，单个超时目前由于异步取回结果导致不准
    //可以考虑swoole_select方式制作单个请求设定超时机制
    const SW_RECIVE_TIMEOUT = 3.0;

    //a flag to sure check the crc32
    //是否开启数据签名，服务端客户端都需要打开，打开后可以强化安全，但会降低一点性能
    const SW_DATASIGEN_FLAG = false;

    //a flag to decide if compress the packet
    //是否打开数据压缩，目前我们用的数据压缩是zlib的gzencode，压缩级别4
    const SW_DATACOMPRESS_FLAG = false;

    //salt to mixed the crc result
    //上面开关开启后，用于加密串混淆结果，请保持客户端和服务端一致
    const SW_DATASIGEN_SALT = "=&$*#@(*&%(@";

    /////////////////
    //分级日志等级
    /////////////////

    //debug 信息，用于调试信息输出，默认不会输出，当在生产环境在线调试时使用
    const LOG_TYPE_DEBUG = 1;

    //trace
    const LOG_TYPE_TRACE = 2;

    //notice
    const LOG_TYPE_NOTICE = 3;

    //info 信息
    const LOG_TYPE_INFO = 4;

    //错误 信息
    const LOG_TYPE_ERROR = 5;

    //警报 信息
    const LOG_TYPE_EMEGENCY = 6;

    //异常 信息
    const LOG_TYPE_EXCEPTION = 7;

    //日志类型：性能日志
    const LOG_TYPE_SNAP = 8;

    //日志类型：埋点耗时性能日志
    const LOG_TYPE_PERFORMENCE = 9;
}
