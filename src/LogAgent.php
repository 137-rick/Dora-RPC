<?php
namespace DoraRPC;

class LogAgent
{

    static $logagent = null;

    static $table = null;

    private static $dumppath = "/tmp/bizlog/";//the biz log dump path

    public static function init($logpath, $table)
    {
        //log dump path
        self::$dumppath = $logpath;

        //logagent buffer
        if (self::$logagent == null) {
            self::$logagent = new \Swoole\Channel(256 * 1024 * 1024);
        }

        //table point
        if (self::$table == null) {
            self::$table = $table;
        }
    }

    public static function setLogLevel($level)
    {
        //set the log level
        if ($level > 9 || $level < 1) {
            return;
        } else {
            //init other parameter from config
            self::$table->set("log_level", array('value' => $level));
        }
    }

    public static function getQueueStat()
    {
        //get queue stat
        return self::$logagent->stats();
    }

    public static function recordLog($level, $tag, $file, $line, $msg)
    {
        $loglevel = self::$table->get("log_level");
        $loglevel = $loglevel["value"];

        //ignore the level log
        if ($loglevel < $level) {
            return;
        }

        //t type ,p path,l line, m msg,g tag,e time,c cost
        $log = array(
            'v' => $level,
            'e' => microtime(true),
            'g' => $tag,
            'p' => $file,
            'l' => $line,
            'm' => $msg,
        );

        //send log
        self::$logagent->push($log);
    }

    public static function threadDumpLog()
    {
        swoole_set_process_name("dora: logdumper");

        //dump the log to the local
        $logcount = 0;
        $logstr = "";
        $startime = microtime(true);

        while (true) {
            $log = self::$logagent->pop();

            //ok add the log
            if ($log !== false) {
                $log = json_encode($log);

                $logstr = $logstr . "\n" . $log;
                $logcount++;
            }

            //logcount大于100条，过去时间3秒 dump日志
            if ($logcount > 100 || microtime(true) - $startime > 3) {
                //todo:dump log
                $logcount = 0;
                $logstr = "";
                $startime = microtime(true);
            }

        }

    }
}