<?php

require_once dirname(__FILE__) . '/packager_base.php';
require_once dirname(__FILE__) . '/source.php';

class Package extends Packager_base {

	public $name;
	public $version;
	public $exports;

	private $path;
	private $directory;
	private $manifest;

	private $paths;
	private $unparsed_paths;
	private $sources = array();
	private $sources_bu_paths = array();
	private $components = array();

	public function __construct($path){
		$this->set_path($path);
		$this->parse_manifest();
	}

	public function __call($method){
		if (strpos($method, 'get_') === 0){
			$property = substr($method, 4);
			return (empty($this->manifest[$property]) ? null : $this->manifest[$property]);
		}

		trigger_error('Call to undefined method: ' . get_class($this) . '::' . $method . '()', E_USER_ERROR);
	}

	# Sources:

	public function get_source($name){
		if (!array_key_exists($name, $this->sources)){
			$this->parse_sourcename($name);
		}

		return (array_key_exists($name, $this->sources) ? $this->sources[$name] : null);
	}

	public function get_sources($names = null){
		if (is_null($names)) return $this->get_sources_by_paths($this->paths);
		$sources = array();

		foreach ($names as $name){
			$source = $this->get_source($name);
			if ($source) $sources[] = $source;
		}

		return $sources;
	}

	public function get_source_by_path($path){
		if (!array_key_exists($path, $this->sources_by_paths)){
			$this->parse_sourcepath($path);
		}

		return (array_key_exists($path, $this->sources_by_paths) ? $this->sources_by_paths[$path] : null);
	}

	public function get_sources_by_paths($paths = null){
		$sources = array();

		foreach ($paths as $path){
			$source = $this->get_source_by_path($path);
			if ($source) $sources[] = $source;
		}

		return $sources;
	}

	public function get_source_by_component($name){
		$name = $this->parse_name($name);

		foreach ($this->paths as $path){
			$source = $this->get_source_by_path($path);
			if ($source->has_component($name)) return $source;
		}

		return null;
	}

	public function get_sources_by_components($names){
		$sources = array();

		foreach ($names as $name){
			$source = $this->get_source_by_component($name);
			if (!array_key_exists($source->name, $sources)) $sources[$source->name] = $source;
		}

		return array_values($sources);
	}

	public function has_source($source){
		return !!$this->get_source($source);
	}

	public function add_source($source){
		if (is_string($source)) {
			if (!in_array($source, $this->paths)) $source = new Source($source, $this);
		} elseif (!in_array($source->path, $this->paths)) {
			$source->set_package($this);
		}

		if (isset($source) && !array_key_exists($source->name, $this->sources)){
			$this->sources[$source->name] =& $source;
			$this->sources_by_paths[$source->path] =& $source;
		}

		return $this;
	}

	public function remove_source($source){
		if (is_string($source)) $source = $this->get_source($source);
		if (!$source) return $this;

		if (isset($source->path)){
			$key = array_search($source->path, $this->paths);
			if ($key !== false) array_splice($this->paths, $key, 1);
		}
		if (isset($this->components)){
			foreach ($source->provides as $component){
				$key = array_search($component, $this->components);
				if ($key !== false) array_splice($this->components, $key, 1);
			}
		}
		unset($this->sources[$source->name]);

		return $this;
	}

	# Components:

	public function get_components(){
		if (empty($this->components)) $this->parse_components();
		return $this->components;
	}

	public function has_component($name){
		return !!$this->get_source_by_component($name);
	}

	# Dependancies:

	public function resolve_source($source){
		$resolved_sources = array();

		foreach ($source->requires as $component){
			foreach ($this->resolve_component($component) as $resolved_source) {
				if (!in_array($resolved_source, $resolved_sources)) $resolved_sources[] = $resolved_source;
			}
		}
		if (!in_array($source, $resolved_sources)) $resolved_sources[] = $source;

		return $resolved_sources;
	}

	public function resolve_sources($sources){
		$resolved_sources = array();

		foreach ($sources as $source){
			if (!in_array($source, $resolved_sources)) {
				foreach ($this->resolve_source($resolved_source) as $resolved_source){
					if (!in_array($resolved_source, $resolved_sources)) $resolved_sources[] = $resolved_source;
				}
			}
		}

		return $resolved_sources;
	}

	public function resolve_component($component){
		$source = $this->get_source_by_component($component);
		if ($source){
			$resolved_sources = $this->resolve_source($source);
		} else {
			self::warn('Component not found: ' . $component);
			$resolved_sources = array();
		}
		return $resolved_sources;
	}

