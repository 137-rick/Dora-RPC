<?php

namespace DoraRPC\Layout\FrontEnd\Dispatcher;

use DoraRPC\Lib\Context;
use DoraRPC\Lib\Log;
use DoraRPC\Common\Func;
class Websocket extends BaseInterface
{
	protected $dispatcher;

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

	public function onRequest($request, $response)
	{
		Context::put("key", "test");
		$response->end(Context::get("key"));
	}
}
