<?php

require_once dirname(__FILE__) . '/spyc/spyc.php';

abstract class Packager_base {

	public static function yaml_decode($string){
		return Spyc::YAMLLoadString($string);
	}

	public static function yaml_decode_file($file){
		return Spyc::YAMLLoad($file);
	}

	public static function yaml_encode($object){
		return Spyc::YAMLDump($object);
	}

	public static function json_decode($string){
		return json_decode($string, true);
	}

	public static function json_decode_file($file){
		return json_decode(file_get_contents($file), true);
	}

	public static function json_encode($object){
		return json_encode($object);
	}

	public static function warn($message){
		$std_err = fopen('php://stderr', 'w');
		fwrite($std_err, $message);
		fclose($std_err);
	}

	public static function array_get($array, $key){
		return (!empty($array) && array_key_exists($key, $array)) ? $array[$key] : null;
	}

	protected function parse_name($name, $default){
		$exploded = array_slice(array_filter(explode('/', $name)), 0);
		$length = count($exploded);

		if ($length == 1) return array($default, $exploded[0]);
		if ($length == 2) return array($exploded[0], $exploded[1]);
		if ($length == 3) return array($exploded[0], $exploded[2]);

		trigger_error('Error parsing name: ' . $name, E_USER_ERROR);
	}

	protected function normalize_authors($manifest){
		if (!empty($manifest['authors'])) $authors = $manifest['authors'];
		elseif (!empty($manifest['author'])) $authors = $manifest['author'];
		else return array();

		return (is_array($authors)) ? $authors : array($authors);
	}

}
