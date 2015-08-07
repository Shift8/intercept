<?php
/**
 * Borrowed from Lithium: the most rad php framework
 * http://li3.me
 *
 * @copyright     Copyright 2015, Shift8Creative (http://www.shift8creative.com)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace Shift8\Intercept;

use Shift8\Intercept\Filters;
use Closure;

/**
 * This is the base class which you will want to extend from. It defines a few conventions. 
 * the same found in the Lithium framework's base classes.
 *
 * There is also the StaticObject class should you want a static class instead.
 *
 * - **Universal constructor**: Any class which defines a `__construct()` method should take
 *   exactly one parameter (`$config`), and that parameter should always be an array. Any settings
 *   passed to the constructor will be stored in the `$_config` property of the object.
 *   
 * - **Initialization / automatic configuration**: After the constructor, the `_init()` method is
 *   called. This method can be used to initialize the object, keeping complex logic and
 *   high-overhead or difficult to test operations out of the constructor. This method is called
 *   automatically by `Object::__construct()`, but may be disabled by passing `'init' => false` to
 *   the constructor. The initializer is also used for automatically assigning object properties.
 *   See the documentation on the `_init()` method for more details.
 *   
 * - **Filters**: The `Object` class implements two methods which allow an object to easily
 *   implement filterable methods. The `_filter()` method allows methods to be implemented as
 *   filterable, and the `applyFilter()` method allows filters to be wrapped around them.
 *   
 * - **Testing / misc.**: The `__set_state()` method provides a default implementation of the PHP
 *   magic method (works with `var_export()`) which can instantiate an object with a static method
 *   call. Finally, the `_stop()` method may be used instead of `exit()`, as it can be overridden
 *   for testing purposes.
 *
 * @see Shift8\Intercept\StaticObject
 */
class Object {

	/**
	 * Stores configuration information for object instances at time of construction.
	 * **Do not override.** Pass any additional variables to `parent::__construct()`.
	 *
	 * @var array
	 */
	protected $_config = array();

	/**
	 * Holds an array of values that should be processed on initialization. Each value should have
	 * a matching protected property (prefixed with `_`) defined in the class. If the property is
	 * an array, the property name should be the key and the value should be `'merge'`. See the
	 * `_init()` method for more details.
	 *
	 * @see Shift8\Intercept\Object::_init()
	 * @var array
	 */
	protected $_autoConfig = array();

	/**
	 * Contains a 2-dimensional array of filters applied to this object's methods, indexed by method
	 * name. See the associated methods for more details.
	 *
	 * @see Shift8\Intercept\Object::_filter()
	 * @see Shift8\Intercept\Object::applyFilter()
	 * @var array
	 */
	protected $_methodFilters = array();

	/**
	 * Parents of the current class.
	 *
	 * @see Shift8\Intercept\Object::_parents()
	 * @var array
	 */
	protected static $_parents = array();

	/**
	 * Initializes class configuration (`$_config`), and assigns object properties using the
	 * `_init()` method, unless otherwise specified by configuration. See below for details.
	 *
	 * In any class that extends this class, feel free to redefine `__construct()` and pass
	 * other arguments (maybe your dependency injection container even). Though the convention 
	 * Intercept uses is an array of config options. Also sometimes called a unified constructor.
	 *
	 * @see Shift8\Intercept\Object::$_config
	 * @see Shift8\Intercept\Object::_init()
	 * @param array $config The configuration options which will be assigned to the `$_config`
	 *              property. This method accepts one configuration option:
	 *              - `'init'` _boolean_: Controls constructor behavior for calling the `_init()`
	 *                method. If `false`, the method is not called, otherwise it is. Defaults to
	 *                `true`.
	 */
	public function __construct(array $config = array()) {
		$defaults = array('init' => true);
		$this->_config = $config + $defaults;

		if ($this->_config['init']) {
			$this->_init();
		}
	}

	/**
	 * Initializer function called by the constructor unless the constructor `'init'` flag is set
	 * to `false`. May be used for testing purposes, where objects need to be manipulated in an
	 * un-initialized state, or for high-overhead operations that require more control than the
	 * constructor provides. Additionally, this method iterates over the `$_autoConfig` property
	 * to automatically assign configuration settings to their corresponding properties.
	 *
	 * For example, given the following: {{{
	 * class Bar extends \Shift8\Intercept\Object {
	 * 	protected $_autoConfig = array('foo');
	 * 	protected $_foo;
	 * }
	 *
	 * $instance = new Bar(array('foo' => 'value'));
	 * }}}
	 *
	 * The `$_foo` property of `$instance` would automatically be set to `'value'`. If `$_foo` was
	 * an array, `$_autoConfig` could be set to `array('foo' => 'merge')`, and the constructor value
	 * of `'foo'` would be merged with the default value of `$_foo` and assigned to it.
	 *
	 * @see Shift8\Intercept\Object::$_autoConfig
	 * @return void
	 */
	protected function _init() {
		foreach ($this->_autoConfig as $key => $flag) {
			if (!isset($this->_config[$key]) && !isset($this->_config[$flag])) {
				continue;
			}

			if ($flag === 'merge') {
				$this->{"_{$key}"} = $this->_config[$key] + $this->{"_{$key}"};
			} else {
				$this->{"_$flag"} = $this->_config[$flag];
			}
		}
	}

