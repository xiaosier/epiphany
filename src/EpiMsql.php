<?php
/*
 * Multi sql 
 * Need PHP version>5.3.0 with mysql driver is mysqlnd
 * @authot lazypeple
 */
class EpiMsql
{
	static $inst = array();
	static $singleton = 0;
	private $links = array();
	private $host, $port, $user, $pass, $dbname;

	public function __construct($config)
	{
		if (!self::$singleton) {
			EpiException::raise(new EpiException('This class cannot be instantiated by the new keyword.  You must instantiate it using: $obj = EpiMsql::getInstance();'));
		}
		$check_params = function($need_items) use ($config){
			foreach ($need_items as $item) {
				if (!array_key_exists($item, $config) || !$config[$item]) {
					EpiException::raise(new EpiException(sprintf("Param %s missing or empty", $item)));
				}
			}
		};
        $need_items = array('host', 'port', 'user', 'pass', 'dbname');
		$check_params($need_items);
		extract($config);
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;
		$this->dbname = $dbname;
	}

	public function addSql($sql)
	{
		// hash value
		$link = $this->getLink();
		$this->links[$link]['link']->query($sql, MYSQLI_ASYNC);
		return $link;
	}

	public function query()
	{
		$done = 0;
		$total = count($this->links);
		do { 
		    $tmp = array();
		    foreach ($this->links as $value) {
		        $tmp[] = $value['link'];
		    }
		 
		    $read = $errors = $reject = $tmp;
		    $re = mysqli_poll($read, $errors, $reject, 1);
		    if (false === $re) {
		    	EpiException::raise(new EpiException("Mysqli poll error"));
		    } elseif ($re < 1) {
		        continue;
		    }
		 
		    foreach ($read as $link) {
		        $sql_result = $link->reap_async_query();
		        $hash = spl_object_hash($link);
		        if (is_object($sql_result)) {
		        	$i = 0;
		        	while ($ret = $sql_result->fetch_array(MYSQLI_ASSOC)) {
		        		$retval[$i++] = $ret;
		        	}
		            $sql_result->free();
		            $this->links[$hash]['error'] = null;
		            $this->links[$hash]['result'] = $retval;
		        } else {
		        	$this->links[$hash]['error'] = $link->error;
		        }
		        $done++;
		    }
		 
		    foreach ($errors as $link) {
		    	$hash = spl_object_hash($link);
		    	$this->links[$hash]['error'] = $link->error;
		        $done++;
		    }
		 
		    foreach ($reject as $link) {
		    	// @TODO
		    }
		} while ($done < $total);
	}

	public function getResult($hash)
	{
		if (!array_key_exists($hash, $this->links)) {
			return false;
		}
		$retval = array();
		$retval['error'] = $this->links[$hash]['error'];
		if (array_key_exists('result', $this->links[$hash])) {
			$retval['result'] = $this->links[$hash]['result'];
		}
		return $retval;
	}

	private function getLink()
	{
		$obj = new mysqli($this->host, $this->user, $this->pass, $this->dbname, $this->port);
		$hash_value = spl_object_hash($obj);
		$this->links[$hash_value] = array('link' => $obj, 'hash_value' => $hash_value);
		return $hash_value;
	}

	public static function getInstance($config)
    {
    	$array2string = function($array) {
    		ksort($array);
    		$convert_str = '';
    		foreach ($array as $key => $value) {
    			if (is_array($value)) {
    				$convert_str .=  $array2string($value);
    			} else {
    				$convert_str .=  $key.$value;
    			}
    		}
    		return $convert_str;
    	};
    	// $config is an array
    	$md5sum = md5($array2string($config));
    	if (!array_key_exists($md5sum, self::$inst)) {
    		self::$singleton = 1;
    		$retval = new EpiMsql($config);
    		self::$inst[$md5sum] = $retval;
    		return $retval;
     	}
     	return self::$inst[$md5sum];
    }
}

if (!function_exists('msql')) {
	/*
	 * msql instance proxy
	 */
	function msql($config)
	{
		return EpiMsql::getInstance($config);		
	}
}
