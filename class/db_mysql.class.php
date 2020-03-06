<?php
/**
* Mysql 数据库类
*/
class db_mysql
{
	/**
	* MySQL 连接标识
	* @var resource
	*/
	var $connid;

	/**
	* 整型变量用来计算被执行的sql语句数量
	* @var int
	*/
	var $querynum = 0;
	//$db = new db_mysql();
	//$db->connect($dbhost, $dbuser, $dbpw, '', $pconnect);
	/**
	* 数据库连接，返回数据库连接标识符
	* @param string 数据库服务器主机
	* @param string 数据库服务器帐号
	* @param string 数据库服务器密码
	* @param string 数据库名
	* @param bool 是否保持持续连接，1为持续连接，0为非持续连接
	* @return link_identifier
	*/
	function connect($dbhost, $dbuser, $dbpw, $dbname, $pconnect = 0) 
	{
		global $db_config;
		$func = $pconnect == 1 ? 'mysql_pconnect' : 'mysql_connect';
		if(!$this->connid = @$func($dbhost, $dbuser, $dbpw))
		{
			return 0;
			//$this->halt('Can not connect to MySQL server');
		}
		// 当mysql版本为4.1以上时，启用数据库字符集设置
		if($this->version() > '4.1' && $db_config['DB_CHARSET'])
        {
			mysql_query("SET NAMES '".$db_config['DB_CHARSET']."'" , $this->connid);
		}
		// 当mysql版本为5.0以上时，设置sql mode
		if($this->version() > '5.0') 
		{
			mysql_query("SET sql_mode=''" , $this->connid);
		}
		if($dbname) 
		{
			if(!@mysql_select_db($dbname , $this->connid))
			{
				return -1;
				//$this->halt('Cannot use database '.$dbname);
			}
		}
		return $this->connid;
	}

	/**
	* 选择数据库
	* @param string 数据库名
	*/
	function select_db($dbname) 
	{
		return mysql_select_db($dbname , $this->connid);
	}
	

	//代替query查询
	function query_memcache($sql) 
	{
		global $mem;

		$key = md5($sql);

		if(!($ret = $mem->get($key))) {
            $query = $this->query($sql);
			$res = array();
			while($item  = $this->fetch_array($query)) {
				$res[] = $item;
			}
			$this->free_result($query);			
			$ret = $res;
			$mem->set($key, $ret , 0, MEMCACHE_LIFE_TIME);
		}
		return $ret;
	}
	
	function get_one_memcache($sql) 
	{
		global $mem;

		$key = md5($sql);
        if(!($ret = $mem->get($key))) {
			echo 'no cache';
			$query = $this->query($sql);
			$ret = $this->fetch_array($query);
			$this->free_result($query);			
			$mem->set($key, $ret , 0, MEMCACHE_LIFE_TIME);
		}else {
			echo 'from cache';
		}
		return $ret;
	}


	/**
	* 执行sql语句
	* @param string sql语句
	* @param string 默认为空，可选值为 CACHE UNBUFFERED
	* @param int Cache以秒为单位的生命周期
	* @return resource
	*/
	function query($sql , $type = '' , $expires = 3600, $dbname = '') 
	{
		global $db_config;
		$sql = str_replace('#@__',$db_config['DB_PREFIX'],$sql);

		$func = $type == 'UNBUFFERED' ? 'mysql_unbuffered_query' : 'mysql_query';
		if(!($query = $func($sql , $this->connid)) && $type != 'SILENT')
		{
			$this->halt('MySQL Query Error', $sql);
		}
		$this->querynum++;
		return $query;
	}
	
	function fetch($sql)
	{
		return $this->query($sql);
	}


	/**
	* 执行sql语句，只得到一条记录
	* @param string sql语句
	* @param string 默认为空，可选值为 CACHE UNBUFFERED
	* @param int Cache以秒为单位的生命周期
	* @return array
	*/
	function get_one($sql, $type = '', $expires = 3600, $dbname = '')
	{
		$query = $this->query($sql, $type, $expires, $dbname);
		$rs = $this->fetch_array($query);
		$this->free_result($query);
		return $rs ;
	}
	