	/**
	 * Apply a closure to a method of the current object instance.
	 * Filters can be applied at specific position(s) based on the options passed.
	 * They can also be named so that they are easier to find and work with 
	 * throughout the codebase.
	 *
	 * @param array $options
	 *        mixed  `method`  The name of the method to apply the closure to. Can either be a single
	 *        				   method name as a string, or an array of method names. Can also be false 
	 *        				   to remove all filters on the current object (required).
	 *        				  
	 *        int    `at`	  The exact position in the filter chain to apply this filter (optional).
	 *        string `name`   The name of the filter being applied (optional).
	 *        string `before` The name of the filter to apply this filter before (optional).
	 *        
	 *        bool   `applyIfPositionNotFound` If the `before` or `after` filter key/name was not found, the
	 *        								   filter will still be applied at the end or beginning (optional).
	 *        								   This is true by default because it assumes it's important to apply
	 *        								   the filter.
	 * 
	 * @param Closure $filter The closure that is used to filter the method(s), can also be false
	 *                        to remove all the current filters for the given method.
	 * @return void
	 */
	public function applyFilter(array $options = array(), $filter = null) {
		$defaults = array(
			'method' => null,
			'name' => null,
			'at' => null,
			'before' => null,
			'after' => null,
			'applyIfPositionNotFound' => true
		);
		$options += $defaults;
		extract($options);

		if($method === false) {
			$this->_methodFilters = array();
			return;
		}
		foreach((array) $method as $m) {
			if(!isset($this->_methodFilters[$m]) || $filter === false) {
				$this->_methodFilters[$m] = array();
			}
			if($filter !== false) {
				// If applying at a specific position.
				if($before || $after || $at) {
					$insertAtPosition = function($methodFilters, $insertPosition) use($filter, $name, $m) {
						// slice the array and append after the first part, then append the second part of the original array.
						if($name) {
							$res = array_slice($methodFilters[$m], 0, $insertPosition, true) + array($name => $filter) + array_slice($methodFilters[$m], $insertPosition, count($methodFilters[$m])-$insertPosition, true);
						} else {
							$res = array_slice($methodFilters[$m], 0, $insertPosition, true) + array($filter) + array_slice($methodFilters[$m], $insertPosition, count($methodFilters[$m])-$insertPosition, true);
						}
						$methodFilters[$m] = $res;

						return $methodFilters;
					};

					// Note: It is possible to apply multiple times if `before` `after` and `at` are all defined.
					if($before) {
						$i=0;
						// If the key/name was not found, insert before everything as a best guess.
						$insertPosition = ($applyIfPositionNotFound) ? 0:false;
						foreach($this->_methodFilters[$m] as $existingFilterName => $v) {
							if($existingFilterName === $before) {
								$insertPosition = $i;
							}
							$i++;
						}
						// If the position was found (or set to insert anyway if not found).
						if($insertPosition !== false) {
							$this->_methodFilters = $insertAtPosition($this->_methodFilters, $insertPosition);
						}
					}

					if($after) {
						$i=0;
						// If the key/name was not found, insert after everything a best guess...Unless told not to insert if the filter position was not found.
						$insertPosition = ($applyIfPositionNotFound) ? count($this->_methodFilters[$m]):false;
						foreach($this->_methodFilters[$m] as $existingFilterName => $v) {
							if($existingFilterName === $after) {
								// "after" is just a position ahead of the found filter.
								$insertPosition = $i+1;
								$found = true;
							}
							$i++;
						}
						// If the position was found (or set to insert anyway if not found).
						if($insertPosition !== false) {
							$this->_methodFilters = $insertAtPosition($this->_methodFilters, $insertPosition);
						}
					}

					if(is_int($at)) {
						$this->_methodFilters = $insertAtPosition($this->_methodFilters, $at);
					}
				} else {
					// Apply normally, push onto the chain last in first out.
					if($name) {
						$this->_methodFilters[$m][$name] = $filter;
					} else {
						$this->_methodFilters[$m][] = $filter;
					}
				}	
			}
		}
	}

	/**
	 * Returns an applied filter for a given method or false if not found.
	 * 
	 * @param  string $method The filtered method
	 * @param  string $name   The filter name or key
	 * @return mixed          Closure or false if not found
	 */
	public function getFilter($method = null, $name = null) {
		if($name === null || !$method) {
			return false;
		}

		foreach($this->_methodFilters[$method] as $k => $v) {
			if($k === $name) {
				return $v;
			}
		}
		return false;
	}

	/**
	 * Returns all filters applied to a given method or an empty array if there are none.
	 * 
	 * @param  string $method The method name
	 * @return array          The applied filters (an array of closures)
	 */
	public function getFilters($method = null) {
		if($method) {
			return $this->_methodFilters[$method];
		}
		return array();
	}

