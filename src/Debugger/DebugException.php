<?php declare(strict_types=1);

namespace Debugger;

use Exception;

/**
 * Support exception class for decision-making
 */
class DebugException extends Exception {

	public function __construct (string $message, int $code, string $file, int $line, ?Throwable $previous = null) {
		$this->file = $file;
		$this->line = $line;
		parent::__construct($message, $code, $previous);
	}
}
