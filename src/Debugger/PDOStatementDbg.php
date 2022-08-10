<?php
namespace Debugger;

/**
 * SQL logging for debugger
 */
class PDOStatementDbg extends \PDOStatement {
	public function execute ($bound_input_params = null) {
		$start = microtime(true);
		try {
			parent::execute($bound_input_params);
			Debugger::sql($this->queryString, $this->rowCount(), microtime(true) - $start);
		}
		catch (PDOException $e) {
			Debugger::sql($this->queryString, '-', microtime(true) - $start);
			throw $e;
		}
		return $this;
	}
}