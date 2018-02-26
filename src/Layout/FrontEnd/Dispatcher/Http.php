<?php
namespace DoraRPC\Layout\FrontEnd\Dispatcher;

use DoraRPC\Common\Func;
use DoraRPC\Lib\Log;

class Http extends BaseInterface
{
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
}