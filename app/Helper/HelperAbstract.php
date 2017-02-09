<?php
/*Read and build configurations from file setting.ini or get default value*/
namespace App\Helper;

//Api class
class HelperAbstract {
	
	protected static $_instances = array(); //all instances
	
	/**
	* param $name is helper name object
	*/
	public static function get($name){
		if( !isset(self::$_instances[$name]) || is_null(self::$_instances[$name]) ) {
			$strClassName = '\\App\\Helper\\'.ucfirst($name);
			self::$_instances[$name] = new $strClassName();
		}
		return self::$_instances[$name];
	}
}
