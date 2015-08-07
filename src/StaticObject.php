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
 * Provides a base class for all static classes. Similar to its counterpart, the `Object` class, 
 * `StaticObject` defines some utility methods for working with the filters system, and methods 
 * useful for testing purposes.
 *
 * @see Shift8\Intercept\Object
 */
class StaticObject {

	/**
	 * Stores the closures that represent the method filters. They are indexed by called class.
	 *
	 * @var array Method filters, indexed by `get_called_class()`.
	 */
	protected static $_methodFilters = array();

	/**
	 * Keeps a cached list of each class' inheritance tree.
	 *
	 * @var array
	 */
	protected static $_parents = array();

	/**
	 * Apply a closure to a method of the current static object.
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
	public static function applyFilter(array $options = array(), $filter = null) {
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

		$class = get_called_class();
		if ($method === false) {
			static::$_methodFilters[$class] = array();
			return;
		}
		foreach ((array) $method as $m) {
			if (!isset(static::$_methodFilters[$class][$m]) || $filter === false) {
				static::$_methodFilters[$class][$m] = array();
			}
			if ($filter !== false) {
				// static::$_methodFilters[$class][$m][] = $filter; // original

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
						foreach(static::$_methodFilters[$class][$m] as $existingFilterName => $v) {
							if($existingFilterName === $before) {
								$insertPosition = $i;
							}
							$i++;
						}
						// If the position was found (or set to insert anyway if not found).
						if($insertPosition !== false) {
							static::$_methodFilters[$class] = $insertAtPosition(static::$_methodFilters[$class], $insertPosition);
						}
					}

					if($after) {
						$i=0;
						// If the key/name was not found, insert after everything a best guess...Unless told not to insert if the filter position was not found.
						$insertPosition = ($applyIfPositionNotFound) ? count(static::$_methodFilters[$class][$m]):false;
						foreach(static::$_methodFilters[$class][$m] as $existingFilterName => $v) {
							if($existingFilterName === $after) {
								// "after" is just a position ahead of the found filter.
								$insertPosition = $i+1;
								$found = true;
							}
							$i++;
						}
						// If the position was found (or set to insert anyway if not found).
						if($insertPosition !== false) {
							static::$_methodFilters[$class] = $insertAtPosition(static::$_methodFilters[$class], $insertPosition);
						}
					}

					if(is_int($at)) {
						static::$_methodFilters[$class] = $insertAtPosition(static::$_methodFilters[$class], $at);
					}
				} else {
					// Apply normally, push onto the chain last in first out.
					if($name) {
						static::$_methodFilters[$class][$m][$name] = $filter;
					} else {
						static::$_methodFilters[$class][$m][] = $filter;
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
	public static function getFilter($method = null, $name = null) {
		if($name === null || !$method) {
			return false;
		}
		$class = get_called_class();

		foreach(static::$_methodFilters[$class][$method] as $k => $v) {
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
	public static function getFilters($method = null) {
		if($method) {
			$class = get_called_class();
			return static::$_methodFilters[$class][$method];
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
	public static function removeFilter($method = null, $name = null) {
		if($name === null || !$method) {
			return false;
		}
		$class = get_called_class();

		foreach(static::$_methodFilters[$class][$method] as $k => $v) {
			if($k === $name) {
				unset(static::$_methodFilters[$class][$method][$k]);
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
	public static function replaceFilter($method = null, $name = null, $filter = null) {
		if($name === null || !$method) {
			return false;
		}
		$class = get_called_class();

		foreach(static::$_methodFilters[$class][$method] as $k => $v) {
			if($k === $name) {
				static::$_methodFilters[$class][$method][$name] = $filter;
				return true;
			}
		}
		return false;
	}

	/**
	 * Calls a method on this object with the given parameters. Provides an OO wrapper for
	 * `forward_static_call_array()`, and improves performance by using straight method calls
	 * in most cases.
	 *
	 * @param string $method Name of the method to call.
	 * @param array $params Parameter list to use when calling `$method`.
	 * @return mixed Returns the result of the method call.
	 */
	public static function invokeMethod($method, $params = array()) {
		switch (count($params)) {
			case 0:
				return static::$method();
			case 1:
				return static::$method($params[0]);
			case 2:
				return static::$method($params[0], $params[1]);
			case 3:
				return static::$method($params[0], $params[1], $params[2]);
			case 4:
				return static::$method($params[0], $params[1], $params[2], $params[3]);
			case 5:
				return static::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
			default:
				return forward_static_call_array(array(get_called_class(), $method), $params);
		}
	}

	/**
	 * Executes a set of filters against a method by taking a method's main implementation as a
	 * callback, and iteratively wrapping the filters around it.
	 *
	 * @param string|array $method The name of the method being executed, or an array containing
	 *        the name of the class that defined the method, and the method name.
	 * @param array $params An associative array containing all the parameters passed into
	 *        the method.
	 * @param Closure $callback The method's implementation, wrapped in a closure.
	 * @param array $filters Additional filters to apply to the method for this call only.
	 * @return mixed
	 */
	protected static function _filter($method, $params, $callback, $filters = array()) {
		$class = get_called_class();
		$hasNoFilters = empty(static::$_methodFilters[$class][$method]);

		if ($hasNoFilters && !$filters && !Filters::hasApplied($class, $method)) {
			return $callback($class, $params, null);
		}
		if (!isset(static::$_methodFilters[$class][$method])) {
			static::$_methodFilters += array($class => array());
			static::$_methodFilters[$class][$method] = array();
		}
		$data = array_merge(static::$_methodFilters[$class][$method], $filters, array($callback));
		return Filters::run($class, $params, compact('data', 'class', 'method'));
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
	protected static function _stop($status = 0) {
		exit($status);
	}

}
?>