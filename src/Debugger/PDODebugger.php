<?php declare(strict_types=1);

namespace Debugger;

use PDOStatement;
use PDO;

/**
 * SQL logging for debugger
 */
class PDODebugger extends PDO {

	/**
	 * @param string $dsn
	 * @param string|null $username
	 * @param string|null $password
	 * @param array|null $options
	 */
	public function __construct (string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
		parent::__construct($dsn, $username, $password, $options);
		if (class_exists('\Debugger\Debugger', false)) $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatementDbg::class]);
	}

	/**
	 * @param string $statement
	 * @return int|false
	 */
	public function exec (string $statement) {
		$req = $this->query($statement);
		return $req ? $req->rowCount() : false;
	}

	/**
	 * @param string $statement
	 * @return PDOStatement|false
	 */
	public function query (string $statement) {
		$args = func_get_args();
		$ret = $this->prepare(array_shift($args));
		if (!empty($args)) call_user_func_array([$ret, 'setFetchMode'], $args);
		$ret->execute();
		return $ret;
	}
}