	/**
	 * Removes an applied filter for a given method.
	 * To remove all applied filters, call `applyFilter()` with the `$options`
	 * `method` key value as false.
	 *
	 * TODO: Think about multiple methods like applyFilter() ... but then return void... Which I don't know if I like...
	 * I like the feedback of what happened.
	 * 
	 * @param  string $method The method name
	 * @param  string $name   The filter name or key
	 * @return boolean        If successful
	 */
	public function removeFilter($method = null, $name = null) {
		if($name === null || !$method) {
			return false;
		}

		foreach($this->_methodFilters[$method] as $k => $v) {
			if($k === $name) {
				unset($this->_methodFilters[$method][$k]);
				return true;
			}
		}
		return false;
	}

	/**
	 * Replaces an existing applied filter for a given method.
	 * TODO: Think about multiple methods like applyFilter() ... but then return void... Which I don't know if I like...
	 * I like the feedback of what happened.
	 * 
	 * @param  string  $method The method name
	 * @param  string  $name   The existing filter key or name
	 * @param  Clsoure $filter The closure that is used to filter the method(s)
	 * @return boolean         If successful (if the filter wasn't found it won't be replaced)
	 */
	public function replaceFilter($method = null, $name = null, $filter = null) {
		if($name === null || !$method) {
			return false;
		}

		foreach($this->_methodFilters[$method] as $k => $v) {
			if($k === $name) {
				$this->_methodFilters[$method][$name] = $filter;
				return true;
			}
		}
		return false;
	}


	/**
	 * Calls a method on this object with the given parameters. Provides an OO wrapper
	 * for call_user_func_array, and improves performance by using straight method calls
	 * in most cases.
	 *
	 * @param string $method  Name of the method to call
	 * @param array $params  Parameter list to use when calling $method
	 * @return mixed  Returns the result of the method call
	 */
	public function invokeMethod($method, $params = array()) {
		switch (count($params)) {
			case 0:
				return $this->{$method}();
			case 1:
				return $this->{$method}($params[0]);
			case 2:
				return $this->{$method}($params[0], $params[1]);
			case 3:
				return $this->{$method}($params[0], $params[1], $params[2]);
			case 4:
				return $this->{$method}($params[0], $params[1], $params[2], $params[3]);
			case 5:
				return $this->{$method}($params[0], $params[1], $params[2], $params[3], $params[4]);
			default:
				return call_user_func_array(array(&$this, $method), $params);
		}
	}

	/**
	 * PHP magic method used in conjunction with `var_export()` to allow objects to be
	 * re-instantiated with their pre-existing properties and values intact. This method can be
	 * called statically on any class that extends `Object` to return an instance of it.
	 *
	 * @param array $data An array of properties and values with which to re-instantiate the object.
	 *        These properties can be both public and protected.
	 * @return object Returns an instance of the requested object with the given properties set.
	 */
	public static function __set_state($data) {
		$class = get_called_class();
		$object = new $class();

		foreach ($data as $property => $value) {
			$object->{$property} = $value;
		}
		return $object;
	}

	/**
	 * Executes a set of filters against a method by taking a method's main implementation as 
	 * a callback, and iteratively wrapping the filters around it. This system allows you to 
	 * "reach into" an object's methods which are marked as _filterable_, and intercept calls 
	 * to those methods, optionally modifying parameters or return values.
	 *
	 * @see Shift8\Intercept\Object::applyFilter()
	 * @see Shift8\Intercept\Filters
	 * @param string $method The name of the method being executed, usually the value of
	 *               `__METHOD__`.
	 * @param array $params An associative array containing all the parameters passed into
	 *              the method.
	 * @param Closure $callback The method's implementation, wrapped in a closure.
	 * @param array $filters Additional filters to apply to the method for this call only.
	 * @return mixed Returns the return value of `$callback`, modified by any filters passed in
	 *         `$filters` or applied with `applyFilter()`.
	 */
	protected function _filter($method, $params, $callback, $filters = array()) {
		list($class, $method) = explode('::', $method);

		if (empty($this->_methodFilters[$method]) && empty($filters)) {
			return $callback($this, $params, null);
		}

		$f = isset($this->_methodFilters[$method]) ? $this->_methodFilters[$method] : array();
		$data = array_merge($f, $filters, array($callback));
		return Filters::run($this, $params, compact('data', 'class', 'method'));
	}

	/**
	 * Gets and caches an array of the parent methods of a class.
	 *
	 * @return array Returns an array of parent classes for the current class.
	 */
	protected static function _parents() {
		$class = get_called_class();

		if (!isset(self::$_parents[$class])) {
			self::$_parents[$class] = class_parents($class);
		}
		return self::$_parents[$class];
	}

	/**
	 * Exit immediately. Primarily used for overrides during testing.
	 *
	 * @param integer|string $status integer range 0 to 254, string printed on exit
	 * @return void
	 */
	protected function _stop($status = 0) {
		exit($status);
	}

}
?>