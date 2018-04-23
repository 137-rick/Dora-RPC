<?php

namespace DoraRPC\Layout\FrontEnd\Dispatcher;

use DoraRPC\Common\Func;
use DoraRPC\Lib\Log;

abstract class BaseInterface
{
	//服务主server obj
	protected $_mainObj = null;

	//配置
	protected $_config = null;

	//服务名称,返回信息header会带这个
	protected $_serverName = "";

	public function __construct($mainobj)
	{
		$this->_mainObj = $mainobj;
		$this->_config = $mainobj->getConfig();
		$this->_serverName = $this->_config["server"]["server_name"];
	}

	public function onStart(\swoole_server $server)
	{
		Func::setProcessName($this->_config["server"]["server_name"], "master");
		Log::info("server_start", __FILE__, __LINE__, "Server is Start");

	}

	public function onShutdown(\swoole_server $server)
	{
		$this->_mainObj->_monitorTable->dumpTableRecord();
		Log::info("server_shutdown", __FILE__, __LINE__, "Server is Close");
	}

	public function onWorkerStart(\swoole_server $server, $worker_id)
	{
		if (!$server->taskworker) {
			//worker
			Func::setProcessName($this->_config["server"]["server_name"], "worker");
			Log::info("worker_start", __FILE__, __LINE__, "Worker Process Stared id:$worker_id");

		} else {
			//task
			Func::setProcessName($this->_config["server"]["server_name"], "task");
			Log::info("task_start", __FILE__, __LINE__, "Task Process Stared id:$worker_id");
		}
	}

	public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code, $signal)
	{
		Log::error("worker_error", __FILE__, __LINE__, "worker_id:$worker_id,worker_pid:$worker_pid,code:$exit_code,signal:$signal");
	}

	public function onWorkerStop(\swoole_server $server, $worker_id)
	{

	}

	public function onManagerStart(\swoole_server $serv)
	{
		Func::setProcessName($this->_config["server"]["server_name"], "manager");
		Log::info("manager_start", __FILE__, __LINE__, "Manager Process Stared");

	}

	public function onManagerStop(\swoole_server $serv)
	{
		Log::info("manager_stop", __FILE__, __LINE__, "Manager Process Stopped");
	}


	public function onConnect(\swoole_server $server, $fd, $from_id)
	{

	}

	public function onReceive(\swoole_server $server, $fd, $reactor_id, $data)
	{

	}

	public function onPacket(\swoole_server $server, $data, $client_info)
	{

	}


	public function onClose(\swoole_server $server, $fd, $reactorId)
	{

	}

	public function onTask(\swoole_server $serv, $task_id, $src_worker_id, $data)
	{

	}

	public function onFinish(\swoole_server $serv, $task_id, $data)
	{

	}

	////////////////http

	public function onRequest($request, $response)
	{

	}

	//////////////// Websocket
	public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
	{

	}

	public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame)
	{

	}

	//获取当前请求信息，header，post，get，body，url
	public function getRequestInfo()
	{
		return array(
			"header" => array(),
			"post" => array(),
			"get" => array(),
			"body" => "",
			"client_ip" => "",
			"domain" => "",
			"ip" => "",
			"port" => "",
			"uri" => "",
		);
	}

	//返回信息
	public function response($code, $msg, $data)
	{
        return array(
            "code" => $code,
            "msg" => $msg,
            "data" => $data
        );
	}


}