	public function resolve_components($components){
		$resolved_sources = array();

		foreach ($components as $component){
			$source = $this->get_source_by_component($component);
			if ($source){
				if (!in_array($source, $resolved_sources)){
					foreach ($this->resolve_source($resolved_source) as $resolved_source){
						if (!in_array($resolved_source, $resolved_sources)) $resolved_sources[] = $resolved_source;
					}
				}
			} else {
				self::warn('Component not found: ' . $component);
			}
		}

		return $resolved_sources;
	}

	# Build:

	public function validate($sources = array(), $components = array()){
		if (empty($sources) && empty($components)){
			$sources = $this->get_sources();
		}

		foreach ($components as $component){
			if ($this->has_component($component)){
				$source = $this->get_source_by_component($component);
				if (!in_array($source, $sources)) $sources[] = $source;
			} else {
				self::warn('Component not found: ' . $component);
			}
		}

		$sources = $this->resolve_sources($sources);

		foreach ($sources as $source){
			if (!$this->has_source($source)){
				self::warn('Source not found: ' . $source);
			}
		}
	}

	public function build($sources = array(), $components = array(), $blocks = array()){
		if (empty($sources) && empty($components)){
			$sources = $this->get_sources();
		}

		foreach ($this->get_sources_by_components($components) as $source){
			if (!in_array($source, $sources)) $sources[] = $source;
		}

		$sources = $this->resolve_sources($sources);

		if (empty($sources)) return '';

		$built = array();
		foreach ($sources as $source) $built[] = $source->build($blocks);

		$source = implode("\n\n", $built) . "\n";
		return $this->process($source);
	}

	public function build_from_sources($sources, $blocks = array()){
		return $this->build($sources, array(), $blocks);
	}

	public function build_from_components($components, $blocks = array()){
		return $this->build(array(), $components, $blocks);
	}

	# Internal:

	protected function parse_name($name){
		return parent::parse_name($name, $this->name);
	}

	protected function set_path($path){
		$pathinfo = pathinfo($path);

		if (is_dir($path)){
			$directory = $pathinfo['dirname'] . '/' . $pathinfo['basename'];

			switch ($directory){

				case file_exists($directory . '/package.yml'):
					$path = $directory . '/package.yml';
				break;

				case file_exists($directory . '/package.yaml'):
					$path = $directory . '/package.yaml';
				break;

				case file_exists($directory . '/package.json'):
					$path = $directory . '/package.json';
				break;

				default:
					trigger_error('No manifest found in: ' . $path, E_USER_ERROR);

			}
		} elseif (file_exists($path)){
			$directory = $pathinfo['dirname'];
			$path = $directory . '/' . $pathinfo['basename'];
		} else {
			trigger_error('Manifest not found: ' . $path, E_USER_ERROR);
		}

		$this->path = $path;
		$this->directory = $directory;
	}

	protected function parse_manifest(){
		$extension = pathinfo($this->path, PATHINFO_EXTENSION);

		switch ($extension){

			case 'json':
				$manifest = self::json_decode_file($this->path);
			break;

			case 'yml':
			case 'yaml':
				$manifest = self::yaml_decode_file($this->path);
			break;

			default:
				trigger_error('Could not determine manifest format: ' . $this->path, E_USER_ERROR);

		}

		if (empty($manifest)) trigger_error('Error parsing manifest: ' . $this->path, E_USER_ERROR);
		if (empty($manifest['sources'])) $manifest['sources'] = array();

		$authors = $this->normalize_authors($manifest);
		$manifest['authors'] = $authors;
		$manifest['author'] = implode(', ', $authors);
		$manifest['paths'] = $manifest['sources'];

		$this->manifest = $manifest;
		$this->name = $this->get_name();
		$this->version = $this->get_version();
		$this->exports = $this->get_exports();
		$this->paths = $this->get_paths();
		$this->unparsed_paths = $this->paths;
	}

	protected function parse_sourcename($name){
		while ($path = array_shift($this->unparsed_paths)){
			$this->add_source($this->directory . '/' . $path);
			if ($source->name == $name) break;
		}
	}

	protected function parse_sourcepath($path){
		while ($unparsed_path = array_shift($this->unparsed_paths)){
			$this->add_source($this->directory . '/' . $unparsed_path);
			if ($source->name == $name) break;
		}
	}

	protected function parse_components($source = null){
		if (!$source){
			$this->components = array();
			foreach ($this->get_sources() as $source){
				$this->parse_components($source);
			}
		} else {
			foreach ($source->provides as $component){
				if (!in_array($component, $this->components)) $this->components[] = $component;
			}
		}
	}

}
