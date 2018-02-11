<?php
require_once "init.php";

use DoraRPC\Layout\FrontEnd\BaseServer;
use DoraRPC\Lib\Log;

$config =
	array(
		//主服务，选主服务 建议按 websocket（http） > http > udp || tcp 顺序创建
		"server" => array(
			//server name
			"server_name" => "Server",

			//listener setting
			"listen" => "0.0.0.0",
			"port" => 80,

			//option: websocket|http|fixed_header_tcp|eof_header_tcp|udp|mqtt
			"protocol" => "websocket",

			//request dispatcher
			"dispatcher" => "\DoraRPC\Layout\FrontEnd\Dispatcher\Websocket",

			//log
			"log_level" => Log::LOG_TYPE_INFO,
			"log_dir" => dirname(__DIR__) . '/logs/',

			//memory table
			"table_size" => 2048,
			"table_dump_path" => "./memorydump.json"
		),

		"listen" => array(
			"http_server" => array(

				//listener setting
				"listen" => "0.0.0.0",
				"port" => 9589,

				//option: websocket|http|fixed_header_tcp|eof_header_tcp|udp|mqtt
				"protocol" => "http",

				//request dispatcher
				"dispatcher" => "\DoraRPC\Layout\FrontEnd\Dispatcher\Http",
			),

		),

		"swoole" => array(
			//'user' => 'www',
			//'group' => 'www',
			'dispatch_mode' => 7,

			'package_max_length' => 2097152, // 1024 * 1024 * 2,
			'buffer_output_size' => 3145728, //1024 * 1024 * 3,
			'pipe_buffer_size' => 33554432, //1024 * 1024 * 32,

			'backlog' => 30000,
			'open_tcp_nodelay' => 1,

			'heartbeat_idle_time' => 180,
			'heartbeat_check_interval' => 60,

			'open_cpu_affinity' => 1,
			'worker_num' => 10,
			'task_worker_num' => 10,

			'max_request' => 20000,
			'task_max_request' => 4000,

			'discard_timeout_request' => false,

			//swoole 日志级别 Info
			'log_level' => 2,

			//swoole 系统日志，任何代码内echo都会在这里输出
			'log_file' => '/tmp/baseserver.log',

			//task 投递内容过长时，会临时保存在这里，请将tmp设置使用内存
			'task_tmpdir' => '/dev/shm/baseserver/',

			//进程pid保存文件路径，请写绝对路径
			'pid_file' => '/data/log/baseserver.pid',

			//静态文件请求路径
			'document_root' => dirname(__DIR__) . '/WebRoot/',
			'enable_static_handler' => true,

			'daemonize' => 0,
		),
	);


$server = new BaseServer();
$server->setConfig($config);
$server->start();

