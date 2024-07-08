<?php declare(strict_types=1);

namespace Debugger;

use Exception;
use Throwable;

/**
 * Support exception class for decision-making
 */
class DebugException extends Exception {

	public function __construct (string $message, int $code, string $file = null, int $line = null, ?Throwable $previous = null) {
		if ($file !== null) $this->file = $file;
		if ($line !== null) $this->line = $line;
		parent::__construct($message, $code, $previous);
	}
}
