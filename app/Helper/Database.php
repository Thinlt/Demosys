<?php
/*Read and build configurations from file setting.ini or get default value*/
namespace App\Helper;

//Api class
class Database {
	
	protected $_user;
	protected $_pass;
	protected $_host;
	
	public function __construct(){
		$this->_user = \Config::get('MYSQL_ROOT_USER');
		$this->_pass = \Config::get('MYSQL_ROOT_PASS');
		$this->_host = \Config::get('MYSQL_HOST');
		if($this->_user == '' || $this->_pass == ''){
			throw new \Exception('<error>ERROR: The value of variables MYSQL_ROOT_USER or MYSQL_ROOT_PASS not set in config.ini</error>');
		}
	}
	
	public function checkUserExists($username){
		$sql_login = 'mysql -u '.$this->_user.' --password='.escapeshellarg($this->_pass).' --host='.$this->_host;
		exec($sql_login.' -e "SELECT User FROM mysql.user WHERE User = \''.$username.'\' and Host = \''.$this->_host.'\'" 2>/dev/null', $users);
		if(in_array($username, $users)){
			return true;
		}
		return false;
	}
	
	/*
	* check database name exists
	* return bool
	*/
	public function checkDbExists($db_name){
		$sql_login = 'mysql -u '.$this->_user.' --password='.escapeshellarg($this->_pass).' --host='.$this->_host;
		exec($sql_login.' -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''.$db_name.'\'" 2>/dev/null', $databases);
		if(in_array($db_name, $databases)){
			return true;
		}
		return false;
	}
	
	/*
	* run sql statement
	* return result
	*/
	public function run($sql, $dbname){
		$sql_login = 'mysql -u '.$this->_user.' --password='.escapeshellarg($this->_pass).' --host='.$this->_host;
		exec($sql_login.' '.$dbname.' -e "'.$sql.'" ', $result);
		return $result;
	}
}
