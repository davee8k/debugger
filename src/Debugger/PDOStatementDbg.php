<?php declare(strict_types=1);

namespace Debugger;

use PDOStatement,
	PDOException;

/**
 * SQL logging for debugger
 */
class PDOStatementDbg extends PDOStatement {

	/**
	 * @param mixed[]|null $params	Bound input parameters
	 * @return bool
	 * @throws PDOException
	 */
	public function execute (array $params = null): bool {
		$ret = false;
		$start = microtime(true);
		try {
			$ret = parent::execute($params);
			Debugger::sql($this->queryString, $this->rowCount(), microtime(true) - $start);
		}
		catch (PDOException $e) {
			Debugger::sql($this->queryString, null, microtime(true) - $start);
			throw $e;
		}
		return $ret;
	}
}
