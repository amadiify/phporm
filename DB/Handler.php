<?php

namespace Amadiify;

/**
 *
 * @package Moorexa Database connection handler
 * @author  Amadi Ifeanyi
 * @version 0.0.1
 **/

class Handler
{
	// database connection vars
	protected $dbvars = [];

	// active connection
	private static $active = [];

	// static vars
	protected static $_vars = [];

	// connect with
	public static $connectWith;

	// default source
	public static $default;

	// current running driver
	public static $driver;

	// manage total requests
	public static $totalRequests;

	// cache failed requests
	public static $failedRequests = [];

	// extablish a new connection
	public static $newConnection = false;

	// save connection
	public static $connection = [];

	// db set
	public static $dbset = false;	

	// force prod
	public static $forceProductionMode = false;

	// app mode
	private static $isOnline = false;


	// create connection
	private static function createConnection( &$source, $vars )
	{
		// create connection or serve existing
		switch (!isset(self::$connection[$source]))
		{
			// create new connection
			case true:
				// check if db source exists
				switch (isset($vars[$source]))
				{
					// load configuration from Connection.php
					case true:
						$settings = $vars[$source];
						// has production array config or string
						if (isset($settings['production']))
						{
							// continue loading production config?
							if ($continue)
							{	
								//self::settingsVars($settings, $vars[$source]);
							}
						}
						// use attributes
						$useAttribute = isset($settings['attributes']) ? $settings['attributes'] == true ? true : false : false;
						// default handler
						$handler = isset($settings['handler']) ? strtolower($settings['handler']) : 'pdo';
						// extract dsn
						$dsn = $settings['dsn'];
						// save driver.
						self::$driver = $settings['driver'];
						// set current connection
						self::$connectWith = $source;
						// manage dsn
						preg_match_all('/[\{]\s{0}(\w{1,}\s{0})\s{0}[\}]/', $dsn, $matches);
						// walk
						array_walk($matches[1], function($val) use (&$dsn, &$settings)
						{
							if (isset($settings[$val]))
							{
								$dsn = str_replace('{'.$val.'}', $settings[$val], $dsn);
							}
						});

						// get socket if running development server.
						if ((!isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REQUEST_QUERY_STRING'])) && !isset($settings['unix_socket']))
						{
							if (!self::$isOnline && !self::iswin())
							{
								$socks = shell_exec('netstat -ln | grep '.$settings['driver']);
								$socks = trim(substr($socks, strpos($socks, '/')));
								if (mb_strlen($socks) > 1)
								{
									$dsn .= ';unix_socket='.$socks;
								}
								// #clean up
								$socks = null;
							}
						}

						// get and set options
						$options = [];

						if ($handler == 'pdo')
						{
							$options = [
								\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
								\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
								\PDO::ATTR_EMULATE_PREPARES => true
							];
						}

						// flattern options to single array
						if (isset($settings['options']))
						{
							$options = array_merge($options, $settings['options']);
						}

						try
						{
							// make connection
							switch ($handler)
							{
								// pdo
								case 'pdo':
									$obj = new \PDO($dsn, $settings['user'], $settings['password']);
									// set attributes
									if ($useAttribute)
									{
										array_walk($options, function($val, $attr) use (&$obj){
											$obj->setAttribute($attr, $val);
										});
									}
								break;

								// mysql
								case 'mysql':
								case 'mysqli':
									$obj = new \mysqli($settings['host'], $settings['user'], $settings['password'], $settings['dbname']);
									// error occured?
									if ($obj->connect_error)
									{
										throw new \Exception("Error Connecting to database.");
									}
									
									mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
									$obj->set_charset($settings['charset']);
								break;
							}

							// save connection and return handle
							self::$active[$settings['driver']] = $obj;
								
							self::$connection[$source] = $obj;

							return $obj;
						}
						catch(\PDOException $e)
						{
							throw new \Exception($e->getMessage());
						}
					break;	

					// throw exception
					case false:
						throw new \Exception('Invalid Database Source. Not found in DB VARS. Database connection failed.');
					break;
				}
			break;

			// serve existing
			case false:
			    return self::$connection[$source];
			break;
		}
	}

	// return active connection
	public static function active($con = false)
	{	
		return self::createConnection( $con, Handler::getProtected('_vars'));
	}

	// return configuration settings
	public static function connectionConfig(&$source, $return = null)
	{
		$vars = Handler::getProtected('_vars');

		if (isset($vars[$source]))
		{
			$settings = $vars[$source];

			if (!is_null($return))
			{
				return $settings[$return];
			}

			return $settings;
		}

		return false;
	}

	// read only
	public static function readvar(&$source)
	{
		if (isset(self::$_vars[$source]))
		{
			return self::$_vars[$source];
		}

		return null;
	}

	public static function usePDO(&$source)
	{
		$var = self::readvar($source);

		$usepdo = true;

		if (isset($var['handler']) && strtolower($var['handler']) != 'pdo')
		{
			$usepdo = false;
		}

		// clean up
		$var = null;

		return $usepdo;
	}

	// for development.
	private static function iswin()
    {
        if (strtolower(PHP_SHLIB_SUFFIX) == 'dll')
        {
            return true;
        }

        return false;
	}
	
	// get settings vars
	private static function settingsVars(&$settings, $vars)
	{
		if (isset($vars['production']))
		{
			$prod = $vars['production'];

			// get type
			switch (gettype($prod))
			{
				// is array?
				case 'array':
					array_walk($prod, function($val, $key) use (&$settings){
						if (isset($settings[$key]))
						{
							// remove key
							unset($settings[$key]);
						}
					});

					unset($settings['production']);
					$settings = array_merge($settings, $prod);
				break;

				// is string?
				case 'string':
					$vars = self::readvar($prod['production']);

					if ($vars !== null && !isset($vars['production']))
					{
						$settings = $vars;
					}
					else
					{
						self::settingsVars($settings, $vars);
					}
				break;
			}

			// clean up
			$prod = null;
		}
	}

	// connect
	public static function getProtected($name)
	{
		static $connection;

		switch ($name)
		{
			case '_vars':
				if (is_null($connection))
				{
					$connection = new Connection();
				}

				return $connection();

			break;
		}
	}

	// get default
	public static function getDefault()
	{
		$all = self::getProtected('_vars');
		self::$_vars = $all;

		if (count(self::$connection) == 0)
		{
			if (isset($all['default']))
			{
				$default = $all['default'];
				$check = 4;

				if (isset($default['host']) && strlen($default['host']) > 5)
				{
					$check--;
				}

				if (isset($default['user']) && strlen($default['user']) > 2)
				{
					$check--;
				}

				if (isset($default['dbname']) && strlen($default['dbname']) > 2)
				{
					$check--;
				}

				if (isset($default['driver']) && strlen($default['host']) > 3)
				{
					$check--;
				}

				if ($check == 0)
				{
					self::$dbset = true;
					return 'default';
				}
			}
		}
		else
		{
			self::$dbset = true;
			return 'default';
		}
	}
}

// END class 
