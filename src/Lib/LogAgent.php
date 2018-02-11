<?php

namespace DoraRPC\Lib;

class LogAgent
{

	//最大dump日志阀值，当暂存日志超过这个数马上开始dump
	const MAX_LOG_DUMP_COUNT = 20;

	//var
	static $isinit = 0;

	static $dumplogmode = 0; //日志落地模式 0 直接写入文件。1 缓存定期写入文件。2 dump到channel 异步写入文件

	static $channel = null;

	static $logTempArray = array();

	private static $dumppath = "/tmp/";//default dump path

	/**
	 * 日志促使化
	 * @param string $logpath
	 */
	public static function init($logpath)
	{
		if (self::$channel == null) {
			self::$channel = new \swoole_channel(256 * 1024 * 1024);
		}

		//log dump path
		self::$dumppath = $logpath;
	}

	/**
	 * 获取日志落地队列状态
	 * @return mixed
	 */
	public static function getQueueStat()
	{
		//get queue stat
		return self::$channel->stats();
	}

	/**
	 * dump log
	 * @param array $log
	 */
	public static function log($log)
	{
		self::$channel->push($log);
	}

	/**
	 * 通过Channel、异步落地日志文件
	 * 适用swoole常驻多进程服务落地日志
	 * 建议启动独立process运行此函数
	 */
	public static function threadDumpLog()
	{
		//logagent buffer
		if (self::$channel == null) {
			throw new \Exception("Logagent Dump Log must run befor change mode", 11113);
		}

		//dump the log to the local
		$logCount = 0;
		$logContent = "";
		$startTime = microtime(true);

		while (true) {
			$log = self::$channel->pop();

			//ok add the log
			if ($log !== false) {
				$log = json_encode($log);
				$logContent = $logContent . "\n" . $log;
				$logCount++;
			} else {
				sleep(1);
			}

			//empty will continue
			if ($logContent === "") {
				continue;
			}

			//logcount大于阀值 || 过去时间3秒 dump日志
			if ($logCount > self::MAX_LOG_DUMP_COUNT || microtime(true) - $startTime > 3) {

				file_put_contents(self::$dumppath . "/" . date("Y-m-d") . "_dora.log", $logContent, FILE_APPEND);

				$logCount = 0;
				$logContent = "";
				$startTime = microtime(true);
			}
		}
	}

}