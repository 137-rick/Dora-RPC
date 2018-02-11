<?php

namespace DoraRPC\Layout\FrontEnd\Dispatcher;

use DoraRPC\Lib\Context;

class Websocket extends BaseInterface
{

	public function onRequest($request, $response)
	{
		Context::put("key","test");
		echo Context::get("key");
	}
}