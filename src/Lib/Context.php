<?php

namespace DoraRPC\Lib;

class Context
{
	protected static $pool = [];

	static function get($key)
	{
		$cid = \Swoole\Coroutine::getuid();
		if ($cid < 0) {
			return null;
		}
		if (isset(self::$pool[$cid][$key])) {
			return self::$pool[$cid][$key];
		}
		return null;
	}

	static function put($key, $item)
	{
		$cid = \Swoole\Coroutine::getuid();
		if ($cid > 0) {
			self::$pool[$cid][$key] = $item;
		}

	}

	static function delete($key = null)
	{
		$cid = \Swoole\Coroutine::getuid();
		if ($cid > 0) {
			if ($key) {
				unset(self::$pool[$cid][$key]);
			} else {
				unset(self::$pool[$cid]);
			}
		}
	}
}