<?php

namespace Amadiify;

// Open PDO for use
use PDO;
use Amadiify\Handler as handler;

/**
 * @package Moorexa Database engine
 * @version 0.0.1
 * @author  Ifeanyi Amadi
 */

class Client
{
   public  static $instance = null;
   private static $command = [];
   private        $method = "";
   private static $calls = 0;
   private        $allowed = [];
   private static $call = 0;
   private        $drum = [];
   private        $sub = 0;
   public  static $bindExternal = [];
   
   // pack all errors occured.
   private $errorpack = [];

   // get database driver from database handler
   private $driver;    

   // sql query 
   private $query = '';    

   // pdo bind values
   private $bind = [];   

   // operation failed
   private $failed = false;  
   
   // skip checking
   private $skipchecking = false;

   // last where statement
   private $lastWhere = '';   

   // database.table
   public $table = '';

   // packed argument
   private static $packed = [];   
   
   // argument sent.. 
   private static $argument = [];   

   // promise instance  
   private static $promise = null; 
   
   // query instance
   protected static $queryInstance = null; 
   
   // pdo instance 
   public $pdoInstance = null;   
   
   // active connections
   private static $activeConnections = [];

   // set active database
   public static $connectWith = null; // do not make constant
   
   // set active table
   public static $activeTable = null; // do not make constant

   // current use
   public $useConnection = null;

   // private $began 
   private static $began = 0;

   // pause execution
   public $pause = false;

   // allow html tags
   private $allowHTMLTags = false;
   
   // active connection
   public $instancedb = null;

   // insert keys
   private $insertKeys = '';

   // get query
   private $getSql = '';

   // get binds
   private $getBinds = [];

   // allow query called
   private $allowedQueryCalled = false;

   // transaction success
   public static $transactionCode = 0;

   // opened connection
   private static $openedConnection = [];

  
    // list of allowed chains
    private function getAllowed($val = [null], &$sql = "")
    {
       if (!isset($val[0]))
       {
           $val[0] = '';
       }

       $this->allowed = [
            'bind'      => "",
            'min'       => str_replace('SELECT', 'SELECT MIN('.implode(',', $val).')', $sql),
            'max'       => str_replace('SELECT', 'SELECT MAX('.implode(',', $val).')', $sql),
            'count'     => str_replace('SELECT', 'SELECT COUNT('.implode(',', $val).')', $sql),
            'avg'       => str_replace('SELECT', 'SELECT AVG('.implode(',', $val).')', $sql),
            'sum'       => str_replace('SELECT', 'SELECT SUM('.implode(',', $val).')', $sql),
            'distinct'  => str_replace('SELECT', 'SELECT DISTINCT', $sql),
            'rand'      => ' ORDER BY RAND() ',
            'where'     => "",
            'or'        => ' OR '.implode(' OR ', $val).' ',
            'as'        => ' AS '.$val[0].' ',
            'on'        => ' ON '.$val[0].' ',
            'innerjoin' => ' INNER JOIN '.$val[0].' ',
            'outerjoin' => ' FULL OUTER JOIN '.$val[0].' ',
            'leftjoin'  => ' LEFT JOIN '.$val[0].' ',
            'rightjoin' => ' RIGHT JOIN '.$val[0].' ',
            'from'      => ' FROM '.$val[0].' ',
            'in'        => ' IN ('.implode(',', $val).') ',
            'union'     => ' UNION ',
            'into'      => str_replace('FROM', 'INTO '.$val[0].' FROM', $sql),
            'unionall'  => ' UNION ALL ',
            'union'     => ' UNION ',
            'and'       => ' AND '.implode(' AND ', $val).' ',
            'group'     => ' GROUP BY '.implode(',', $val).' ',
            'having'    => ' HAVING '.$val[0].' ',
            'exists'    => ' EXISTS ('.$val[0].') ',
            'any'       => ' ANY ('.$val[0].') ',
            'all'       => ' ALL ('.$val[0].') ',
            'not'       => ' NOT '.implode(' NOT ', $val).' ',
            'notin'     => ' NOT IN ('.implode(',', $val).') ',
            'between'   => ' BETWEEN '.implode(' AND ', $val).' ',
            'limit'     => ' LIMIT '.implode(',', $val).' ',
            'orderby'   => ' ORDER BY '.implode(' ', $val).' ',
            'sql'       => " ". (isset($val[0]) ? $val[0] : ''),
            'get'       => '',
            'insert'    => '',
            'update'    => '',
            'delete'    => '',
            'like'      => function() use ($val, $sql){
                
                    $a =& $val;
                    $structure = $sql;
    
                    $line = $this->__stringBind($a[0], ' LIKE ', '');

                    $where = $line['line'];
                    $bind = $line['bind'];

                    if (preg_match('/({where})/', $structure))
                    {
                        $structure = str_replace('{where}', 'WHERE '.$where.' ', $structure);
                        $this->query = $structure;
                        $this->lastWhere = 'WHERE '.$where.' ';
                    }
                    else
                    {
                        $this->query = trim($this->query) .' '. $where;
                        $w = substr($this->query, strpos($this->query, 'WHERE'));
                        $w = substr($w, 0, strrpos($w, $where)) . $where;
                        $this->lastWhere = $w;
                    }

                    unset($a[0]);
                    $this->__addBind($a, $bind, null);

                    $newBind = [];
                    
                    // avoid clashes
                    $this->__avoidClashes($bind, $newBind);

                    $this->bind = array_merge($this->bind, $newBind);
                
            },
       ];

       return $this->allowed;
    }

    // queries by drivers  
    private function drivers($driver = null)
    {    
        // supported drivers.
        $queries = [
            // mysql queries..
            'mysql' => [
                'update' => 'UPDATE {table} SET {query} {where}',
                'insert' => 'INSERT INTO {table} ({column}) VALUES {query}',
                'delete' => 'DELETE FROM {table} {where}',
                'select' => 'SELECT {column} FROM {table} {where}'
            ],
            // pgsql queries..
            'pgsql' => [
                'update' => 'UPDATE ONLY {table} SET {query} {where}',
                'insert' => 'INSERT INTO {table} ({column}) VALUES {query}',
                'delete' => 'DELETE FROM {table} {where}',
                'select' => 'SELECT {column} FROM {table} {where}'
            ],
            // sqlite queries.. 
            'sqlite' => [
                'update' => 'UPDATE {table} SET {query} {where}',
                'insert' => 'INSERT INTO {table} ({column}) VALUES {query}',
                'delete' => 'DELETE FROM {table} {where}',
                'select' => 'SELECT {column} FROM {table} {where}'
            ]
		];
		
		if (!is_null($driver))
		{
			return isset($queries[$driver]) ? $queries[$driver] : null;
		}
		else
		{
			return isset($queries[$this->driver]) ? $queries[$this->driver] : null;
		}
    }

    // add static bind    
    private static function __bind(&$obj, &$a)
    {
            if (count($obj->bind) > 0)
            {
                $__bind = [];

                foreach ($obj->bind as $key => $val)
                {
                    if (empty($val))
                    {
                        $__bind[$key] = '';
                    }
                }

                if (count($__bind) > 0)
                {
                    $i = 0;

                    foreach ($__bind as $key => $val)
                    {
                        if (isset($a[$i]) && is_string($a[$i]))
                        {
                            $obj->bind[$key] = addslashes(strip_tags($a[$i]));
                        }
                        elseif (isset($a[$i]) && (is_object($a[$i]) || is_array($a[$i])))
                        {
                            $command = end(db::$command);

                            if (isset($obj->errorpack[$command]) && is_array($obj->errorpack[$command]))
                            {
                                $obj->errorpack[$command][] = 'Invalid Bind parameter. Scaler Type expected, Compound Type passed.';
                            }
                            else
                            {
                                $obj->errorpack[$command] = [];
                                $obj->errorpack[$command][] = 'Invalid Bind parameter. Scaler Type expected, Compound Type passed.';
                            }
                        }   
                        else
                        {
                            $obj->bind[$key] = isset($a[$i]) ? $a[$i] : '';
                        }
                        
                        $i++;
                    }
                    
                }
            }
    }

    // array as argument
    private function __arrayBind($data, $seperator = ',')
    {
        $set = '';
        $bind = [];

        foreach ($data as $key => $val)
        {
            if (!is_array($val) && !is_object($val))
            {
                $set .= $key.' = :'.$key.' '.$seperator.' ';
                $val = html_entity_decode($val);
                $val = stripslashes($val);
                $bind[$key] = addslashes($val);
            }
        }

        $sep = strrpos($set, $seperator);
        $set = substr($set, 0, $sep);

        return ['set' => $set, 'bind' => $bind];
    }

