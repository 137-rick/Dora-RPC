<?php

namespace DoraRPC\Layout\FrontEnd\Dispatcher;

use DoraRPC\Common\Func;

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
	}

	public function onStart(\swoole_server $server)
	{
		Func::setProcessName($this->_config["server"]["server_name"], "master");
	}

	public function onShutdown(\swoole_server $server)
	{
		$this->_mainObj->_monitorTable->dumpTableRecord();
	}

	public function onWorkerStart(\swoole_server $server, $worker_id)
	{
		if (!$server->taskworker) {
			//worker
			Func::setProcessName($this->_config["server"]["server_name"], "worker");
		} else {
			//task
			Func::setProcessName($this->_config["server"]["server_name"], "task");
		}
	}

	public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code, $signal)
	{

	}

	public function onWorkerStop(\swoole_server $server, $worker_id)
	{

	}

	public function onManagerStart(\swoole_server $serv)
	{
		Func::setProcessName($this->_config["server"]["server_name"], "manager");
	}

	public function onManagerStop(\swoole_server $serv)
	{

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

	}


}