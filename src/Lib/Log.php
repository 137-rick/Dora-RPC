<?php

namespace DoraRPC\Lib;

class Log
{
	//debug
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

	private static $log_level = self::LOG_TYPE_INFO;

	/**
	 * 书写原始日志文件
	 * @param $msg
	 * @param string $file
	 */
	public static function rawwrite($msg, $file = '/tmp/raw.log')
	{
		// 将数据转换为字符串
		is_array($msg) && $msg = var_export($msg, true);

		// 写入日志
		file_put_contents($file, $msg . "\n", FILE_APPEND);
	}


	/**
	 * 设置当前系统最低记录日志级别
	 * @param $level
	 */
	public static function setLogLevel($level)
	{
		//set the log level
		if ($level > 9 || $level < 1) {
			return;
		} else {
			//init other parameter from config
			self::$log_level = $level;
		}
	}

	/**
	 * debug 日志输出,用于线下寻找bug使用，日志量很多 平时不要开
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function debug($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_DEBUG) {
			return;
		}

		self::recordLog("log.debug", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * trace 跟踪信息,用于线下数据过程变量内容输出，日志量很多 平时不要开
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function trace($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_TRACE) {
			return;
		}
		self::recordLog("log.trace", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * 注意信息,用于线上线下警告数据，生产环境推荐开到info
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function notice($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_NOTICE) {
			return;
		}
		self::recordLog("log.notice", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * 提示信息，用于一些需要注意的日志信息
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function info($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_INFO) {
			return;
		}
		self::recordLog("log.info", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * 线上错误信息
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function error($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_ERROR) {
			return;
		}
		self::recordLog("log.error", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * 线上警报信息，后续会对这个进行合并发送警报邮件
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 警报文字原因
	 * @param array $extra 附加数据
	 */
	public static function alarm($tag, $file, $line, $msg, $extra = array())
	{
		//ignore the level log
		if (self::$log_level > self::LOG_TYPE_EMEGENCY) {
			return;
		}
		self::recordLog("log.alarm", $tag, $file, $line, $msg, $extra);
	}

	/**
	 * 线上警报信息，后续会对这个进行合并发送警报邮件
	 * @param string $tag 标识符，可以用module_function_action 形式
	 * @param string $file 文件路径可以使用__FILE__作为传入
	 * @param int $line 当前产生日志的代码行数，可以使用__LINE__
	 * @param string $msg 具体信息，请使用数组
	 * @param string $code 错误码
	 * @param string $backtrace 具体错误信息
	 * @param array $extra 附加数据
	 */
	public static function exception($tag, $file, $line, $msg, $code = "", $backtrace = "", $extra = array())
	{
		$extra["code"] = $code;
		$extra["backtrace"] = $backtrace;
		self::recordLog("log.exception", $tag, $file, $line, $msg, $extra);
	}

	private static function recordLog($logname, $tag, $file, $line, $msg, $extra = array())
	{

		if (is_array($msg)) {
			$msg = json_encode($msg);
		}

		$log = array(
			"x_name" => $logname,
			"x_timestamp" => time(),
			"x_module" => $tag,
			"x_pid" => getmypid(),
			"x_file" => basename($file),
			"x_line" => $line,
			"x_msg" => $msg,
		);

		$extraList = array();

		if ($extra) {
			//addtional log
			foreach ($extra as $key => $val) {
				if (in_array($key, array("duration", "code", "backtrace", "dns_duration", "source", "uid", "server_ip",
										 "client_ip", "user_agent", "host", "instance_name", "db", "action", "param",
										 "response", "response_length"))) {
					$log["x_" . $key] = $val;
				} else {
					$extraList[$key] = $val;
				}
			}
		}

		$log["x_extra"] = json_encode($extraList);

		LogAgent::log($log);
	}


	public static function getLogLevelName($level)
	{
		switch ($level) {
			case 1:
				return "debug";
				break;
			case 2:
				return "trace";
				break;
			case 3:
				return "notice";
				break;
			case 4:
				return "info";
				break;
			case 5:
				return "error";
				break;
			case 6:
				return "alarm";
				break;
			case 7:
				return "exception";
				break;
			default:
				return "unknow:" . $level;
		}
	}
}