    // array bind for insert
    private function __arrayInsertBody($array, $structure)
    {
        static $x = 0;
        // values
        $values = [];
        $binds = [];

        foreach ($array as $i => $data)
        {
            if (is_object($data))
            {
                $data = Client::toArray($data);
            }

            if (is_array($data))
            {   
                $xx = 0;
                $value = [];

                foreach ($data as $key => $val)
                {
                    $hkey = trim($structure[$xx]);
                    $value[] = ':'.$hkey.$x;
                    $d = isset($data[$hkey]) ? $data[$hkey] : (isset($data[$xx]) ? $data[$xx] : null);
                    $d = html_entity_decode($d);
                    $d = stripslashes($d);
                    $binds[$hkey.$x] = addslashes($d);
                    $xx++;
                }

                $values[] = '('.implode(',', $value).')';
                $x++;
            }
        }

        $x = 0;

        return ['values' => implode(',', $values), 'bind' => $binds];
    } 
    
    private function __arrayInsertHeader($array)
    {
        $header = [];

        if (isset($array[0]) && is_array($array[0]))
        {
            foreach ($array[0] as $key => $val)
            {
                if (is_string($key))
                {
                    $header[] = $key;
                }
                else
                {
                    $header[] = $val;
                }
            } 
        }

        return ['header' => implode(',', $header), 'structure' => $header];
    } 

    // string insert bind
    private function __stringInsertBind($data)
    {
        // get all strings
        preg_match_all('/[\'|"]([\s\S])[\'|"|\S]{1,}[\'|"]/',$data, $match);
                                    
        $strings = [];
        if (count($match[0]) > 0)
        {
            foreach($match[0] as $i => $string)
            {
                $strings[] = $string;
                $data = str_replace($string, ':string'.$i, $data);
            }
        }

        // now split by comma
        $split = explode(',',$data);

        // replace strings now with original values.
        if (count($strings) > 0)
        {
            foreach($strings as $i => $string)
            {
                $split[$i] = str_replace(':string'.$i, $string, $split[$i]);
            }
        }

        $bind = [];
        $header = [];
        $values = [];

        // check if we don't have lvalue and rvalue
        static $xc = 0;

        foreach($split as $i => $line)
        {
            $line = trim($line);
            if (preg_match('/[=]/', $line))
            {
                // get rvalue
                $eq = strpos($line, '=');
                $rval = trim(substr($line, $eq+1));

                // get lvalue
                $lval = trim(substr($line, 0, $eq));

                if (!in_array($lval, $header))
                {
                    $header[] = $lval;
                }
                
                if ($rval == '?')
                {
                    $values[] = ':'.$lval.$xc;

                    $rval = ':'.$lval;
                    $bind[$lval.$xc] = '';
                }
                elseif ($rval[0] == ':')
                {
                    $values[] = $rval.$xc;
                    $bind[$rval.$xc] = '';
                }
                else 
                {
                    // has values
                    $start = $rval[0];
                    if (preg_match("/[a-zA-Z0-9|'|\"]/", $start))
                    {
                        if ($start == '"')
                        {
                            $bf = $rval;
                            $end = strrpos($rval, '"');
                            $rval = substr($rval, 0, $end);
                            $line = str_replace($bf, $rval, $line);
                            $split[$i] = $line;
                        } 
                        elseif ($start == "'")
                        {
                            $bf = $rval;
                            $end = strrpos($rval, "'");
                            $rval = substr($rval, 0, $end);
                            $line = str_replace($bf, $rval, $line);
                            $split[$i] = $line;
                        }
                        elseif (preg_match('/^[0-9]/', $start))
                        {
                            $bf = $rval;
                            $end = strpos($rval,' ');
                            if ($end !== false)
                            {
                                $rval = substr($rval, 0, $end);
                                $line = str_replace($bf, $rval, $line);
                                $split[$i] = $line;
                            }
                        }
                    }

                    $rval = preg_replace('/^[\'|"]/','',$rval);
                    $rval = preg_replace('/[\'|"]$/','',$rval);
                    $rval = html_entity_decode($rval);
                    $rval = stripslashes($rval);
                    $rval = addslashes($rval);

                    $values[] = ':'.$lval.$xc;
                    $bind[$lval.$xc] = $rval;
                }
            }

            $xc++;
        }

        $xc = 0;

        return ['values' => $values, 'bind' => $bind, 'header' => $header];
    }    

    // string as argument
    private function __stringBind($data, $l = null, $r = null)
    {
        // get all strings
        preg_match_all('/[\'|"]([\s\S])[\'|"|\S]{0,}[\'|"]/',$data, $match);
                                    
        $strings = [];
        if (count($match[0]) > 0)
        {
            foreach($match[0] as $i => $string)
            {
                $strings[] = $string;
                $data = str_replace($string, ':string'.$i, $data);
            }
        }

        // now split by comma, or, and
        $split = preg_split('/(\s{1,}or\s{1,}|\s{1,}OR\s{1,}|\s{1,}and\s{1,}|[,]|\s{1,}AND\s{1,})/', $data);

        // watch out for other valid sql keywords.
        foreach($split as $i => $ln)
        {
            $ln = trim($ln);
            if (!preg_match('/[=]/',$ln) || preg_match('/^[0-9]/',$ln))
            {
                if (isset($split[$i-1]))
                {
                    if (stripos($split[$i-1], 'limit'))
                    {
                        $split[$i-1] .= ','.$ln;
                        unset($split[$i]);
                        sort($split);
                    }
                }
            }
        }

        // replace strings now with original values.
        if (count($strings) > 0)
        {
            foreach($strings as $i => $string)
            {
                $split[$i] = str_replace(':string'.$i, $string, $split[$i]);
                $data = str_replace(':string'.$i, $string,  $data);
            }
        }

        $bind = [];
        $newSplit = [];

        // check if we don't have lvalue and rvalue
        static $xy = 0;

        foreach($split as $i => $line)
        {
            $line = trim($line);
            if (!preg_match('/(=|!=|>|<|>=|<=)/', $line))
            {
                $query = implode(',', $newSplit);
                $__key = $line;

                if (preg_match("/[:]($line)/", $this->query) || preg_match("/[:]($line)/", $query))
                {
                    $line .= $xy;  
                    $bind[$line] = '';

                    $xy++;
                    
                }
                else
                {
                    $bind[$__key] = '';
                }

                $l = is_null($l) ? ' = ' : $l;
                $r = is_null($r) ? '' : $r;

                $new = $__key . $l . ':'.$line. $r;
                $line = $new;
            }
            else
            {
                // get rvalue
                $eq = strpos($line, '=');
                $sep = '=';

                if ($eq===false)
                {
                    if (preg_match('/(!=)/',$line))
                    {
                        $eq = strpos($line, '!=');
                        $sep = '!=';
                    }
                }

                if ($eq===false)
                {
                    if (preg_match('/(>)/',$line))
                    {
                        $eq = strpos($line, '>');
                        $sep = '>';
                    }
                }

                if ($eq===false)
                {
                    if (preg_match('/(<)/',$line))
                    {
                        $eq = strpos($line, '<');
                        $sep = '<';
                    }
                }

                if ($eq===false)
                {
                    if (preg_match('/(>=)/',$line))
                    {
                        $eq = strpos($line, '>=');
                        $sep = '>=';
                    }
                }

                if ($eq===false)
                {
                    if (preg_match('/(<=)/',$line))
                    {
                        $eq = strpos($line, '<=');
                        $sep = '<=';
                    }
                }

                $rval = trim(substr($line, $eq+intval(strlen($sep))));

                // get lvalue
                $lval = trim(substr($line, 0, $eq));
                $lval = trim(preg_replace('/[!|=|<|>]$/','',$lval));

                
                if ($rval == '?')
                {
                    static $xx = 0;

                    $query = implode(',', $newSplit);

                    if (preg_match("/[:]($lval)/", $this->query) || preg_match("/[:]($lval)/", $query))
                    {
                        $lval .= $xx;  
                        $xx++;
                    }

                    $rval = ':'.$lval;
                    $line = str_replace('?', $rval, $line);
                    $bind[$lval] = '';
                }
                elseif ($rval[0] == ':')
                {
                    static $xx = 0;

                    $query = implode(',', $split);

                    if (preg_match("/($rval)/", $this->query) || preg_match("/[:]($lval)/", $query))
                    {
                        $bind[substr($rval,1).$xx] = '';
                        $xx++;
                    }   
                    else
                    {
                        $bind[substr($rval,1)] = '';
                    }
                    
                }
                else 
                {
                    static $xx = 0;

                    // has values
                    $start = $rval[0];
                    if (preg_match("/[a-zA-Z0-9|'|\"]/", $start))
                    {
                        if ($start == '"')
                        {
                            $bf = $rval;
                            $end = strrpos($rval, '"');
                            $rval = substr($rval, 0, $end+1);
                            $line = str_replace($bf, $rval, $line);
                            $split[$i] = $line;
                        } 
                        elseif ($start == "'")
                        {
                            $bf = $rval;
                            $end = strrpos($rval, "'");
                            $rval = substr($rval, 0, $end+1);
                            $line = str_replace($bf, $rval, $line);
                            $split[$i] = $line;
                        }
                        elseif (preg_match('/^[0-9]/', $start))
                        {
                            $bf = $rval;
                            $end = strpos($rval,' ');
                            if ($end !== false)
                            {
                                $rval = substr($rval, 0, $end);
                                $line = str_replace($bf, $rval, $line);
                                $split[$i] = $line;
                            }
                        }
                    }

                    $rval = preg_replace('/^[\'|"]/','',$rval);
                    $rval = preg_replace('/[\'|"]$/','',$rval);
                    $rval = html_entity_decode($rval);
                    $rval = addslashes(strip_tags($rval));


                    $query = implode(', ', $newSplit);

                    if (preg_match("/[:]($lval)/", $this->query) || preg_match("/[:]($lval)/", $query))
                    {
                        $line = $lval .' '.$sep.' :'.$lval.$xx;
                        $bind[$lval.$xx] = $rval;

                        $xx++;
                    }
                    else
                    {
                        $line = $lval .' '.$sep.' :'.$lval;
                        $bind[$lval] = $rval;
                    }
                }
            }

            $newSplit[] = $line;
        }

        $xy = 0;

        if (is_string($data))
        {
            $originalData = [];

            foreach ($split as $i => $line)
            {
                $q = preg_quote($line);
                $beg = strpos($data, $line);
                $sized = strlen($line);

                $with = "{".$beg.$sized.substr(md5($line),0,mt_rand(10,40))."}";
                $originalData[$with] = $newSplit[$i];

                $data = substr_replace($data, $with, $beg, $sized);
                unset($split[$i]);
            }

            foreach($originalData as $map => $val)
            {
                $data = str_replace($map, $val, $data);
            }
        }
        else
        {
            $this->failed = true;
            $this->errorpack[$this->method][] = 'Empty string passed';
        }

        return ['line' => $data, 'bind' => $bind];
    }

