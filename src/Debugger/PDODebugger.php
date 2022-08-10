<?php
namespace Debugger;

/**
 * SQL logging for debugger
 */
class PDODebugger extends \PDO {

	public function __construct ($dsn, $username = null, $password = null, array $options = null) {
		parent::__construct($dsn, $username, $password, $options);
		if (class_exists('\Debugger\Debugger', false)) $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['\Debugger\PDOStatementDbg']);
	}

	/**
	 * @param string $statement
	 * @return int
	 */
	public function exec ($statement) {
		return $this->query($statement)->rowCount();
	}

	/**
	 * @param string $statement
	 * @return bool|PDOStatement
	 */
	public function query ($statement) {
		$args = func_get_args();
		$ret = $this->prepare(array_shift($args));
		if (!empty($args)) call_user_func_array([$ret, 'setFetchMode'], $args);
		$ret->execute();
		return $ret;
	}
}