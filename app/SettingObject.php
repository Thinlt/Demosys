<?php
/*Read and build configurations from file setting.ini or get default value*/
namespace App;

use Composer\Semver\Comparator;

class SettingObject {
	
	protected $_settings = array();
	protected $_handle; //file setting handle
	protected $_file; //the file saved config
	
	public function __construct(){
		$this->_file = BP.'/settings.ini';
		$config_file = $this->_file;
		if(!file_exists($config_file)){
			throw new \Exception('The file '. basename($config_file) .' does not existed or cannot readable permission, please check in '. BP);
		}
		$setting_content = '';
		//read line
		$this->_handle = fopen($config_file, "r+");
		if ($this->_handle) {
			while (($line = fgets($this->_handle)) !== false) {
				// process the line read.
				if(strpos(trim($line), '#') === 0){
					continue; //ignore comment line
				}
				//connect string content
				if($line != ''){			
					$setting_content .= $line;
				}
			}
			fclose($this->_handle); //destroy handle file opening
			
			$this->_settings = json_decode($setting_content, true); //converted into associative arrays
			if($setting_content != '' && $this->_settings == null && ($json_error = json_last_error()) != JSON_ERROR_NONE){
				switch ($json_error) {
					case JSON_ERROR_NONE:
						$json_error = ''; // JSON is valid // No error has occurred
						break;
					case JSON_ERROR_DEPTH:
						$json_error = 'The maximum stack depth has been exceeded.';
						break;
					case JSON_ERROR_STATE_MISMATCH:
						$json_error = 'Invalid or malformed JSON.';
						break;
					case JSON_ERROR_CTRL_CHAR:
						$json_error = 'Control character error, possibly incorrectly encoded.';
						break;
					case JSON_ERROR_SYNTAX:
						$json_error = 'Syntax error, malformed JSON.';
						break;
					// PHP >= 5.3.3
					case JSON_ERROR_UTF8:
						$json_error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
						break;
					// PHP >= 5.5.0
					case JSON_ERROR_RECURSION:
						$json_error = 'One or more recursive references in the value to be encoded.';
						break;
					// PHP >= 5.5.0
					case JSON_ERROR_INF_OR_NAN:
						$json_error = 'One or more NAN or INF values in the value to be encoded.';
						break;
					case JSON_ERROR_UNSUPPORTED_TYPE:
						$json_error = 'A value of a type that cannot be encoded was given.';
						break;
					default:
						$json_error = 'Unknown JSON error occured.';
						break;
				}
				throw new \Exception($json_error.PHP_EOL);
			}
		} else {
			// error opening the file.
			throw new \Exception('The file '. basename($config_file) .' opening error');
		}
	}
	
	/**
	* return array of settings
	*/
	public function getValues(){
		return $this->_settings;
	}
	
	/**
	* set value to settings
	* param $path is string as a path of array key /key1/key2/
	* param $value mixed string|array of values
	*/	
	public function set($path, $value){
		$keys = explode('/', trim($path, '\/\\'));
		$settings = &$this->_settings;
		$last_key = end($keys);
		foreach($keys as $key){
			if(!isset($settings[(string)$key])) $settings = array((string)$key => []); //define it

			if($last_key == $key){
				$settings[(string)$key] = $value;
				//write to begining of setting file
				//fseek($this->_handle, 0); //seek to begining
				//fwrite($this->_handle, json_encode($this->_settings, JSON_PRETTY_PRINT));
				file_put_contents($this->_file, str_replace('\/', '/', json_encode($this->_settings, JSON_PRETTY_PRINT)));
				return $this;
			}

			$settings = &$settings[(string)$key];
		}
		return $this;
	}
	
	
	/*
	* get config by name
	* 
	* parameter $path is string of array setting path
	* path is parent/child
	* return value
	*/
	public function get($path){
		$keys = explode('/', $path);
		$value = $this->_settings;
		if(!is_array($value)){
			return null;
		}
		foreach($keys as $key){
			if(!isset($value[$key])){
				return '';
			}
			$value = $value[$key];
		}
		return $value;
	}
	
	/*
	* remove a item
	* 
	* parameter $path is string of array setting path
	* 
	* return value
	*/
	public function remove($path){
		$keys = explode('/', $path);
		$settings = &$this->_settings;
		$last_key = end($keys);
		foreach($keys as $key){
			if($last_key == $key){
				if(isset($settings[$key])){
					unset($settings[$key]);
					//write to begining of setting file
					//fseek($this->_handle, 0); //seek to begining
					//fwrite($this->_handle, json_encode($this->_settings, JSON_PRETTY_PRINT));
					file_put_contents($this->_file, json_encode($this->_settings, JSON_PRETTY_PRINT));
				}
				return $this;
			}
			if(!isset($settings[$key])){
				return $this;
			}
			$settings = &$settings[$key];
		}
		return $this;
	}
	
	/*
	* Alias of remove() method 
	* remove a item
	* 
	* parameter $path is string of array setting path
	* 
	* return value
	*/
	public function del($path){
		return $this->remove($path);
	}
	
	/**
	* update composer file
	* param $file is string path of composer file
	* return true if write success or false if not success
	*/
	public function updateComposer($file){
		if(!file_exists($file)) throw new \Exception('The file '.$file.' is not exists');
		//get version from composer file
		$composer_content = file_get_contents($file);
		$composer = json_decode($composer_content, true);
		$versions = $this->get('magento/update_composer/versions'); //all update json
		if(!isset($composer['version'])){
			$composer['version'] = current(array_keys($versions));//get composer version is the key of the first setting versions
		}
		$setting_versions = array_keys($versions);
		
		//compare version
		$biggest = $composer['version'];
		foreach($setting_versions as $ver){
			if(Comparator::greaterThanOrEqualTo($ver, $biggest)){
				$biggest = $ver;
			}
		}
		//check exists in version setting
		if(!in_array($biggest, $setting_versions)){
			$biggest = $setting_versions[0]; //get first version key in array
		}
		//get update composer json
		$update = $versions[$biggest];//$this->get('magento/update_composer/versions/'.$biggest);
		
		//update
		$new_composer = array_replace_recursive($composer, $update);
		//write new update composer file
		$_handle = fopen($file, "w");
		if ($_handle) {
			fwrite($_handle, str_replace('\/', '/', json_encode($new_composer, JSON_PRETTY_PRINT)));
			fclose($_handle);
			return true;
		}
		return false;
	}
	
	
	/* destruct function */
	public function __destruct(){
		//fclose($this->_handle); //destroy handle file opening 
	}
}