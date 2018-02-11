<?php

namespace DoraRPC\Layout\BackEnd;

class APIServer
{
	private $_server;

	public function __construct($ip, $port)
	{
		$this->_server = new \swoole_websocket_server($ip, $port);

		$this->_server->on("message", function (\swoole_server $server, \swoole_websocket_frame $frame) {
			$server->push($frame->fd, $frame->data);

		});

		$this->_server->on("request", function ($request, $response) {
			$time = microtime(true);

			$response->header("Content-Type", "text/plain");
			$cli = new \Co\http\Client("127.0.0.1", 9501);
			$cli->set([ 'timeout' => 1]);
			$ret = $cli->upgrade("/");
			if ($ret) {
				for($i=0;$i<100;$i++) {
					$cli->push("hello");
					$cli->push("hello");

					var_dump($cli->recv());
				}
			}
			$response->end($ret);
			$cost = bcsub(microtime(true), $time, 4);
		});

	}

	public function loadConfig($filepath)
	{
		$this->_server->set([
			'worker_num' => 4,
		]);
	}

	public function start()
	{
		$this->_server->start();
	}
}