    // add binds silently
    private function __addBind(&$a, &$bind)
    {
        if (count($a) > 0)
        {
            $i = 0;
            foreach ($bind as $x => $b)
            {
                if (empty($b) && isset($a[$i]))
                {
                    if (is_string($a[$i]))
                    {
                        $a[$i] = html_entity_decode($a[$i]);
                        $a[$i] = stripslashes($a[$i]);
                        $bind[$x] = addslashes($a[$i]);
                    }
                    else
                    {
                        $bind[$x] = $a[$i];
                    }
                    unset($a[$i]);
                }
                $i++;
            }
        }
    }

    // avoid clashes
    private function __avoidClashes(&$bind, &$newBind)
    {
        $currentBind = $this->bind;

        static $i = 0;
        $added = false;
        $ret = 0;

        foreach($bind as $key => $val)
        {
            // avoid name clashes..
            if (isset($currentBind[$key]))
            {
                if (empty($currentBind[$key]))
                {
                    if (is_string($val))
                    {
                        $val = html_entity_decode($val);
                        $val = stripslashes($val);
                        $newBind[$key] = addslashes($val);
                    }
                    else
                    {
                        $newBind[$key] = $val;
                    }
                }
                else
                {
                    
                    $ret = $i;
                    if (is_string($val))
                    {
                        $val = html_entity_decode($val);
                        $val = stripslashes($val);
                        $newBind[$key.$i] = addslashes($val);
                    }
                    else
                    {
                        $newBind[$key.$i] = $val;
                    }
                    $i++;
                    $added = true;
                }
            }
            else
            {
                if (is_string($val))
                {
                    $val = html_entity_decode($val);
                    $val = stripslashes($val);
                    $newBind[$key] = addslashes($val);
                }
                else
                {
                    $newBind[$key] = $val;
                }
            }
        }

        if ($added)
        {
            return $ret;
        }
        else
        {
            return '';
        }

    }

    // set active table
    public function setActiveTable($table)
    {
       self::$activeTable = $table;
       $this->table = $table;
    }

    // set connect with
    public function setConnectWith($with)
    {
       self::$connectWith = $with;
    }

    public function _apply($dataName = null)
    {
       if (!is_null($dataName) && !empty($dataName))
	   {
            if (isset(self::$openedConnection[$dataName]))
            {
                $con = self::$openedConnection[$dataName];
                $con->table = !is_null($this->table) ? $this->table : $con->table;
                $con->bind = [];
                $con->query = '';
                $con->allowed = $this->getAllowed();

                return $con;
            }   
			// switch connection
			else
			{
                $driver = Handler::connectionConfig($dataName, 'driver');
                // get allowed
                $this->getAllowed();
                
                if (Handler::connectionConfig($dataName) !== false)
                {
                    Handler::$dbset = true;
                }

                if (Handler::$dbset === true)
                {
                    if ($this->drivers($driver) !== null)
                    {
                        $this->useConnection = $dataName;

                        // save driver
                        $this->driver = $driver;
                        
                        $this->instancedb = $dataName;

                        // extablish connection
                        $con = Handler::active($dataName);

                        // save instance.
                        $this->pdoInstance = $con;

                        // push connection 
                        self::$openedConnection[$dataName] = $this;
                    }
                    else
                    {
                        throw new \Exception('Driver you used isn\'t supported on this server. Please see documentation');
                    }
                }
			}
	   }

	   return $this;
    }

    // get table info
    public function _getTableInfo($instance = null, $type = null, $table = null)
    {
       $ins = $this;

       if (is_object($instance))
       {
           $ins = $instance;
       }

       $server = !is_null($ins->driver) ? $ins->driver : $this->driver;
       $tableName = !is_null($ins->table) ? $ins->table : $this->table;

       if (is_object($instance) && is_string($type))
       {
           $server = $type;
       }

       if (is_string($instance))
       {
           $server = $instance;
       }

       if (!is_null($table))
       {
           $tableName = $table;
       }

       if (is_string($instance) && is_string($type))
       {
           $tableName = $type;
       }

       $query = [
           'sqlite' => "SELECT sql FROM sqlite_master WHERE name = '{$tableName}'",
           'pgsql' => "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$tableName}'",
           'mysql' => "SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_NAME = '{$tableName}'",
       ];

       $structure = [];

       if (isset($query[$server]))
       {    
            
            $run = $ins->sql($query[$server]);

            if ($run->rows > 0)
            {
                switch($server)
                {
                    case 'mysql':
                    case 'pgsql':
                            $run->array(function($row) use (&$structure)
                            {
                                if (isset($row['COLUMN_TYPE']))
                                {
                                    // get size
                                    $type = $row['COLUMN_TYPE'];
                                    $size = preg_replace("/([^\d]*)/",'',$type);
                                    $name = preg_replace("/([^a-zA-Z_]*)/",'', $type);
                                    $structure[$row['COLUMN_NAME']] = ['size' => $size, 'type' => $name];
                                }
                            });
                    break;

                    case 'sqlite':
                    break;
                }
            }
            
       }

       return $structure;
    }

