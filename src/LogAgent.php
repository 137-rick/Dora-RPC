<?php
namespace DoraRPC;

class LogAgent
{

    static $logagent = null;

    private $dumppath = "/tmp/bizlog/";

    static $loglevel = DoraConst::LOG_TYPE_INFO;

    final public function __construct($logpath)
    {
        //log dump path
        if ($logpath != "") {
            $this->dumppath = $logpath;
        }

        //logagent buffer
        if (self::$logagent == null) {
            self::$logagent = new \Swoole\Channel(256 * 1024 * 1024);
        }
    }

    public function getQueueStat()
    {
        return self::$logagent->stats();
    }

    public function setLogLevel($level)
    {
        if ($level > 9 || $level < 1) {
            return;
        } else {
            self::$loglevel = $level;
        }
    }

    public function recordLog($level, $tag, $file, $line, $msg)
    {
        //t type ,p path,l line, m msg,g tag,e time,c cost
        $log = array(
            'v' => $level,
            'e' => microtime(true),
            'g' => $tag,
            'p' => $file,
            'l' => $line,
            'm' => $msg,
        );
        self::$logagent->push($log);
    }

    public function threadDumpLog()
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