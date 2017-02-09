<?php
/*Read and build configurations from file config.ini or get default value*/

$commands = require BP.'/app/commandList.php';
$config_file = __DIR__ . '/../config.ini';

if(!file_exists($config_file)){
	echo 'The file '. basename($config_file) .' does not existed or cannot readable permission, please check in '. BP;
	echo "\n\n";
	exit(1);
}

//all config variables
$conf_vars = array(
	'commands' => $commands
); 

//read line
$handle = fopen($config_file, "r");
if ($handle) {
	
    while (($line = fgets($handle)) !== false) {
		
        // process the line read.
		if(strpos(trim($line), '#') === 0){
			continue; //ignore comment line
		}
		
		//pare variable and value
		if($line != ''){			
			if(($split_pos = strpos($line, '=')) > 0){
				$var_name = trim(substr($line, 0, $split_pos), "\"' \t\n\r\0\x0B");//variable name
				$var_value = trim(substr($line, $split_pos + 1), "\"' \t\n\r\0\x0B");//variable name
				$conf_vars[$var_name] = $var_value;	
			}
		}
    }
	
	extract($conf_vars); //extract variables to global
	
    fclose($handle);
} else {
    // error opening the file.
	echo 'The file '. basename($config_file) .' opening error';
	echo "\n\n";
	exit(1);
}

final class Config{
	
	public static function getAll(){
		global $conf_vars;
		return $conf_vars;
	}
	
	/*
	* get config by name
	* 
	* parameter $name is string name of config variable
	* 
	* return value
	*/
	public static function get($name){
		global $conf_vars;
		if(isset($conf_vars[$name])){
			return $conf_vars[$name];
		}
		return null;
	}
	
	/*
	* set config value by name
	* 
	* parameter $name is string name of config variable
	* 
	* parameter $value is value of config variable
	*/
	public static function set($name, $value){
		global $conf_vars;
		$conf_vars[$name] = $value;
		return $value;
	}
}

