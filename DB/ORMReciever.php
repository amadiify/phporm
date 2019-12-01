<?php

namespace Amadiify;

/*
 * @package Orm Reciever for DB Class 
 * @author wekiwork inc. 
 **/
 
class ORMReciever 
{
	// using singleton pattern
	private static $class_instance = [];

	// methods chained
	private $method_called = [];

	// db instance
	protected $instance = null;

	// id
	protected $id = 1;

	// table name
	protected $tableName = null;

	// get instance
	public static function getInstance(&$db_instance, $id=1, $orm=null)
	{
		if ($orm == null)
		{
			$caller = $db_instance->table;

			if (!isset(self::$class_instance[$caller]))
			{
				self::$class_instance[$caller] = new ORMReciever;
			}

			// set db instance
			self::$class_instance[$caller]->instance = $db_instance;

			// clean up method called
			self::$class_instance[$caller]->method_called = [];
			self::$class_instance[$caller]->id = $id;

			// return instance
			return self::$class_instance[$caller];
		}
	}	

	// __call magic method
	public function __call($method, $data)
	{
		return self::pushCall($method, $data, $this);
	}

	// get magic method
	public function __get($name)
	{
		if (count($this->method_called) > 0)
		{
			return $this->executeRequest()->{$name};
		}
	}

	// __callStatic magic method
	public static function __callStatic($method, $data)
	{

		return self::pushCall($method, $data);
	}

	// push call
	private static function pushCall($method, $data, $instance=null)
	{
		if (is_null($instance))
		{
			static $dbinstance;

			if (is_null($dbinstance))
			{
				$dbinstance = new Client();
			}

			$instance = new self;
			$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
			$readfile = new \SplFileObject($debug['file']);
			$before = [];

			foreach ($readfile as $i => $line)
			{
				if ($i == ($debug['line']-1))
				{
					// get table
					$line = trim($line);
					preg_match('/([_a-zA-Z0-9]+?)[:]{2}/', $line, $match);
					if (isset($match[1]))
					{
						$dbinstance->table = $match[1];
						$instance->instance = $dbinstance;
					}
					else
					{
						for ($x=$i; $x != 0; $x--)
						{
							$line = isset($before[$x]) ? $before[$x] : null;
							if (preg_match('/([_a-zA-Z0-9]+?)[:]{2}/', $line, $match) == true)
							{
								$dbinstance->table = $match[1];
								$instance->instance = $dbinstance;
								break;
							}
						}
					}
					break;
				}
				else
				{
					$before[] = $line;
				}
			}
			$readfile=null;
			$before = null;
		}

		if ($method != 'go' && !DBPromise::hasFetchMethod($method))
		{
			$instance->method_called[] = ['method' => $method, 'args' => $data];

			$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($instance->id + 1));

			$file = $debug[$instance->id];

			if (isset($file['file']))
			{
				$path = $file['file'];

				// read file
				$readfile = new \SplFileObject($path);
				foreach ($readfile as $index => $line)
				{
					if ($index == ($file['line']-1))
					{
						$line = preg_replace('/[\s]/', '', $line);
						$line = preg_replace('/(\$)(\w*)(->)/', '@', $line);

						$line = explode('->', $line);

						array_walk($line, function($e, $i) use (&$line){
							if (preg_match('/([a-zA-Z_0-9]+)[(]/', $e, $m))
							{

								$line[$i] = str_replace($m[0], '->'.$m[0], $e);
							}
							else
							{
								$line[$i] = '@'.$e;
							}
						});

						$line = implode('', $line);

						$exp = explode($file['type'], $line);

						if ($file['type'] == '::')
						{
							$line = strstr($line, '->'.$method);

							if (preg_match('/(->)([^(]+)[(]/', $line) == true)
							{
								$exp = explode('->', $line);
							}
						}

						foreach ($exp as $i => $e)
						{
							$quote = preg_quote("{$method}(", '/');

							if (preg_match("/^($quote)/", $e) == true)
							{
								
								if (strrpos($e, ');') !== false)
								{
									return $instance->executeRequest();
								}
								
							}
						}

						break;
					}
				}
			}
			else
			{
				
			}

			$readfile = null;
		}
		else
		{
			if (\Moorexa\DBPromise::hasFetchMethod($method))
			{
				$instance->method_called[] = ['method' => $method, 'args' => $data];
			}

			return $instance->executeRequest();
		}

		return $instance;	
	}

	// execute request
	public function executeRequest()
	{
		$process = true;

		if (!isset($this->instance->table))
		{
			$process = false;
		}

		// execute here.
		if ($process)
		{
			$instance = $this->instance;

			array_walk($this->method_called, function($arr) use (&$instance){
				$instance = call_user_func_array([$instance, $arr['method']], $arr['args']);
			});

			$this->method_called = [];

			$class = get_class($instance);

			if (is_string($class) && (strtolower($class) != 'amadiify\db\dbpromise'))
			{
				$send = $instance->go();
			}
			else
			{
				$send = $instance;
			}

			return $send;
		}

		return $this;
	}
}
