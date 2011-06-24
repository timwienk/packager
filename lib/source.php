<?php

require_once dirname(__FILE__) . '/packager_base.php';

class Source extends Packager_base {

	public $name;
	public $provides;
	public $requires;

	const MANIFEST_REGEX = '/\/\*\s*^---(.*?)^(?:\.\.\.|---)\s*\*\//ms';
	const BLOCK_REGEX = '/(\/[\/*])\s*<%1>(.*?)<\/%1>(?:\s*\*\/)?/s';
	const NAME_REGEX = '/^.*\/|\.[^.]+$/';

	private $package;
	private $path;
	private $source;
	private $manifest;
	private $blocks;

	public function __construct($path, $package = null){
		$this->set_path($path);
		$this->set_package($package);
		$this->add_processor(array($this, 'replace_blocks'));
	}

	public function __call($method, $arguments){
		if (strpos($method, 'get_') === 0){
			$property = substr($method, 4);
			return (empty($this->manifest[$property]) ? null : $this->manifest[$property]);
		}

		trigger_error('Call to undefined method: ' . get_class($this) . '::' . $method . '()', E_USER_ERROR);
	}

	# Package:

	public function set_package($package){
		$this->package = $package;
		$this->parse_manifest();
		return $this;
	}

	# Components:

	public function has_component($name){
		$name = $this->parse_name($name);
		return in_array($name, $this->provides);
	}

	# Build:

	public function validate(){
		return true;
	}

	public function build($blocks = array()){
		$this->blocks = $blocks;
		$source = $this->process($this->source);
		$this->blocks = null;

		return $source;
	}

	# Internal:

	protected function parse_name($name){
		$default_package = ($this->package ? $this->package->name : null);
		return parent::parse_name($name, $default_package);
	}

	protected function set_path($path){
		if (!file_exists($path)) trigger_error('File not found: ' . $path, E_USER_ERROR);

		$this->path = $path;
		$this->source = file_get_contents($path);
	}

	protected function read_manifest(){
		$manifest = null;

		preg_match(self::MANIFEST_REGEX, $this->source, $matches);
		if (!empty($matches) && !empty($matches[1])) $manifest = self::yaml_decode($matches[1]);

		return $manifest;
	}

	protected function parse_manifest(){
		$manifest = $this->read_manifest();

		if (empty($manifest)) trigger_error('Error parsing manifest in: ' . $this->source, E_USER_ERROR);
		if (empty($manifest['name'])) $manifest['name'] = preg_replace(self::NAME_REGEX, '', $this->path);
		$manifest['path'] = $this->path;
		$manifest['provides'] = array_map(array($this, 'parse_name'), (array) $manifest['provides']);
		$manifest['requires'] = array_map(array($this, 'parse_name'), (array) $manifest['requires']);

		$authors = $this->normalize_authors($manifest);
		$manifest['authors'] = $authors;
		$manifest['author'] = implode(', ', $authors);

		$this->manifest = $manifest;
		$this->name = $this->get_name();
		$this->provides = (array) $this->get_provides();
		$this->requires = (array) $this->get_requires();
	}

	protected function replace_blocks($source){
		foreach ($this->blocks as $block){
			$regex = str_replace('%1', $block, self::BLOCK_REGEX);
			$source = preg_replace_callback($regex, array($this, 'block_replacement'), $source);
		}
		return $source;
	}

	private function block_replacement($matches){
		return (strpos($matches[2], ($matches[1] == '//') ? "\n" : '*/') === false) ? $matches[2] : '';
	}

}
