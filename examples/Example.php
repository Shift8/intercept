<?php
namespace Shift8\Intercept\Examples;

class Example extends \Shift8\Intercept\Object {

	public function test($options = array()) {
		$params = compact('options');

		return $this->_filter(__METHOD__, $params, function($self, $params) {
			// Whatever you want to do, do it here and return the result.
			return 'test result';
		});
	}

}
?>