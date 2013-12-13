<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'MappingIterator.php';

class CallbackMappingIterator extends MappingIterator {

	private $callback;

	public function __construct($iterator, $callback) {
		parent::__construct($iterator);
		$this->callback = $callback;
	}

	public function map($key, $value) {
		return call_user_func($this->callback, $key, $value);
	}

}

?>