	/**
	*返回单条符合条件的结果,如SELECT，SHOW等
	*@param String $sql
	*@param String $result_type
	*@return Array 
	*/
	function fetchOne($sql, $type = '', $expires = 3600, $dbname = '') 
	{
		return $this->get_one($sql, $type, $expires, $dbname);
	}
	/**
	*返回符合条件的结果集
	*@param String $sql
	*@return Array
	*/
	function fetchAll($sql,$type = MYSQL_ASSOC) 
	{
		$tmp = array();
		$query = $this->query($sql);
		while($item  = $this->fetch_array($query)) {
			$tmp[] = $item;
		}
		$this->free_result($query);			
		return $tmp;
	}
	/**
	*插入数据
	*/
	function insertTable($tablename, $insertsqlarr, $returnid=0, $replace = false, $silent=0)
	{
		$insertkeysql = $insertvaluesql = $comma = '';
		foreach ($insertsqlarr as $insert_key => $insert_value) {
			$insertkeysql .= $comma.'`'.$insert_key.'`';
			$insertvaluesql .= $comma."'".mysql_real_escape_string($insert_value)."'";
			$comma = ', ';
		}
		$method = $replace?'REPLACE':'INSERT';
		$this->query($method.' INTO '.$tablename.' ('.$insertkeysql.') VALUES ('.$insertvaluesql.')');
		if($returnid && !$replace) return $this->insert_id();
	}
	/**
	*更新数据
	*/
	function updateTable($tablename, $setsqlarr, $wheresqlarr='', $silent=0) 
	{
		$setsql = $comma = '';
		foreach ($setsqlarr as $set_key => $set_value) {
			$setsql .= $comma.'`'.$set_key.'`'.'=\''.mysql_real_escape_string($set_value).'\'';
			$comma = ', ';
		}
		if(empty($wheresqlarr)) {
			$where = '1';
		}else{
			$where = $this->AndSQL($wheresqlarr);
		}
		$this->query('UPDATE '.$tablename.' SET '.$setsql.' WHERE '.$where);
	}
	/**
	*查询条件组装String
	*
	*/
	public function AndSQL($where) 
	{
		$String = $comma = '';
		if(is_array($where)) 
		{
			foreach($where AS $k=>$v)
			{
				$String .= $comma.'`'.$k.'`'.'=\''.$v.'\'';
				$comma = ' AND ';
			}
		}
		else
		{
			$String = $where;
		}
		return $String;
	}

	/**
	* 从结果集中取得一行作为关联数组
	* @param resource 数据库查询结果资源
	* @param string 定义返回类型
	* @return array
	*/
	function fetch_array($query, $result_type = MYSQL_ASSOC) 
	{
		return is_resource($query) ? mysql_fetch_array($query,$result_type) : $query[0];
		//return mysql_fetch_array($query, $result_type);
	}
	
	function fetch_object($query)
	{
		return mysql_fetch_object($query);
	}

	/**
	* 取得前一次 MySQL 操作所影响的记录行数
	* @return int
	*/
	function affected_rows() 
	{
		return mysql_affected_rows($this->connid);
	}

	/**
	* 取得结果集中行的数目
	* @return int
	*/
	function num_rows($query) 
	{
		return mysql_num_rows($query);
	}

	/**
	* 返回结果集中字段的数目
	* @return int
	*/
	function num_fields($query) 
	{
		return mysql_num_fields($query);
	}

    /**
	* @return array
	*/
	function result($query, $row) 
	{
		return @mysql_result($query, $row);
	}

	function free_result($query) 
	{
		return mysql_free_result($query);
	}

	/**
	* 取得上一步 INSERT 操作产生的 ID 
	* @return int
	*/
	function insert_id() 
	{
		return mysql_insert_id($this->connid);
	}

    /**
	* @return array
	*/
	function fetch_row($query) 
	{
		return mysql_fetch_row($query);
	}

    /**
	* @return string
	*/
	function version() 
	{
		return mysql_get_server_info($this->connid);
	}

	function close() 
	{
		$void = func_get_args();
		foreach ($void as $query) {
			if (is_resource($query) && get_resource_type($query)==='mysql result') {
				mysql_free_result($query);
			}
		}
		unset($void);
		
		if($this->connid != 0) mysql_close($this->connid);
	}

    /**
	* @return string
	*/
	function error()
	{
		return @mysql_error($this->connid);
	}

    /**
	* @return int
	*/
	function errno()
	{
		return intval(@mysql_errno($this->connid)) ;
	}

    /**
	* 显示mysql错误信息
	*/
	function halt($message = '', $sql = '')
	{
		exit("The current database connecting is too large, please try again later.Thank you.<br/><font color='white'>MySQL Query:$sql <br> MySQL Error:".$this->error()." <br> MySQL Errno:".$this->errno()." <br> Message:$message </font>");
	}
}
?>
