<?php
/*Read and build configurations from file setting.ini or get default value*/
namespace App;

//Api class
final class Setting {
	/**
	* @var Singleton
	*/
	private static $_instance;
	
	public static function getInstance(){
		if( is_null(self::$_instance) ) {
			self::$_instance = new SettingObject();
		}
		return self::$_instance;
	}
	
	public static function __callStatic($name, $arguments){
		if(is_callable(array(self::getInstance(), $name))){
			return call_user_func_array(array(self::getInstance(), $name), $arguments);
		}
		return self::getInstance();
	}
}