    // get request
    private function ___get($a, $structure, $table)
    {
       // set method
       $this->method = 'get';

        if (count($a) > 0)
        {
            // data passed
            $data = $a[0];

            // get rulesdata for object passed.
            if (is_object($data))
            {
                if (method_exists($data, 'rulesHasData'))
                {
                    $data = $data->rulesHasData();
                    // array shift
                    $a[0] = $data;
                }
            }

            // is object?
            if (is_object($data))
            {
                // convert to array
                $data = Client::toArray($data);
            }

            // json data?
            if (is_string($data) && trim($data[0]) == '{' )
            {
                // conver to an object
                $data = Client::toArray(json_decode($data));
            }

            if (is_array($data))
            {
                $cond = 'AND';

                if (isset($a[1]) && $a[1] == 'OR')
                {
                    $cond = 'OR';

                    unset($a[1]);
                }

                $arrayBind = $this->__arrayBind($data, $cond);
                
                $structure = str_replace('{column}', '*', $structure);
                $structure = str_replace('{where}', 'WHERE '.$arrayBind['set'].' ', $structure);

                $this->query = $structure;
                $this->bind = $arrayBind['bind'];

                array_shift($a);
            }
            else
            {

                if (preg_match('/(=|!=|>|<|>=|<=)/', $data))
                {
                    $dl = $this->__stringBind($data);
                    $bind = $dl['bind'];

                    array_shift($a);

                    $this->__addBind($a, $bind);

                    // sort($a);
                    $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);
                    $structure = str_replace('{column}', '*', $structure);

                    $this->query = $structure;
                    $this->bind = $bind;
                }
                else
                {
                    $continue = false;

                    if (preg_match('/[,]/', $data) || (isset($a[1]) && preg_match('/[=]/', $data)) || !isset($a[1]))
                    {
                        $continue = true;
                    }
                    else
                    {
                        if (!isset($a[1]))
                        {
                            $continue = true;
                        }
                        else
                        {
                            $dl = $this->__stringBind($data);
                            $bind = $dl['bind'];
            
                            array_shift($a);
                            
                            $this->__addBind($a, $bind);
            
                            $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);
                            $structure = str_replace('{column}', '*', $structure);

                            $this->query = $structure;
                            $this->bind = $bind;
                        }
                    }


                    if ($continue)
                    {
                        $structure = str_replace('{column}', $data, $structure);
                        array_shift($a);

                        if (count($a) > 0)
                        {
                            $data = $a[0];

                            // is object?
                            if (is_object($data))
                            {
                                // convert to array
                                $data = Client::toArray($data);
                            }
                
                            // json data?
                            if (is_string($data) && trim($data[0]) == '{' )
                            {
                                // conver to an object
                                $data = Client::toArray(json_decode($data));
                            }

                            if (is_array($data))
                            {
                                $cond = 'AND';

                                if (isset($a[1]) && $a[1] == 'OR')
                                {
                                    $cond = 'OR';

                                    unset($a[1]);
                                }

                                $arrayBind = $this->__arrayBind($data, $cond);
                    
                                $structure = str_replace('{column}', '*', $structure);
                                $structure = str_replace('{where}', 'WHERE '.$arrayBind['set'].' ', $structure);
                
                                $this->query = $structure;
                                $this->bind = $arrayBind['bind'];
                            }
                            else
                            {
                                $dl = $this->__stringBind($data);
                                $bind = $dl['bind'];
                
                                array_shift($a);
                                
                                $this->__addBind($a, $bind);
                
                                $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);
                                $structure = str_replace('{column}', '*', $structure);

                                $this->query = $structure;
                                $this->bind = $bind;
                            }
                        }
                    }

                    $this->query = $structure;
                }
            }
        }
        else
        {
            $structure = str_replace('{column}', '*', $structure);

            $this->query = $structure;
        }

        return $this;
    }

    // insert request
    private function ___insert($a, $structure, $table)
    {
        $instance = &$this;

        // set method
        $instance->method = 'insert';

        if (count($a) > 0)
        {   
            // check if args 2 is string and possibly an object
            if (isset($a[1]) && is_string($a[1]))
            {
                $object = json_decode($a[1]);
                $copy = $a;
                // build new args
                $newArgs = [];

                if (is_string($a[0]) && is_object($object))
                {
                    $columns = explode(',',$a[0]);

                    foreach ($object as $key => $val)
                    {
                        $row = [];
                
                        $row[trim($columns[0])] = $key;
                        $row[trim($columns[1])] = $val;

                        $newArgs[] = $row;
                    }

                    if (count($newArgs) > 0)
                    {
                        $a = $newArgs;
                    }
                }
            }

            // data passed
            $data = $a[0];

            // get rulesdata for object passed.
            if (is_object($data))
            {
                if (method_exists($data, 'rulesHasData'))
                {
                    $data = $data->rulesHasData();
                    // array shift
                    $a[0] = $data;
                }
            }

            // is object?
            if (is_object($data))
            {
                // convert to array
                $data = Client::toArray($data);
            }

            // json data?
            if (is_string($data) && trim($data[0]) == '{' )
            {
                // conver to an object
                $data = Client::toArray(json_decode($data));
                $a[0] = $data;
            }

            if (is_array($data))
            {
                $getHeader = $instance->__arrayInsertHeader($a);
               
                $header = $getHeader['header'];
                $struct = $getHeader['structure'];

                $instance->insertKeys = $header;

                $structure = str_replace('{column}', $header, $structure);
                
                $data = $instance->__arrayInsertBody($a, $struct);
                $bind = $data['bind'];
                $values = $data['values'];
                
                $structure = str_replace('{query}', $values, $structure);

                $instance->query = $structure;
                $instance->bind = $bind;
            }
            else
            {
                // string
                // no equal ?
                if (strpos($data,'=') === false)
                {
                    $struct = explode(',', $data);
                    $structure = str_replace('{column}', $data, $structure);

                    $instance->insertKeys = $data;

                    array_shift($a);

                    // data passed
                    if (isset($a[0]))
                    {
                        $data = $a[0];
            
                        // is object?
                        if (is_object($data))
                        {
                            // convert to array
                            $data = Client::toArray($data);
                        }
            
                        // json data?
                        if (is_string($data) && trim($data[0]) == '{' )
                        {
                            // conver to an object
                            $data = Client::toArray(json_decode($data));
                        }

                        $continue = true;
                        

                        if (is_array($data))
                        {
                            if (count($a) != 1)
                            {
                                $continue = false;
                                
                                $data = $instance->__arrayInsertBody($a, $struct);
                                $bind = $data['bind'];
                                $values = $data['values'];
                                
                                $structure = str_replace('{query}', $values, $structure);
                                $instance->query = $structure;
                                $instance->bind = $bind;
                            }
                            else
                            {
                                $a = $data;
                            }
                        }
                        else
                        {
                            if (count($a) > 0)
                            {
                                $continue = true;
                            }
                        }
                        
                        
                        if ($continue)
                        {
                            static $x = 0;

                            $values = [];
                            $binds = [];
                            $len = count($struct)-1;
                            $y = 0;

                            if (count($struct) > count($a))
                            {   
                                foreach ($struct as $i => $h)
                                {
                                    if (!isset($a[$i]))
                                    {
                                        $a[$i] = null;
                                    }
                                }
                            }

                            $len--;
                            $value = [];
                            
                            foreach ($a as $i => $val)
                            {
                                $struct[$y] = trim($struct[$y]);
                                $value[$y] = ':'.$struct[$y].$x;
                                $binds[$struct[$y].$x] = addslashes(htmlentities($val, ENT_QUOTES, 'UTF-8'));
                                
                                if ($y == count($struct)-1 || $y == count($a)-1)
                                {
                                    $y = 0;
                                    $values[] = '('.implode(',', $value).')';
                                }
                                else
                                {
                                    $y++;
                                }

                                $x++;
                            }
                            
                            $x = 0;
                            
                            $structure = str_replace('{query}',implode(',', $values),$structure);
                            $instance->query = $structure;
                            $instance->bind = $binds;
                        }

                    }
                    else
                    {
                        static $x = 0;

                        $values = [];
                        $binds = [];
                        $len = count($struct)-1;
                        $y = 0;

                        if (count($struct) > count($a))
                        {   
                            foreach ($struct as $i => $h)
                            {
                                if (!isset($a[$i]))
                                {
                                    $a[$i] = null;
                                }
                            }
                        }

                        $len--;
                        $value = [];
                        
                        foreach ($a as $i => $val)
                        {
                            $struct[$y] = trim($struct[$y]);
                            $value[$y] = ':'.$struct[$y].$x;
                            $binds[$struct[$y].$x] = addslashes(htmlentities($val, ENT_QUOTES, 'UTF-8'));
                            
                            if ($y == count($struct)-1 || $y == count($a)-1)
                            {
                                $y = 0;
                                $values[] = '('.implode(',', $value).')';
                            }
                            else
                            {
                                $y++;
                            }

                            $x++;
                        }


                        $x = 0;
                        
                        $structure = str_replace('{query}',implode(',', $values),$structure);
                        $instance->query = $structure;
                        $instance->bind = $binds;
                    }
                }
                // has equal
                else
                {
                    $data = $instance->__stringInsertBind($data);
                    $structure = str_replace('{column}', implode(',', $data['header']), $structure);
                    $structure = str_replace('{query}', '('.implode(',', $data['values']).')', $structure);

                    $bind = $data['bind'];
                    $instance->insertKeys = $data['header'];
                
                    array_shift($a);
                    
                    $instance->__addBind($a, $bind);

                    $instance->bind = $bind;
                    $instance->query = $structure;
                }
            }
        }
        else
        {
            $instance->errorpack['insert'][] = 'No data to insert. You can pass compound data types.';
        }  
        
        return $instance;
    }

    // delete request
    private function ___delete($a, $structure, $table)
    {
        $instance = &$this;

        // set method
        $instance->method = 'delete';

        if (count($a) > 0)
        {
            // data passed
            $data = $a[0];

            // get rulesdata for object passed.
            if (is_object($data))
            {
                if (method_exists($data, 'rulesHasData'))
                {
                    $data = $data->rulesHasData();
                    // array shift
                    $a[0] = $data;
                }
            }

            // is object?
            if (is_object($data))
            {
                // convert to array
                $data = Client::toArray($data);
            }

            // json data?
            if (is_string($data) && trim($data[0]) == '{' )
            {
                // conver to an object
                $data = Client::toArray(json_decode($data));
            }

            if (is_array($data))
            {
                $arrayBind = $instance->__arrayBind($data, 'OR');
                
                $structure = str_replace('{where}', 'WHERE '.$arrayBind['set'].' ', $structure);

                $instance->query = $structure;
                $instance->bind = $arrayBind['bind'];

                array_shift($a);
            }
            else
            {
                if (preg_match('/(=|!=|>|<|>=|<=)/', $data))
                {
                    $dl = $instance->__stringBind($data);
                    $bind = $dl['bind'];

                    array_shift($a);

                    $instance->__addBind($a, $bind);

                    $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);

                    $instance->query = $structure;
                    $instance->bind = $bind;
                }
                else
                {
                    $continue = false;

                    if (preg_match('/[,]/', $data) || (isset($a[1]) && preg_match('/[=]/', $data)) || !isset($a[1]))
                    {
                        $continue = true;
                    }
                    else
                    {
                        if (!isset($a[1]))
                        {
                            $continue = true;
                        }
                        else
                        {
                            $dl = $instance->__stringBind($data);
                            $bind = $dl['bind'];
            
                            array_shift($a);
                            
                            $instance->__addBind($a, $bind);
            
                            $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);

                            $instance->query = $structure;
                            $instance->bind = $bind;
                        }
                    }

                    if ($continue)
                    {
                        array_shift($a);

                        if (count($a) > 0)
                        {
                            $data = $a[0];

                            // is object?
                            if (is_object($data))
                            {
                                // convert to array
                                $data = Client::toArray($data);
                            }
                
                            // json data?
                            if (is_string($data) && trim($data[0]) == '{' )
                            {
                                // conver to an object
                                $data = Client::toArray(json_decode($data));
                            }

                            if (is_array($data))
                            {
                                $arrayBind = $instance->__arrayBind($data, 'OR');
                    
                                $structure = str_replace('{where}', 'WHERE '.$arrayBind['set'].' ', $structure);
                
                                $instance->query = $structure;
                                $instance->bind = $arrayBind['bind'];
                            }
                            else
                            {
                                $dl = $instance->__stringBind($data);
                                $bind = $dl['bind'];
                                
                                array_shift($a);
                                
                                $instance->__addBind($a, $bind);
                
                                $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);

                                $instance->query = $structure;
                                $instance->bind = $bind;
                            }
                        }
                        else
                        {
                            $dl = $instance->__stringBind($data);
                            $bind = $dl['bind'];
            
                            array_shift($a);
                            
                            $instance->__addBind($a, $bind);
            
                            $structure = str_replace('{where}', 'WHERE '.$dl['line'].' ', $structure);

                            $instance->query = $structure;
                            $instance->bind = $bind;
                        }
                    }
                }
            }

            $instance->query = $structure;
        }
        else
        {
            $instance->query = $structure;
        }

        return $instance;
    }

    // update request
    private function ___update($a, $structure, $table)
    {
        // set method
        $this->method = 'update';

        if (count($a) > 0)
        {   
            // check if args 2 is string and possibly an object
            if (isset($a[1]) && is_string($a[1]))
            {
                $object = json_decode($a[1]);
                $copy = $a;

                // build new args
                $newArgs = [];

                if (is_string($a[0]))
                {
                    $obj = json_decode($a[0]);

                    if (is_null($obj))
                    {
                        $columns = explode(',', $a[0]);

                        foreach ($object as $key => $val)
                        {
                            $row = [];
                    
                            $row[trim($columns[0])] = $key;
                            $row[trim($columns[1])] = $val;

                            $newArgs[] = $row;
                        }

                        if (count($newArgs) > 0)
                        {
                            unset($a[0], $a[1]);
                            $a = array_merge($newArgs, $a);
                        }
                    }
                }
            }

            // data passed
            $data = $a[0];

            // get rulesdata for object passed.
            if (is_object($data))
            {
                if (method_exists($data, 'rulesHasData'))
                {
                    $data = $data->rulesHasData();
                    // array shift
                    $a[0] = $data;
                }
            }

            // is object?
            if (is_object($data))
            {
                // convert to array
                $data = Client::toArray($data);
            }

            // json data?
            if (is_string($data) && trim($data[0]) == '{' )
            {
                // conver to an object
                $data = Client::toArray(json_decode($data));
                $a[0] = $data;
            }

            // data passed is an array
            if (is_array($data))
            {
                $arrayBind = $this->__arrayBind($data);

                $structure = str_replace('{query}', $arrayBind['set'], $structure); 

                $this->query = $structure;
                $this->bind = $arrayBind['bind'];

                array_shift($a);
            }
            else
            {
                $dl = $this->__stringBind($data);
                $bind = $dl['bind'];

                array_shift($a);
                
                $this->__addBind($a, $bind);

                $structure = str_replace('{query}', $dl['line'], $structure);

                $this->query = $structure;
                $this->bind = $bind;
            }

            // where added ?
            if (count($a) > 0)
            {
                if (is_array($a[0]) || is_object($a[0]) || (is_string($a[0]) && $a[0] == '{'))
                {
                    if (is_object($a[0]))
                    {
                        $a[0] = Client::toArray($a[0]);
                    }

                    if (is_string($a[0]) && $a[0] == '{')
                    {
                        $a[0] = Client::toArray(json_decode($a[0]));
                    }

                    if (is_array($a[0]))
                    {
                        $whereBind = $this->__arrayBind($a[0], 'AND');
                        $where = $whereBind['set'];
                        $bind = $whereBind['bind'];

                        $structure = str_replace('{where}', 'WHERE '.$where.' ', $structure);

                        $this->query = $structure;
                        $this->lastWhere = 'WHERE '.$where.' ';

                        $newBind = [];
                        
                        // avoid clashes
                        $this->__avoidClashes($bind, $newBind);

                        $this->bind = array_merge($this->bind, $newBind);
                    }
                    else
                    {
                        $this->errorpack['update'][] = 'Where statement not valid. Must be a string, object, array or json string';
                    }
                }
                else
                {
                    if (is_string($a[0]))
                    {
                        $line = $this->__stringBind($a[0]);
                        $where = $line['line'];
                        $bind = $line['bind'];
                        
                        $structure = str_replace('{where}', 'WHERE '.$where.' ', $structure);
                        $this->query = $structure;
                        $this->lastWhere = 'WHERE '.$where.' ';
                        
                        array_shift($a);

                        $this->__addBind($a, $bind);

                        $newBind = [];
                        
                        // avoid clashes
                        $this->__avoidClashes($bind, $newBind);

                        
                        $this->bind = array_merge($this->bind, $newBind);
                    }
                }
            }

            
        }
        else
        {
            // error, no data passed
            $this->errorpack['update'][] = 'No data passed.';
        }

        return $this;
    }

    // run binding
    private function runBinding()
    {
        $a = func_get_args();

        if (count($this->bind) > 0)
        {
            $__bind = [];

            foreach ($this->bind as $key => $val)
            {
                if (empty($val))
                {
                    $__bind[$key] = '';
                }
            }

            if (count($__bind) > 0)
            {
                $i = 0;
                $bind = [];

                if (is_array($a[0]))
                {
                    foreach ($a[0] as $i => $val1)
                    {
                        if (is_string($i) && isset($__bind[$i]))
                        {
                            $bind[$i] = $val1;
                        }
                        else
                        {
                            $keys = array_keys($__bind);

                            if (isset($keys[$i]))
                            {
                                $key = $keys[$i];
                                $bind[$key] = $val1;
                            }
                        }
                    }
                }
                else
                {
                    foreach ($__bind as $key => $val)
                    {
                        if (isset($a[$i]))
                        {
                            if (is_string($a[$i]))
                            {
                                $bind[$key] = addslashes(strip_tags($a[$i]));
                            }
                            elseif (is_object($a[$i]) || is_array($a[$i]))
                            {
                                $command = $this->method;

                                if (is_array($this->errorpack[$command]))
                                {
                                    $this->errorpack[$command][] = 'Invalid Bind parameter. Scaler Type expected, Compound Type passed.';
                                }
                                else
                                {
                                    $this->errorpack[$command] = [];
                                    $this->errorpack[$command][] = 'Invalid Bind parameter. Scaler Type expected, Compound Type passed.';
                                }
                            }   
                            else
                            {
                                if (isset($a[$i]))
                                {
                                    $bind[$key] = $a[$i];
                                }
                            }
                        }
                        else
                        {
                            $bind[$key] = isset($a[$i-1]) ? $a[$i-1] : '';
                        }
                        
                        $i++;
                    }
                }
                
                $newBind = [];
                $this->__avoidClashes($bind, $newBind);

                $this->bind = array_merge($this->bind, $newBind);
            }
        }

        return $this;
    }

    // run where
    private function runWhere()
    {
        $a = func_get_args();

        if (count($a) > 0)
        {
            $structure = $this->query;

            if (is_array($a[0]) || is_object($a[0]) || (is_string($a[0]) && $a[0] == '{'))
            {
                if (is_object($a[0]))
                {
                    $a[0] = Client::toArray($a[0]);
                }

                if (is_string($a[0]) && $a[0] == '{')
                {
                    $a[0] = Client::toArray(json_decode($a[0]));
                }

                if (is_array($a[0]))
                {
                    $sep = isset($a[1]) ? $a[1] : 'and';

                    $whereBind = $this->__arrayBind($a[0], $sep);
                    $where = $whereBind['set'];
                    $bind = $whereBind['bind'];

                    if (preg_match('/({where})/', $structure))
                    {
                        $structure = str_replace('{where}', 'WHERE '.$where.' ', $structure);
                        $this->query = $structure;
                        $this->lastWhere = 'WHERE '.$where.' ';
                    }
                    else
                    {
                        $this->query = trim($this->query).' '. $where;

                        $w = substr($this->query, strpos($this->query, 'WHERE'));
                        $w = substr($w, 0, strrpos($w, $where)) . $where;
                        $this->lastWhere = $w;
                    }

                    $newBind = [];
                    
                    // avoid clashes
                    $this->__avoidClashes($bind, $newBind);

                    $this->bind = array_merge($this->bind, $newBind);
                }
                else
                {
                    $this->errorpack[$this->method][] = 'Where statement not valid. Must be a string, object, array or json string';
                }
            }
            else
            {
                if (is_string($a[0]))
                {
                    $line = $this->__stringBind($a[0]);

                    $where = $line['line'];
                    $bind = $line['bind'];
                    
                    if (preg_match('/({where})/', $structure))
                    {
                        $structure = str_replace('{where}', 'WHERE '.$where.' ', $structure);
                        $this->query = $structure;
                        $this->lastWhere = 'WHERE '.$where.' ';
                    }
                    else
                    {
                        $this->query = trim($this->query) .' '. $where;
                        $w = substr($this->query, strpos($this->query, 'WHERE'));
                        $w = substr($w, 0, strrpos($w, $where)) . $where;
                        $this->lastWhere = $w;
                    }
                    
                    array_shift($a);

                    $this->__addBind($a, $bind);

                    $newBind = [];
                    
                    // avoid clashes
                    $this->__avoidClashes($bind, $newBind);

                    $this->bind = array_merge($this->bind, $newBind);
                }
            }
        }

        return $this;
    }

    // run sqlStatement
    public function _sql()
    {
        $a = func_get_args();

        // sql
        $data = $a[0];
        array_shift($a);
        $instance = $this;

        
        if (is_string($data) && strlen($data) > 3)
        {
            $bind = [];
            $newBind = [];
            $getAssignment = true;

            if (isset($a[0]) && $a[0] === false)
            {
                $getAssignment = false;
            }

            if ($getAssignment)
            {
                // get assignment
                if (preg_match('/(=|!=|>|<|>=|<=)/', $data))
                {
                    preg_match_all('/\s{1,}([\S]+)\s{0,}(=|!=|>|<|>=|<=)\s{0,}[:|\?|\'|"|0-9]/', $data, $match);

                    foreach ($match[0] as $i => $ln)
                    {
                        $ln = trim($ln);
                        $quote = preg_quote($ln);

                        $end = substr($ln,-1);
                        if ($end == ':')
                        {
                            preg_match("/($quote)([\S]+)/", $data, $m);
                            $ln = trim($m[0]);
                        }
                        elseif (preg_match('/[0-9]/', $end))
                        {
                            preg_match("/($quote)([\S]+|)/", $data, $m);
                            $ln = trim($m[0]);
                        }
                        elseif (preg_match('/[\'|"]/', $end))
                        {
                            preg_match("/($quote)([\s\S])['|\"|\S]{0,}['|\"]/", $data, $m);
                            $ln = trim($m[0]);
                        }

                        $dl = $instance->__stringBind($ln);
                        $line = $dl['line'];
                        $bind[] = $dl['bind'];

                        $beg = strpos($data, $ln);
                        $sized = strlen($ln);

                        $data = substr_replace($data, $line, $beg, $sized);
                        unset($match[0][$i]);
                    }

                    if (count($bind) > 0)
                    {
                        foreach($bind as $i => $arr)
                        {
                            if (is_array($arr))
                            {
                                foreach($arr as $key => $val)
                                {
                                    $newBind[$key] = $val;
                                }
                            }
                        }
                    }
                }

                if (count($newBind) > 0)
                {
                    $instance->__addBind($a, $newBind);

                    $newBind2 = [];
                                            
                    // avoid clashes
                    $instance->__avoidClashes($newBind, $newBind2);

                    $instance->bind = array_merge($instance->bind, $newBind2);
                }
            }

            $instance->query = $data;

            $instance->method = 'sql';

            return $instance;
        }
        else
        {
            return (object) ['rows' => 0, 'row' => 0, 'error' => 'Invalid sql statement.'];
        }
        
    }

    // prepare query
    private function ___prepare($query)
    {
        if ($this->pdoInstance == null)
        {
            $instance = $this->_serve();
            $this->pdoInstance = $instance->pdoInstance;
            $this->instancedb = $instance->instancedb;
        }

        if (strlen($query) > 4)
        {
            if (Handler::$dbset === true)
            {
                $con = $this->pdoInstance;
                $usePDO = Handler::usePDO($this->instancedb);


                if ($this->pdoInstance != null)
                {
                    // use transactions.
                    if (method_exists($con, 'inTransaction') && $con->inTransaction() === false)
                    {
                        if (method_exists($con, 'beginTransaction'))
                        {
                            $con->beginTransaction();
                        }
                    }

                    $order = [];
                    $bind = $this->bind;

                    $this->getBinds = $bind;
                    $this->getSql = $query;


                    if (!$usePDO)
                    {
                        if (preg_match('/[:]([\S]*)/', $query))
                        {
                            $_query = $query;

                            preg_match_all('/([:][\S]*?)[,|\s|)]/', $query, $matches);
                            if (count($matches[0]) > 0 && count($bind) > 0)
                            {
                                foreach ($matches[1] as $index => $param)
                                {
                                    $replace = $param;
                                    $param = trim($param);
                                    $param = preg_replace('/^[:]/','',$param);
                                    $val = isset($bind[$param]) ? $bind[$param] : null;

                                    $type = '';

                                    switch (gettype($val))
                                    {
                                        case 'integer':
                                            $type = 'i';
                                        break;
                                        case 'string':
                                            $type = 's';
                                        break;
                                        case 'double':
                                            $type = 'd';
                                        break;
                                        case 'blob':
                                            $type = 'b';
                                        break;
                                        default:
                                            $type = 'i';
                                    }

                                    $order[] = [
                                        'type' => $type,
                                        'val' => $val
                                    ];
                                }
                            }

                            $_query = preg_replace('/([:][\S]*?)([,|\s|)])/','?$2',$_query);

                            $_query = preg_replace('/[\?]([a-zA-Z]*)/','? $1', $_query);
                            $query = $_query;
                        }
                    }

                    $this->query = $query;

                    $smt = $con->prepare($query);   
                   
                    if (count($bind) > 0)
                    {
                        $index = 0;

                        if ($usePDO)
                        {
                            foreach ($bind as $key => $val)
                            {
                                if (is_array($val) && isset($val[$index]))
                                {
                                    $val = $val[$index];
                                    $index++;
                                }
                                
                                if (is_string($val))
                                {
                                    $smt->bindValue(':'.$key, $val, PDO::PARAM_STR);
                                }
                                elseif (is_int($val))
                                {
                                    $smt->bindValue(':'.$key, $val, PDO::PARAM_INT);
                                }
                                elseif (is_bool($val))
                                {
                                    $smt->bindValue(':'.$key, $val, PDO::PARAM_BOOL);
                                }
                                elseif (is_null($val))
                                {
                                    $smt->bindValue(':'.$key, $val, PDO::PARAM_NULL);
                                }
                                elseif (!is_array($val))
                                {
                                    $smt->bindValue(':'.$key, $val);
                                }
                                else
                                {
                                    $value = array_shift($val);
                                    $smt->bindValue(':'.$key, $value);
                                }
                            }
                        }
                        else
                        {
                            $binds = [];
                            $types = [];

                            if (count($order) > 0)
                            {
                                foreach($order as $i => $arr)
                                {
                                    $types[] = $arr['type'];
                                    $binds[] = $arr['val'];
                                }
                            }

                            $types = implode('', $types);
                            $refArr = $binds;
                            $smt->bind_param($types, ...$refArr);
                            $_binds = [$types];
                            $_binds = array_merge($_binds, $binds);
                            $this->bind = $_binds;
                        }
                    }

                    return $smt;
                }
                else
                {
                    throw new \Exception('Database not serving any connection to this file.');
                }
            }
        }
    }

    // execute query
    private function ___execute($smt)
    {
        $promise = new DBPromise;

        $usePDO = Handler::usePDO($this->instancedb);
        $promise->usePDO = $usePDO;
        $promise->setFetchMode();

        if ($this->allowQuery($smt))
        {
            if ($this->query != '')
            {
                $query = $this->query;
                $bind = $this->bind;

                if ($this->method != 'get')
                {
                    $promise->setBindData($bind);
                }

                $this->query = '';
                $this->bind = [];
                    
                if (Handler::$dbset === true)
                {
                    try 
                    {
                        $exec = $smt->execute();

                        if ($usePDO)
                        {
                            $rows = $smt->rowCount();
                        }
                        else
                        {
                            
                            $smt->store_result();

                            if (is_object($smt) && property_exists($smt, 'num_rows'))
                            {
                                $rows = $smt->num_rows;
                            }
                            else
                            {
                                $rows = $smt->affected_rows;
                            }
                            
                        }

                        if ($exec)
                        {
                            switch ($this->method)
                            {
                                case 'insert':
                                case 'update':
                                case 'delete':
                                    self::$transactionCode = 200;
                                break;
                            }
                        }

                        $promise->setpdoInstance($smt);
                        $promise->rows = $rows;
                        $promise->row = $rows;

                        if ($this->method == 'get')
                        {
                            if ($rows == 1)
                            {
                                $promise->row = 1;
                                
                                if ($usePDO)
                                {
                                    $arr = $smt->fetch(PDO::FETCH_ASSOC);
                                }
                                else
                                {
                                    $promise->bind_array($smt, $row);
                                    $smt->fetch();
                                    $smt->reset();

                                    $arr = $row;
                                }

                                $promise->set('getPacked', $arr);
                            }
                        }
                        elseif ($this->method == 'insert')
                        {
                            if ($usePDO)
                            {
                                $id = $this->pdoInstance->lastInsertId();
                            }
                            else
                            {
                                $id = $this->pdoInstance->insert_id;
                            }

                            $promise->id = $id;
                            $promise->ok = true;
                        }
                        else
                        {
                            $promise->ok = true;
                        }

                        if ($promise->ok)
                        {
                            // add to migration
                            if ($this->method != 'get' &&
                                $this->method == 'insert' ||
                                $this->method == 'update' ||
                                $this->method == 'delete'
                            )
                            {
                                $query = null;
                                $bind = null;
                            }
                        }

                        // commit transaction
                        if (method_exists($this->pdoInstance, 'commit'))
                        {
                            $this->pdoInstance->commit();
                        }
                    }
                    catch(PDOException $e)
                    {
                        if (method_exists($this->pdoInstance, 'rollback'))
                        {
                            // rollback transaction
                            $this->pdoInstance->rollback();
                        }

                        // pack error
                        $this->errorpack[$this->method][] = $e;
                        $promise->hasError = true;
                        $promise->errors[] = $e;
                    }
                }
            }
        }
        else
        {
            $promise->hasError = true;
            $promise->errors = ['Record Exists. Failed to insert'];
            $promise->ok = false;
            $promise->error = 'Failed to insert record. Data exists.';
        }

        $table = '';

        switch (strlen($this->table) > 1)
        {
            case true:
                $table = $this->table;
            break;

            case false:
                $table = self::$activeTable;
            break;
        }

        // set table
        $promise->table = $table;

        // return promise
        return $promise;
    }

    public static function __callStatic($method, $data)
    {
        // create instance
        $createinstance = function() use ($method)
        {
            $instance = null;
            if (isset(self::$activeConnections[$method]))
            {
                $instance = self::$activeConnections[$method];
            }
            else
            {
                $instance = new Client;
                self::$activeConnections[$method] = $instance;
            }

            $instance->query = '';
            $instance->bind = [];
            $instance->_serve();
            $instance->getSql = '';
            $instance->getBinds = [];

            return $instance;
        };

        switch (strtolower($method))
        {
            case 'sql':
                return $createinstance()->callMethod('_sql', $data)->go();
            break;

            case 'table':
                $instance = $createinstance();
                $instance->table = $data[0];
                return ORMReciever::getInstance($instance);
            break;

            case 'serve':
                return $createinstance();
            break;

            case 'gettableinfo':
                $instance = $createinstance();
                return $instance->callMethod('_getTableInfo', $data);
            break;

            case 'apply':
                return $createinstance()->callMethod('_apply', $data);
            break;

            default:
                // set table name
                $instance = $createinstance();
                $instance->table = $method;
                return ORMReciever::getInstance($instance);

        }
    }

    public function __call($method, $data)
    {
        switch (strtolower($method))
        {
            case 'get':
            case 'delete':
            case 'update':
            case 'insert':
                if ($this->method != '' && $this->query != '')
                {
                    // execute the previous request
                    $exec = $this->go();
                    return call_user_func_array([$exec, $method], $data);
                }
                else
                {
                    $this->bind = [];
                    $instance = $this;

                    if ($this->driver == null)
                    {
                        $instance = $this->_serve();
                        $this->driver = $instance->driver;
                        $this->getAllowed();
                    }

                    $queries = $this->drivers($this->driver);

                    $pass = $method == 'get' ? 'select' : $method;
                    $structure = isset($queries[$pass]) ? $queries[$pass] : null;
                    
                    $this->method = $method;

                    if (strlen($this->table) > 1)
                    {
                        $structure = str_replace('{table}', $this->table, $structure);
                    }

                    $func = '___'.$method;

                    return $this->callMethod($func, [$data, $structure, $this->table]);
                }
            break;

            case 'apply':
                return $this->callMethod('_apply', $data);
            break;

            case 'sql':
                return $this->callMethod('_sql', $data)->go();
            break;

            case 'allowHTML':
                $this->allowHTMLTags = true;
            break;

            case 'bind':
                return $this->callMethod('runBinding', $data);
            break;

            case 'where':
                return $this->callMethod('runWhere', $data);
            break;

            default:

                silenterror();

                if (isset($this->allowed[$method]))
                {
                    $allowed = $this->getAllowed($data, $this->query);
                    $this->query .= is_callable($allowed[$method]) ? $allowed[$method]() : $allowed[$method];
                }
                else
                {

                    // check if has fetch method
                    if (DBPromise::hasFetchMethod($method))
                    {
                        $run = $this->go();
                        return call_user_func_array([$run, $method], $data);
                    }

                    // specifically where.. 
                    if (preg_match('/({where})/', $this->query))
                    {
                        $newBind = [];
                        $bind = [$method => ''];

                        $i = $this->__avoidClashes($bind, $newBind);

                        $where = 'WHERE '.$method.' = :'.$method.$i.' ';

                        $this->bind = array_merge($this->bind, $newBind);
                        
                        $this->query = str_replace('{where}', $where, $this->query);
                    }
                    else
                    {
                        
                        $newBind = [];
                        $bind = [$method => ''];

                        $i = $this->__avoidClashes($bind, $newBind);

                        $append = ' '.$method.' = :'.$method.$i.' ';
                        
                        $this->bind = array_merge($this->bind, $newBind);

                        $this->query = trim($this->query) . $append;
                    }

                }
        }

        return $this;
    }

    // allow query execution.
    private function allowQuery(&$con)
    {
       if ($this->method == 'insert')
       {
            $instance = &$this;
            
            if ($instance->allowedQueryCalled === false)
            {
                $usePDO = Handler::usePDO($instance->instancedb);

                $db = $instance->pdoInstance;
                // check if record doesn't exists.
                // to avoid repitition.
                // get columns
                $column = substr($instance->query, strpos($instance->query, '(')+1);
                $column = substr($column, 0, strpos($column, ')'));

                // convert to an array
                $array = explode(',', $column);

                $bind = $instance->bind;

                $where = [];

                if ($instance->getSql != '')
                {
                    if ($usePDO)
                    {
                        $keys = count(explode(',', $instance->insertKeys));
                        $bind = array_splice($bind, 0, count($array));
                        $bindKeys = array_keys($bind);

                        foreach ($array as $index => $key)
                        {
                            $where[] = $key .' = ?';
                        }
                        

                        $where = implode(" AND ", $where);
                        $select = 'SELECT * FROM '.$instance->table.' WHERE '.$where;

                        // group
                        $query_group = [];
                        $start = 0;
                        $end = 0;

                        $bind = $instance->bind;

                        $total = count($bind);

                        $keyBinds = array_keys($instance->getBinds);

                        for ($i=$start; $i<$total; $i++)
                        {
                            $bindCopy = $bind;
                            $data = array_splice($bindCopy, $start, $keys);

                            $keyBindCopy = $keyBinds;
                            $kb = array_splice($keyBindCopy, $start, $keys);

                            $query_group[] = ['bind' => $data, 'keys' => $kb];

                            if ($start < ($total - $keys))
                            {
                                $start += $keys;
                            }
                            else
                            {
                                break;
                            }
                        }

                        $success = 0;

                        $query = $instance->getSql;
                        $newBind = [];
                        $updateQuery = false;
                        $values = [];

                        $sel = $db->prepare($select);

                        foreach ($query_group as $index => $record)
                        {
                            $bind = $record['bind'];

                            // run query
                            $exec = $sel->execute(array_values($bind));

                            $keybinds = $record['keys'];

                            if ($sel->rowCount() == 0)
                            {
                                $success++;

                                $value = [];
                                foreach ($keybinds as $i => $k)
                                {
                                    $newBind[$k] = $instance->getBinds[$k];
                                    $value[] = ':'.$k;
                                }

                                $values[] = '('.implode(',', $value).')';
                            }
                            else
                            {
                                $updateQuery = true;
                            }
                        }

                        if ($success > 0)
                        {
                            if ($updateQuery)
                            {
                                // build new query
                                $query = $instance->query;
                                $stop = strpos($query, 'VALUES');
                                $query = substr($query, 0, $stop);

                                $query .= 'VALUES '.implode(',', $values);

                                $instance->bind = $newBind;
                                $instance->query = $query;

                                $instance->allowedQueryCalled = true;

                                $con = $this->___prepare($query);
                            }

                            return true;
                        }
                        else
                        {
                            return false;
                        }

                    }
                    else
                    {
                        $types = str_split($bind[0]);
                        array_shift($bind);
                        $keys = count(explode(',', $instance->insertKeys));
                        
                        $select = 'SELECT * FROM '.$instance->table.' WHERE ';

                        foreach ($array as $index => $key)
                        {
                            $where[] = $key .' = ?';
                        }

                        $where = implode(" AND ", $where);

                        $select .= $where;

                        // group
                        $query_group = [];
                        $start = 0;
                        $end = 0;

                        $total = count($bind);

                        $keyBinds = array_keys($instance->getBinds);

                        for ($i=$start; $i<$total; $i++)
                        {
                            $copy = $types;
                            $type = implode('', array_splice($copy, $start, $keys));

                            $bindCopy = $bind;
                            $data = array_splice($bindCopy, $start, $keys);

                            $keyBindCopy = $keyBinds;
                            $kb = array_splice($keyBindCopy, $start, $keys);

                            array_unshift($data, $type);
                            $query_group[] = ['bind' => $data, 'keys' => $kb];

                            if ($start < ($total - $keys))
                            {
                                $start += $keys;
                            }
                            else
                            {
                                break;
                            }
                        }

                        $success = 0;

                        // run query
                        $sel = $db->prepare($select);

                        $query = $instance->getSql;
                        $newBind = [];
                        $updateQuery = false;
                        $values = [];

                        foreach ($query_group as $index => $record)
                        {
                            $bind = $record['bind'];
                            $type = $bind[0];
                            $other = array_splice($bind, 1);
                            $sel->bind_param($bind[0], ...$other);
                            $sel->execute();
                            $sel->store_result();

                            $keybinds = $record['keys'];

                            if ($sel->num_rows == 0)
                            {
                                $success++;

                                $value = [];
                                foreach ($keybinds as $i => $k)
                                {
                                    $newBind[$k] = $instance->getBinds[$k];
                                    $value[] = ':'.$k;
                                }

                                $values[] = '('.implode(',', $value).')';
                            }
                            else
                            {
                                $updateQuery = true;
                            }
                        }

                        if ($success > 0)
                        {
                            if ($updateQuery)
                            {
                                // build new query
                                $query = $instance->query;
                                $stop = strpos($query, 'VALUES');
                                $query = substr($query, 0, $stop);

                                $query .= 'VALUES '.implode(',', $values);

                                $instance->bind = $newBind;
                                $instance->query = $query;

                                $instance->allowedQueryCalled = true;

                                $con = $instance->___prepare($query);
                            }

                            return true;
                        }
                        else
                        {
                            return false;
                        }
                    }
                }
            }
       }

       return true;
    }

    // check for potential errors
    private function __checkForErrors($command)
    {
        $free = true;
        $query = $this->query;
        $errors = [];

        switch ($command)
        {
            case 'update':
                if (preg_match('/({table})/', $query))
                {
                    $free = false;
                    $errors[] = 'Table not found. Statement Constrution failed.';
                }

                if (preg_match('/({query})/', $query))
                {
                    $free = false;
                    $errors[] = 'Query not found. Statement Constrution failed.';
                }

                if (preg_match('/({where})/', $query))
                {
                    $free = false;
                    $errors[] = 'Where statement missing. Statement Constrution failed.';
                }
            break;
        }

        if (count($errors) > 0)
        {
            if (!isset($this->errorpack[$command]))
            {
                $this->errorpack[$command] = [];
            }

            $this->errorpack[$command] = array_merge($this->errorpack[$command], $errors);
        }

        return $free;
    }

    // serve database connection  
    private function _serve()
    {
        $instance = &$this;
        
        // get default data-source-name
        $connect = Handler::getDefault();

        
        if ($instance->useConnection !== null)
        {
            $connect = $instance->useConnection;
        }

        $freshConnect = true;

        if (is_string($connect) && strlen($connect) > 1)
        {
            if (isset(self::$openedConnection[$connect]))
            {
                $instance = self::$openedConnection[$connect];
                $instance->table = !is_null($this->table) ? $this->table : $instance->table;
                $instance->query = '';
                $instance->bind = [];
                $instance->allowed = $instance->getAllowed(); 

                $freshConnect = false;
            }
        }

        if ($instance->pdoInstance == null)
        {
            if ($freshConnect)
            {
                if (is_string($connect) && strlen($connect) > 1)
                {
                    // get driver
                    $driver = Handler::connectionConfig($connect, 'driver');
                    
                    if (Handler::$dbset === true)
                    {
                        // a valid driver?
                        if ($instance->drivers($driver) !== null)
                        {
                            // save driver
                            $instance->driver = $driver;
                            $instance->instancedb = $connect;
                            
                            // extablish connection
                            $con = Handler::active($connect);

                            // save instance.
                            $instance->pdoInstance = $con;

                            $instance->allowed = $instance->getAllowed();

                            // save instance
                            self::$openedConnection[$connect] = $instance;
                        }
                        else
                        {
                            throw new \Exception('Driver you used isn\'t supported on this server. Please see documentation');
                        }
                    }
                }
                else
                {
                    throw new \Exception('No Database connection to establish. Please check your configuration.');
                }
            }

        }

        return $instance;
    }

    // process request
    public function go()
    {

        if ($this->failed === false)
        {
            $name = $this->method;

            // process request.
            // handle errors
            $ok = $this->__checkForErrors($name);

            // we good?
            if ($ok)
            {
                // good
                if ($name == 'get')
                {

                    // fill in the gap 
                    foreach ($this->bind as $key => $val)
                    {
                        if (is_null($val) || (is_string($val) && strlen($val) == 0))
                        {
                            foreach ($this->bind as $i => $x)
                            {
                                if (!empty($x))
                                {
                                    $this->bind[$key] = $x;
                                    break;
                                }
                            }
                        }
                    }

                    // remove placeholder {where}
                    $this->query = str_replace('{where}','',$this->query);
                }

                if (!$this->allowHTMLTags)
                {
                    $bind = $this->bind;
                    
                    foreach ($bind as $key => $val)
                    {
                        if (is_array($val) || is_object($val))
                        {
                            foreach ($val as $i => $x)
                            {
                                if (is_string($x))
                                {
                                    $val[$i] = strip_tags($x);
                                }
                            }

                            $bind[$key] = $val;
                        }
                        elseif (is_string($val))
                        {
                            $bind[$key] = strip_tags($val);
                        }
                    }

                    $this->bind = $bind;
                }

                // prepare query
                $smt = $this->___prepare($this->query);

                $data = $this->___execute($smt);

                $this->method = '';

                return $data;
            }
        }
        else
        {
            return (object) ['rows' => 0, 'row' => 0, 'error' => $this->errorpack];
        }
    }

    // get __magic method
    public function __get($name)
    {
        if ($this->method != '')
        {
            return $this->go()->{$name};
        }
    }

    private function callMethod($method, $data)
    {
        return call_user_func_array([$this, $method], $data);
    }

    // convert object to array.
    public static function toArray($object)
    {
        $res = [];

        $res = json_encode($object);
        $dec = json_decode($res, true);

        return $dec;
    }


    // convert array to object
    public static function toObject($array)
    {
        $res = (object) [];

        foreach ($array as $i => $x)
        {
            if (is_array($x))
            {
                $x = self::toObject($x);

                $res->{$i} = (object) $x;
            }
            else
            {
                $res->{$i} = $x;
            }
        }

        return $res;
    }
}

// ends here.
