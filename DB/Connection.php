<?php

namespace Amadiify;

use PDO;

/**
 * @package database connection handler
 * @author Moorexa <www.moorexa.com>
 * This is a free copy and can be used in any php project
 * You can send a feedback and contributions here @ https://github.com/moorexa/moorexa.db
 */

class Connection
{
	public function __invoke()
	{
		return [
			'default' => [
			   'dsn' 	   => '{driver}:host={host};dbname={dbname};charset={charset}',
			   'driver'    => 'mysql', // mysql, pgsql, sqlite
			   'host' 	   => '',
			   'user'      => '',
			   'password'  => '',
			   'dbname'    => '',
			   'charset'   => 'UTF8',
			   'port'      => '',
			   'handler'   => 'pdo', // pdo or mysqli
			   'attributes'=> true,
			   'production'=> [
				   'driver'  =>   'mysql',
				   'host'    =>   '',
				   'user'    =>   '',
				   'password'  =>   '',
				   'dbname'    =>   '',
			   ],
			   'options'   => [ PDO::ATTR_PERSISTENT => true ]
			],
			// ADD MORE HERE
	   ];
	}
}