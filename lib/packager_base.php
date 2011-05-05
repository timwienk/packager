<?php

require_once dirname(__FILE__) . '/spyc/spyc.php';

abstract class Packager_base {

	protected $processors = array();

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

	public static function write($file, $source){
		return file_put_contents($file, $source);
	}

	public static function warn($message){
		$std_err = fopen('php://stderr', 'w');
		fwrite($std_err, $message);
		fclose($std_err);
	}

	public function add_processor($fn){
		if (!in_array($fn, $this->processors)) $this->processors[] = $fn;
		return $this;
	}

	public function add_processors($fns){
		foreach ($fns as $fn){
			$this->add_processor($fn);
		}
		return $this;
	}

	public function remove_processor($fn){
		$key = array_search($fn, $this->processors);
		if ($key !== false) array_splice($this->processors, $key, 1);
		return $this;
	}

	public function remove_processors($fns){
		foreach ($fns as $fn){
			$this->remove_processor($fn);
		}
		return $this;
	}

	protected function process($source){
		foreach ($this->processors as $fn){
			$source = call_user_func($fn, $source);
		}
		return $source;
	}

	protected function parse_name($name, $default_package = null){
		if (!is_array($name)) $name = explode('/', $name);
		$exploded = array_slice(array_filter($name), 0);
		$length = count($exploded);

		if ($length == 1) return array($default_package, null, $exploded[0]);
		if ($length == 2) return array($exploded[0], null, $exploded[1]);
		if ($length == 3) return array($exploded[0], $exploded[1], $exploded[2]);

		trigger_error('Error parsing name: ' . $name, E_USER_ERROR);
	}

	protected function normalize_authors($manifest){
		if (!empty($manifest['authors'])) $authors = $manifest['authors'];
		elseif (!empty($manifest['author'])) $authors = $manifest['author'];
		else return array();

		return (is_array($authors)) ? $authors : array($authors);
	}

}
