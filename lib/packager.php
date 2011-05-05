<?php

require_once dirname(__FILE__) . '/packager_base.php';
require_once dirname(__FILE__) . '/package.php';

class Packager extends Packager_base {

	private $paths = array();
	private $packages = array();

	public function __construct($paths){
		foreach ((array) $paths as $path) {
			$package = new Package($path);
			$packages[$package->name] = $package;
		}
	}

	# Packages:

	public function get_package($name){
		return (array_key_exists($name, $this->packages) ? $this->packages[$name] : null);
	}

	public function get_packages($names = null){
		if (is_null($names)) return array_values($this->packages);

		$packages = array();
		foreach ($names as $name){
			if (array_key_exists($name, $this->packages)) $packages[] = $this->packages[$name][0];
		}
		return $packages;
	}

	public function has_package($package, $version = null){
		if (is_string($package)){
			return (array_key_exists($package, $this->packages) && (is_null($version) || array_key_exists($version, $this->packages[$package])));
		} else {
			if (array_key_exists($package->name, $this->packages)){
				if (in_array($package, $this->packages[$package->name])) return true;
				return ($package->version && array_key_exists($package->version, $this->packages[$package->name]));
			}
		}
	}

	public function add_package($package){
		if (is_string($package) && !in_array($package, $this->paths)){
			$package = new Package($package);
		}

		if (array_key_exists($package->name, $this->packages)){
			if (!$package->version){
				self::warn('Unable to add package with existing name, without version information: ' . $path);
			}
			if (array_key_exists($package->version, $this->packages[$package->name])){
				self::warn('Package of same version already in package list: ' . $path);
			}
		} else {
			$this->packages[$package->name] = array($package);
		}
		if ($package->version) $this->packages[$package->name][$package->version] = $package;

		return $this;
	}

	public function remove_package($package, $version = null){
		if (is_string($package)){
			if (is_null($version)) {
				unset($this->packages[$package]);
			} elseif (array_key_exists($package, $this->packages)){
				$versions =& $this->packages[$package];
				foreach ($versions as $key => $pkg){
					if ($pkg->name == $package && $pkg->version == $version){
						unset($versions[$package][$key]);
					}
				}
			}
		} else {
			if (array_key_exists($package->name, $this->packages)){
				$versions =& $this->packages[$package->name];
				foreach ($versions as $key => $pkg){
					if ($pkg == $package || isset($package->version) && $pkg->version == $package->version){
						unset($versions[$key]);
					}
				}
			}
		}

		if (empty($this->packages[$package->name])){
			unset($this->packages[$package->name]);
		} elseif (!array_key_exists(0, $versions)){
			foreach ($versions as $pkg){
				$versions[0] = $pkg;
				break;
			}
		}

		return $this;
	}

	# Sources:

	public function get_source($name){
		list($package, $version, $name) = $this->parse_name($name);
		if ($package) $package = $this->get_package($package, $version);

		if ($package){
			return $package->get_source($name);
		} else {
			foreach ($this->get_packages() as $package){
				$source = $package->get_source($name);
				if ($source) return $source;
			}
		}

		return null;
	}

	public function get_sources($names = null){
		$sources = array();

		if (is_null($names)){
			foreach ($this->get_packages() as $package) array_merge($sources, $package->get_sources());
		} else {
			foreach ($names as $name){
				$source = $this->get_source($name);
				if ($source) $sources[] = $source;
			}
		}

		return $sources;
	}

	public function get_source_by_path($path){
		foreach ($this->get_packages() as $package){
			$source = $package->get_source_by_path($path);
			if ($source) return $source;
		}
		return null;
	}

	public function get_sources_by_paths($paths = null){
		$sources = array();

		foreach ($paths as $path){
			$source = $this->get_source_by_path($path);
			if ($source) $sources[] = $source;
		}

		return $sources;
	}

	public function has_source($name){
		foreach ($this->packages as $package){
			if ($package->has_source($name)) return true;
		}
		return false;
	}

	# Components:

	public function get_components(){
		$components = array();
		foreach ($this->packages as $package){
			$components = array_merge($package->get_components(), $components);
		}
		return $components;
	}

	public function has_component($name){
		foreach ($this->packages as $package){
			if ($package->has_component($name)) return true;
		}
		return false;
	}

	# Dependancies:

	public function resolve_source(){
	}

	public function resolve_sources(){
	}

	public function resolve_dependancy(){
	}

	public function resolve_dependancies(){
	}

	# Build:

	public function validate($packages = array(), $sources = array(), $components = array()){
	}

	public function build($packages = array(), $sources = array(), $components = array(), $blocks = array()){
	}

	public function build_from_sources($sources, $blocks = array()){
		return $this->build(array(), $sources, array(), $blocks);
	}

	public function build_from_components($components, $blocks = array()){
		return $this->build(array(), array(), $components, $blocks);
	}

}
