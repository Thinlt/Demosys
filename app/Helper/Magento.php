<?php
/*Read and build configurations from file setting.ini or get default value*/
namespace App\Helper;

//Api class
class Magento {
	
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
	
	/**
	* get database infomation in magento config file
	*/
	public function getDatabaseInfo($root_dir, $magento_ver = '2'){
		$root_dir = rtrim($root_dir, '\/\\');
		if($magento_ver == '2'){
			if(file_exists($root_dir.'/app/etc/env.php')){
				$env = require $root_dir.'/app/etc/env.php';
				if(isset($env['db']['connection']['default'])){
					return array(
						'host' => $env['db']['connection']['default']['host'],
						'dbname' => $env['db']['connection']['default']['dbname'],
						'username' => $env['db']['connection']['default']['username'],
						'password' => $env['db']['connection']['default']['password']
					);
				}
			}
		}else{
			
		}
		return array();
	}
	
	/**
	* change database infomation in magento config file
	* $db_infos = array('host', 'dbname', 'username', 'password')
	*/
	public function changeDatabaseInfo($root_dir, $db_infos = array(), $magento_ver = '2'){
		$root_dir = rtrim($root_dir, '\/\\');
		$file = $root_dir.'/app/etc/env.php';
		if($magento_ver == '2'){
			if(file_exists($file)){
				$env = require $file;
				if(isset($env['db'])){
					$env = array_replace_recursive($env, array(
						'db'=>array('connection'=>array('default'=>$db_infos))
					));
					$content = '<?php' . PHP_EOL . 'return ' . var_export($env, true).';'. PHP_EOL;
					file_put_contents($file, $content);
					return true;
				}
			}else{
				throw new \Exception('The file '.$root_dir.'/app/etc/env.php doesn\'t exists.');
			}
		}else{
			
		}
		return false;
	}

	/**
	 * @param $root_dir path to magento site
	 * @param string $magento_ver magento version
	 * @return config data array
	 * @throws \Exception
	 */
	public function readConfigFile($root_dir, $magento_ver = '2'){
		$root_dir = rtrim($root_dir, '\/\\');
		$config_data = array();
		if($magento_ver == '2'){
			$file = $root_dir.'/app/etc/config.php';
			if(file_exists($file)){
				$env = require $file;
				if(is_array($env)){
					$config_data = $env;
				}
			}else{
				throw new \Exception('The file '.$root_dir.'/app/etc/config.php doesn\'t exists.');
			}
		}else{

		}
		return $config_data;
	}

	/**
	 * @param $root_dir path to magento site
	 * @param $data all data of config file to write
	 * @param string $magento_ver magento version
	 * @return boolean
	 * @throws \Exception
	 */
	public function writeConfigFile($root_dir, $data, $magento_ver = '2'){
		$root_dir = rtrim($root_dir, '\/\\');
		if($magento_ver == '2'){
			$file = $root_dir.'/app/etc/config.php';
			if(file_exists($file)){
				$env = require $file;
				if(is_array($data)){
					$env = array_replace_recursive($env, $data);
					$content = '<?php' . PHP_EOL . 'return ' . var_export($env, true).';'. PHP_EOL;
					file_put_contents($file, $content);
					return true;
				}
			}else{
				throw new \Exception('The file '.$root_dir.'/app/etc/config.php doesn\'t exists.');
			}
		}else{

		}
		return false;
	}
}
