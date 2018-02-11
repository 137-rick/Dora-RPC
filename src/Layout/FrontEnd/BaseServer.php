<?php

namespace DoraRPC\Layout\FrontEnd;

use DoraRPC\Common\Func;
use DoraRPC\Lib\Log;
use DoraRPC\Lib\LogAgent;
use DoraRPC\Lib\MemoryTable;

class BaseServer
{
	private $_config = null;

	private $_server = null;

	private $_subserver = array();

	private $_mainDispatcher = null;

	private $_subDispatcher = array();

	public $_monitorTable = null;

	private function getServerClassInfo($protocol)
	{
		$protocol = strtolower($protocol);
		switch ($protocol) {
			case "websocket":
				return array(
					"server_class" => "swoole_websocket_server",
					"socket" => \SWOOLE_SOCK_TCP,
					"option" => array(
						"open_websocket_protocol" => true,
					),
				);
				break;

			case "http":
				return array(
					"server_class" => "swoole_http_server",
					"socket" => \SWOOLE_SOCK_TCP,
					"option" => array(
						"open_websocket_protocol" => false,
						"open_http_protocol" => true,
					),
				);
				break;

			case "fixed_header_tcp":
				return array(
					"server_class" => "swoole_server",
					"socket" => \SWOOLE_SOCK_TCP,
					"option" => array(
						"open_websocket_protocol" => false,
						"open_http_protocol" => false,
						'open_length_check' => 1,
						'package_length_type' => 'N',
						'package_length_offset' => 0,
						'package_body_offset' => 4,
					),
				);
				break;
			case "eof_header_tcp":
				return array(
					"server_class" => "swoole_server",
					"socket" => \SWOOLE_SOCK_TCP,
					"option" => array(
						"open_websocket_protocol" => false,
						"open_http_protocol" => false,
						'open_eof_split' => true,
						'package_eof' => "\r\$\n",
					),
				);
				break;
			case "udp":
				return array(
					"server_class" => "swoole_server",
					"socket" => \SWOOLE_SOCK_UDP,
					"option" => array(
						"open_websocket_protocol" => false,
						"open_http_protocol" => false,
					),
				);
				break;
			case "mqtt":
				return array(
					"server_class" => "swoole_server",
					"socket" => \SWOOLE_SOCK_UDP,
					"option" => array(
						"open_websocket_protocol" => false,
						"open_http_protocol" => false,
						"open_mqtt_protocol" => true,
					),
				);
				break;
			default:
				echo "There is not found protocol setting by " . $protocol . PHP_EOL;
				exit;
		}
	}

