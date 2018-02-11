<?php

namespace DoraRPC\Lib;

class MemoryTable
{
	private $_table = null;

	// dumppath 日志落地位置需填写绝对路径
	private $_dumppath = "/tmp/dump.json";

	private $_tablesize = 2048;

	/**
	 * Fend_MemoryTable constructor.
	 * table创建 columns为列定义、dumppath为tabledump数据文件路径、table最大记录数
	 * @param array $columns 数组key、type、len，定义table列
	 * @param string $dumppath 备份地址
	 * @param int $tablesize 最大数据量
	 */
	function __construct($columns, $dumppath, $tablesize)
	{
		$this->_dumppath = $dumppath;
		$this->_tablesize = $tablesize;

		$table = new \swoole_table($this->_tablesize);
		foreach ($columns as $col) {
			$table->column($col["key"], $col["type"], $col["len"]);
		}
		$table->create();

		$this->_table = $table;

		$this->loadTableRecord();
	}

	/**
	 * 从文件中加载table数据到内存中
	 * 用于从文件恢复table数据
	 */
	public function loadTableRecord()
	{
		//load table data from file
		if (file_exists($this->_dumppath)) {
			$record = file_get_contents($this->_dumppath);

			if ($record) {
				$record = json_decode($record, true);
				foreach ($record as $k => $item) {
					$this->_table->set($k, $item);
				}
			}
		}
	}

	/**
	 * 备份当前table数据到文件，根据参数过滤掉指定前缀数据或key
	 * @param array $excludekey 不包括key
	 * @param array $excluedePrefix 不包括带这些前缀的key
	 */
	public function dumpTableRecord($excludekey = array(), $excluedePrefix = array())
	{
		//table store to the file
		$statics = array();
		foreach ($this->_table as $k => $v) {
			//过滤掉指定前缀数据或指定key数据

			$verifyed = true;
			foreach ($excluedePrefix as $prefix) {
				if (stripos($k, $prefix) === 0) {
					$verifyed = false;
					break;
				}
			}
			//过滤掉指定前缀数据或指定key数据
			if (!$verifyed || in_array($k, $excludekey)) {
				continue;
			}

			$statics[$k] = $v;
		}
		file_put_contents($this->_dumppath, json_encode($statics));
	}


	/**
	 * 根据前缀搜索数据，并返回列表
	 * @param $prefix
	 * @return array
	 */
	public function getListByPrefix($prefix)
	{
		$pidCountList = array();
		foreach ($this->_table as $k => $v) {
			if (stripos($k, $prefix) === 0) {
				$pidCountList[$k] = $v;
			}
		}
		return $pidCountList;
	}

	/**
	 * 获取一个key
	 * @param $key
	 * @return array
	 */
	public function get($key)
	{
		return $this->_table->get($key);
	}

	/**
	 * 获得某个key下的某个field
	 * @param $key
	 * @param $field
	 * @return string/int/float or false
	 * */
	public function getField($key, $field)
	{
		$col = $this->_table->get($key);
		if (!$col) {
			return false;
		}
		return $col[$field];
	}

	/**
	 * 修改key值
	 * @param string $key
	 * @param array $val
	 * @return string|int|null
	 */
	public function set($key, $val)
	{
		return $this->_table->set($key, $val);
	}

	/**
	 * 删除key
	 * @param $key
	 * @return bool
	 */
	public function del($key)
	{
		return $this->_table->del($key);
	}

	/**
	 * 获取所有kv值
	 * @return array
	 */
	public function getList()
	{
		$list = array();
		foreach ($this->_table as $k => $v) {
			$list[$k] = $v;
		}
		return $list;
	}
}