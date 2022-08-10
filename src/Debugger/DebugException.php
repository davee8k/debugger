<?php
namespace Debugger;

/**
 * Support exception class for decision making
 */
class DebugException extends \Exception {
	function __construct ($message, $code, $file, $line, $previous = null) {
		$this->file = $file;
		$this->line = $line;
		parent::__construct($message, $code, $previous);
	}
}