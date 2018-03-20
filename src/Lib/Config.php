<?php

namespace DoraRPC\Lib;

class Config
{
    private static $_config = array();

    private static $_configPath = array();


    /**
     * 加载配置文件，如果不存在返回false
     * @param $filepath
     * @return bool 成功返回true
     */
    public static function loadConfig($filepath)
    {
        //file is exists
        if (!file_exists($filepath)) {
            return false;
        }

        //pareser ini file
        $config = parse_ini_file($filepath);

        //nothing?
        if (count($config) == 0) {
            return false;
        }

        //success
        self::$_config     = $config;
        self::$_configPath = $filepath;
        return true;
    }

    /**
     * 获取swoole基础配置选项
     * @return array|mixed
     */
    public static function getSwooleConfig()
    {
        if (isset(self::$_config["swoole"])) {
            return self::$_config["swoole"];
        }
        return array();
    }
}