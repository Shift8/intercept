<?php
namespace Shift8\Intercept\Examples;

class StaticExample extends \Shift8\Intercept\StaticObject {

	public static function test($options = array()) {
		$params = compact('options');
		return static::_filter(__FUNCTION__, $params, function($self, $params) {
			// Whatever you want to do, do it here and return the result.
			return 'test static result';
		});
	}

}
?>