	/**
	 * 启动服务
	 */
	public function start()
	{
		if ($this->_config == null) {
			echo "Config was not set!You must setup the config befor start" . PHP_EOL;
			exit;
		}

		//get main server class name and setting
		$mainClassInfo = $this->getServerClassInfo($this->_config["server"]["protocol"]);

		//create the main server
		$this->_server = new $mainClassInfo["server_class"](
			$this->_config["server"]["listen"],
			$this->_config["server"]["port"],
			\SWOOLE_PROCESS,
			$mainClassInfo["socket"]
		);

		//set the swoole config
		$this->_server->set(array_merge($this->_config["swoole"], $mainClassInfo["option"]));
		//Fend_Log::write(json_encode($this->_config["swoole"]));

		//load server dispatcher
		$dispatcherClassName = $this->_config["server"]["dispatcher"];

		if (!class_exists($dispatcherClassName)) {
			die("baseserver->Config->server->dispatcher class $dispatcherClassName was not found!");
		}

		//main dispatcher
		$this->_mainDispatcher = new $dispatcherClassName($this, $this->_server);

		//bind event with main dispatcher
		$this->_server->on('Start', array($this->_mainDispatcher, 'onStart'));
		$this->_server->on('Shutdown', array($this->_mainDispatcher, 'onShutdown'));

		$this->_server->on('WorkerStart', array($this->_mainDispatcher, 'onWorkerStart'));
		$this->_server->on('WorkerError', array($this->_mainDispatcher, 'onWorkerError'));
		$this->_server->on('WorkerStop', array($this->_mainDispatcher, 'onWorkerStop'));

		$this->_server->on('ManagerStart', array($this->_mainDispatcher, 'onManagerStart'));
		$this->_server->on('ManagerStop', array($this->_mainDispatcher, 'onManagerStop'));

		$this->_server->on('Task', array($this->_mainDispatcher, 'onTask'));
		$this->_server->on('Finish', array($this->_mainDispatcher, 'onFinish'));

		// protocol event register

		//TCP
		if (in_array(
			$this->_config["server"]["protocol"],
			array("fixed_header_tcp", "eof_header_tcp", "mqtt")
		)) {
			$this->_server->on('Connect', array($this->_mainDispatcher, 'onConnect'));
			$this->_server->on('Receive', array($this->_mainDispatcher, 'onReceive'));
			$this->_server->on('Close', array($this->_mainDispatcher, 'onClose'));
		}

		//WebSocket
		if ($this->_config["server"]["protocol"] == "websocket") {
			$this->_server->on('Open', array($this->_mainDispatcher, 'onOpen'));
			$this->_server->on('Message', array($this->_mainDispatcher, 'onMessage'));
			$this->_server->on('Request', array($this->_mainDispatcher, 'onRequest'));
			$this->_server->on('Close', array($this->_mainDispatcher, 'onClose'));
		}

		//http
		if ($this->_config["server"]["protocol"] == "http") {
			$this->_server->on('Request', array($this->_mainDispatcher, 'onRequest'));
		}

		//udp
		if ($this->_config["server"]["protocol"] == "udp") {
			$this->_server->on('Packet', array($this->_mainDispatcher, 'onPacket'));
			$this->_server->on('Receive', array($this->_mainDispatcher, 'onReceive'));
		}

		//sub listener protocol
		if (is_array($this->_config["listen"]) && count($this->_config["listen"]) > 0) {

			//create new listen with dispatcher
			foreach ($this->_config["listen"] as $listenerName => $config) {

				//add listener port with protocol
				$subClassInfo = $this->getServerClassInfo($config["protocol"]);
				$subserver =
					$this->_server->addListener(
						$config["listen"],
						$config["port"],
						$subClassInfo["socket"]
					);
				$this->_subserver[$listenerName] = $subserver;

				//load listen dispatcher
				$dispatcherClassName = $config["dispatcher"];

				//check dispatcher class exists
				if (!class_exists($dispatcherClassName)) {
					die("baseserver->Config->listen->" . $listenerName . "->dispatcher class $dispatcherClassName was not found!");
				}

				$dispatcher = new $dispatcherClassName($this, $subserver, $config);
				$this->_subDispatcher[$listenerName] = $dispatcher;
				// protocol event register

				//TCP
				if (in_array(
					$config["protocol"],
					array("fixed_header_tcp", "eof_header_tcp", "mqtt")
				)) {
					$subserver->on('Connect', array($dispatcher, 'onConnect'));
					$subserver->on('Receive', array($dispatcher, 'onReceive'));
					$subserver->on('Close', array($dispatcher, 'onClose'));
				}

				//WebSocket
				if ($config["protocol"] == "websocket") {
					$subserver->on('Open', array($dispatcher, 'onOpen'));
					$subserver->on('Message', array($dispatcher, 'onMessage'));
					$subserver->on('Request', array($dispatcher, 'onRequest'));
					$subserver->on('Close', array($dispatcher, 'onClose'));
				}

				//http
				if ($config["protocol"] == "http") {
					$subserver->on('Request', array($dispatcher, 'onRequest'));
				}

				//udp
				if ($config["protocol"] == "udp") {
					$subserver->on('Packet', array($dispatcher, 'onPacket'));
					$subserver->on('Receive', array($dispatcher, 'onReceive'));
				}

			}
		}

		//memory table
		$columnConfig = array(
			array("key" => 'int', "type" => \swoole_table::TYPE_INT, "len" => 8),
			array("key" => 'string', "type" => \swoole_table::TYPE_STRING, "len" => 32),
		);
		$this->_monitorTable = new MemoryTable($columnConfig, $this->_config["server"]["table_dump_path"], $this->_config["server"]["table_size"]);

		//设置日志存储路径
		LogAgent::init($this->_config["server"]["log_dir"]);

		//设置输出日志级别
		Log::setLogLevel($this->_config["server"]["log_level"]);

		//log agent for dump log
		$this->_server->addProcess(new \swoole_process(function () {
			Func::setProcessName($this->_config["server"]["server_name"], "log");
			LogAgent::threadDumpLog();
		}));

		Log::info("Server", __FILE__, __LINE__,
			"Server IP:" . $this->_config["server"]["listen"] . " Port:" . $this->_config["server"]["port"] . " LocalIP:" . Func::getLocalIp());

		$this->_server->start();

	}

	public function setConfig($config)
	{
		$this->_config = $config;
	}

	public function getConfig()
	{
		return $this->_config;
	}
}