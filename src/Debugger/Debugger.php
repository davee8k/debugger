<?php
namespace Debugger;

/**
 * Simple debugger PHP 5.5+
 *
 * @author DaVee8k
 * @version 0.86.0
 * @license https://unlicense.org/
 */
class Debugger {
	/**
	 * @var string
	 */
	public static $session = 'rs-debugger';
	/**
	 * @var string[]
	 */
	public static $errorTypes = [
		E_ERROR => 'FATAL ERROR',
		E_USER_ERROR => 'USER ERROR',
		E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
		E_PARSE => 'PARSE ERROR',
		E_CORE_ERROR => 'CORE ERROR',
		E_COMPILE_ERROR => 'COMPILE ERROR',
		E_WARNING => 'WARNING',
		E_USER_WARNING => 'USER WARNING',
		E_CORE_WARNING => 'CORE WARNING',
		E_COMPILE_WARNING => 'COMPILE WARNING',
		E_NOTICE => 'NOTICE',
		E_USER_NOTICE => 'USER NOTICE',
		E_STRICT => 'STRICT STANDARDS',
		E_DEPRECATED => 'DEPRECATED',
		E_USER_DEPRECATED => 'USER DEPRECATED'
	];
	/**
	 * @var string|null	Send notification on mail if hidden
	 */
	private static $email = null;
	/**
	 * @var string|bool
	 */
	private static $work = false;
	/**
	 * @var string|null
	 */
	private static $dir = null;
	/**
	 * @var string|null
	 */
	private static $url = null;
	/**
	 * @var bool	log suppressed errors
	 */
	private static $suppressed = false;
	/**
	 * @var string
	 */
	private static $file = 'exception';
	/**
	 * @var int
	 */
	private static $time = 0;
	/**
	 * @var array
	 */
	private static $query = [];
	/**
	 * @var array
	 */
	private static $runtimeError = [];
	/**
	 * @var array|null
	 */
	private static $memory = null;
	/**
	 * @var array
	 */
	private static $attachments = [];

	/**
	 * Debugger constructor.
	 * @param mixed $work
	 * @param string|null $dir
	 * @param int|null $level
	 */
	function __construct ($work, $dir = null, $level = null) {
		self::$time = filter_input(INPUT_SERVER, 'REQUEST_TIME_FLOAT', FILTER_VALIDATE_FLOAT) ?: microtime(true);
		// load variables
		self::$work = $work;
		self::$dir = $dir;
		// set on for debugging
		if (self::$work) {
			// disable errors
			if (function_exists('ini_set')) {
				if (self::$dir !== null) ini_set('error_log', self::$dir.'php_error.log');

				error_reporting(is_null($level) ? -1 : $level);
				ini_set('html_errors', false);
				ini_set('log_errors', false);
				ini_set('display_errors', false);
			}
			set_exception_handler([__CLASS__, 'handlerException']);
			set_error_handler([__CLASS__, 'handleError']);
			register_shutdown_function([__CLASS__, 'handleShutDown']);
			self::$runtimeError = error_get_last();
			self::$memory = self::loadMemory();
		}

		// current url
		if (filter_has_var(INPUT_SERVER, 'REQUEST_URI')) {
			self::$url = (!empty(filter_input(INPUT_SERVER, 'HTTPS')) ? 'https://' : 'http://').
					(filter_input(INPUT_SERVER, 'HTTP_HOST') ?: filter_input(INPUT_SERVER, 'SERVER_NAME')).
					filter_input(INPUT_SERVER, 'REQUEST_URI');
		 }
	}

	/**
	 * Get work mode
	 * @return mixed
	 */
	public static function getWorkState () {
		return self::$work;
	}

	/**
	 *
	 * @param Throwable $e
	 * @param string $path
	 * @throws Throwable
	 */
	public static function errorAccess ($e, $path) {
		if (self::getWorkState() === true) throw $e;
		self::log($e->getMessage().'. From '.getenv('REMOTE_ADDR').' in /'.$path, 'access');
	}

	/**
	 * Set email address
	 * @param string $email
	 */
	public static function setMail ($email) {
		self::$email = $email;
	}

	/**
	 *
	 * @param string $mark
	 * @param string $name
	 * @param string $content
	 */
	public static function addAttachment ($mark, $name, $content = null) {
		self::$attachments[$mark] = ['NAME'=>$name, 'TEXT'=>$content];
	}

	/**
	 * Disable debugger in html - turn to file work mode
	 * @param bool $terminate
	 */
	public static function disable ($terminate = false) {
		if ($terminate || self::$work === true) self::$email = false;
		if (!$terminate && self::$work && self::$dir) self::$work = 'FILE';
		else self::$work = false;
	}

	/**
	 * Write error message into file
	 * @param string $message
	 * @param string $file
	 */
	public static function log ($message, $file = 'error') {
		if (self::$dir) error_log('['.@date('Y-m-d H:i:s').'] '.trim($message).' @ '.self::$url.PHP_EOL, 3, self::$dir.$file.'.log');
	}

	public static function sql ($query, $rows, $time) {
		if (self::$work) {
			list($where) = array_slice(debug_backtrace(), 2, 1);	// PHP 5.4 debug_backtrace()[2]
			self::$query[] = ['QUERY'=>$query,'ROWS'=>$rows,'TIME'=>$time,
				'FILE'=>isset($where['file']) ? $where['file'] : '','LINE'=>isset($where['line']) ? $where['line'] : ''];
		}
	}

	/**
	 * Load memory from session
	 * @return mixed
	 */
	public static function loadMemory () {
		if (filter_has_var(INPUT_COOKIE, self::$session) && self::getRequestMode() === 0) {
			if (session_status() !== PHP_SESSION_ACTIVE) session_write_close();

			$sessionName = session_name(self::$session);
			session_start();

			$memory = !empty($_SESSION['debug_history']) ? $_SESSION['debug_history'] : null;
			unset($_SESSION['debug_history']);
			session_write_close();
			setcookie(self::$session, '', time() - 3600, '/');

			session_name($sessionName);

			return $memory;
		}
		return null;
	}

	/**
	 * Save current debug into memory
	 */
	public static function saveMemory () {
		if (!headers_sent()) {
			if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

			$sessionName = session_name(self::$session);
			session_start();

			$_SESSION['debug_history'] = self::$memory;
			$_SESSION['debug_history'][] = self::getData(microtime(true) - self::$time) + self::$attachments;

			session_write_close();
			session_name($sessionName);
		}
	}

	/**
	 * Ignore suppresed @ warnings
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @param array $errcontext
	 */
	public static function handleError ($errno, $errstr, $errfile, $errline, array $errcontext = null) {
		// 4437 - suppressed in php 8
		if (self::$suppressed || ($errno & error_reporting()) === $errno) {
			self::handlerException(new DebugException($errstr, $errno, $errfile, $errline));
		}
		return null;
	}

	/**
	 * @param Throwable $e
	 */
	public static function handlerException ($e) {
		if (!$e instanceof SilentDieException) {
			if (self::$work !== true) {
				self::log($e->getMessage().' ('.$e->getCode().') In '.$e->getFile().' at line '.$e->getLine());
				if (self::$email) self::sendNotifyMail(self::$email, $e->getMessage());
			}
			if (self::$work === 'FILE') {
				if (!self::$dir) die("DEBUGGER: UNSET DIRECTORY.");
				$errorHash = md5($e->getMessage().'-'.$e->getCode().'-'.$e->getFile().'-'.$e->getLine());
				if (!self::exeptionFileExists($errorHash)) {
				ob_start();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title><?=strip_tags($e->getMessage());?></title>
		<meta charset="UTF-8" />
	</head>
	<body>
<?php
				self::drawError($e);

				echo '<hr><ul>',
					'<li>Generated: '.@date('Y-m-d H:i:s')."</li>\n",
					'<li>'.phpversion()."</li>\n",
					(self::$url ? '<li><a href="'.self::$url.'"></a>'.self::$url."</li>\n" : ''),
					'</ul>';

				self::drawDebugger();
?>
	</body>
</html>
<?php
					$fileName = self::$file.'_'.@date('Y-m-d-H-i-s').'_'.$errorHash.'.html';
					file_put_contents(self::$dir.$fileName, ob_get_clean(), LOCK_EX);
				}
			}
			else if (self::$work) {
				if (!headers_sent()) {
					header('Content-Type: text/html; charset=utf-8');
					http_response_code(500);
				}
				self::drawError($e);
				flush();
			}
		}
	}

	/**
	 *
	 * @return void
	 */
	public static function handleShutDown () {
		$error = error_get_last();
		if ($error !== null && (self::$work === true || self::$runtimeError !== $error)) {
			$error += ['type'=>E_CORE_ERROR, 'file'=>'unknown file', 'line'=>0, 'message'=>'shutdown'];
			self::handlerException(new DebugException($error['message'], $error['type'], $error['file'], $error['line']));
		}

		if (self::$work === true) {
			// can be shown
			if (self::getRequestMode() > 0) self::saveMemory();
			else self::drawDebugger();
		}
	}

	/**
	 * Send notify email with basic information about error
	 * @param string $email
	 * @param string $message
	 * @return bool
	 */
	public static function sendNotifyMail ($email, $message) {
		$host = trim(filter_input(INPUT_SERVER, 'HTTP_HOST') ?: ( filter_input(INPUT_SERVER, 'SERVER_NAME') ?: gethostname() ), " .");
		return mail($email,
			"PHP error call from ".$host,
			'['.@date('Y-m-d H:i:s').'] '.trim($message),
			'From: noreply@'.$host."\r\nContent-Type: text/plain; charset=UTF-8");
	}

	/**
	 * Draw exeption
	 * @param Throwable $e
	 */
	public static function drawError ($e) {
		$trace = $e->getTrace();
		if ($e instanceof PDOException) $trace = array_slice ($trace, 2);
?>
<div id="rs-debug-error" style="border: 2px solid red; background: #fff; color: #000; padding: 5px;">
	<h4><?=(isset(self::$errorTypes[$e->getCode()]) ? self::$errorTypes[$e->getCode()] : $e->getCode()).': '.$e->getMessage();?></h4>
<?="<p>in ".self::formatFileName($e->getFile())." on line <b>".$e->getLine()."</b></p>";?>
	<ul>
<?php
		if (count($trace) > 1) {
			foreach ($trace as $debug) {
				if (isset($debug['file'])) {
					if (!isset($debug['class']) || $debug['class'] != get_called_class()) echo "<li>in ".self::formatFileName($debug['file'])." on line <b>".$debug['line']."</b> at <i>".
							(isset($debug['class']) ? $debug['class'] : '').(isset($debug['type']) ? $debug['type'] : '').$debug['function']."()</i></li>\n";
				}
			}
		}
?>
	</ul>
</div>
<?php
	}

	/**
	 * Draw debugger status bar
	 */
	public static function drawDebugger () {
		$totalTime = microtime(true) - self::$time;
?>

<!-- DEBUGGER -->
<style type="text/css">
	#rs-debug-bar { position: fixed; z-index: 1000; bottom: 0; left: 0; background: #fff; color: #000; opacity: 0.4; font-size: 13px; line-height: 16px; font-family: sans-serif; box-shadow: #666 0 0 5px; }
	#rs-debug-bar:hover { opacity: 1.0; }
	#rs-debug-bar .debug-row-history { font-size: 11px; line-height: 13px; }
	#rs-debug-bar .debug-tab { position: relative; display: inline-block; padding: 2px; }
	#rs-debug-bar .debug-tab>span:hover { cursor: pointer; background: #ddd; }
	#rs-debug-bar .debug-window { position: absolute; bottom: 20px; left: 0; display: none; max-width: 700px; padding: 5px; background: #fff; border-radius: 5px; box-shadow: #666 0 0 5px; }
	#rs-debug-bar .debug-window table { width: 100%; margin-bottom: 10px; word-wrap: break-word; }
	#rs-debug-bar .debug-window table th,
	#rs-debug-bar .debug-window table caption { font-size: medium; line-height: 1.5em; color: #fff; background: #666; }
	#rs-debug-bar .debug-window table tr:nth-child(even) { background: #ddd; }
	#rs-debug-bar .debug-window table td { padding: 3px; word-break: break-all;}
	#rs-debug-bar .debug-content { max-height: 500px; width: 600px; overflow: auto; }
	#rs-debug-bar .debug-close { text-decoration: none; color: #f00; font-size: 14px; line-height: 14px; font-weight: bold; float: right; }
</style>
<div id="rs-debug-bar">
	<div>
		<?php self::drawDebbugerLine(self::getData($totalTime) + self::$attachments);?>
		<div class="debug-tab"><a title="close bar" href="#" class="debug-close" style="float: none;" onclick="var el = document.getElementById('rs-debug-bar'); el.parentNode.removeChild(el); return false;">×</a></div>
	</div>
<?php
	if (self::$memory) {
		foreach (array_reverse(self::$memory) as $i=>$data) {
			echo '<div class="debug-row-history">';
			self::drawDebbugerLine($data, $i);
			echo "</div>\n";
		}
	}
?>
</div>
<?php
	}

	/**
	 * Draw debugger line
	 * @param array $data
	 * @param string $i
	 */
	protected static function drawDebbugerLine (array $data, $i = '') {
		foreach ($data as $key=>$row) {
			$idMark = 'rs-debug-fixed-bar-'.$key.$i;

			echo '	<div class="debug-tab">',(empty($row['TEXT']) ? $row['NAME'] :
				'<span onclick="document.getElementById(\''.$idMark.'\').style.display=\'block\'">'.$row['NAME'].'</span>'),"\n";
			if (!empty($row['TEXT'])) {
?>
		<div id="<?=$idMark;?>" class="debug-window">
			<a title="close bar" href="#" class="debug-close" onclick="this.parentNode.style.display='none'; return false;">×</a>
			<div class="debug-content">
				<?=$row['TEXT'];?>
			</div>
		</div>
<?php
			}
			echo "	</div>\n";
		}
	}

	/**
	 * Load all data for current debugger
	 * @param int $totalTime
	 * @return array
	 */
	private static function getData ($totalTime) {
		$array = [];

		// database
		$array['sql']['NAME'] = 'SQL: no query';
		if (!empty(self::$query)) {
			$totalSQL = 0;
			foreach (self::$query as $q) $totalSQL += $q['TIME'];
			$array['sql']['NAME'] = 'SQL: '.self::formatTime($totalSQL).'ms / '.count(self::$query).' query';

			$text = "<table><tr><th>Time [ms]</th><th>Request</th><th>Rows</th></tr>\n";
			foreach (self::$query as $q) {
				$text .= '<tr><td>'.self::formatTime($q['TIME'], 3).'</td><td>'.
					preg_replace('/(^|\s)(SELECT|UPDATE|INSERT INTO|DELETE|FROM|WHERE|CALL|LIMIT|ORDER[\s]+BY|GROUP[\s]+BY|LEFT[\s]+JOIN|RIGHT[\s]+JOIN|INNER[\s]+JOIN|JOIN)(\s)/i',
							'$1<br><b>$2</b>$3', htmlspecialchars($q['QUERY'], ENT_QUOTES)).'<br><br><small>in '.$q['FILE'].' on line '.$q['LINE'].'</small></td><td>'.$q['ROWS']."</td></tr>\n";
			}
			$array['sql']['TEXT'] = $text."</table>\n";
		}

		// variables
		$array['vars']['NAME'] = 'Memory: '.self::formatMemory(memory_get_peak_usage()).'/'.self::formatMemory(memory_get_peak_usage(true)).'MB';
		$text = self::getTable('GET', $_GET);
		$text .= self::getTable('POST', $_POST);
		$text .= self::getTable('FILES', $_FILES);
		$text .= self::getTable('COOKIE', $_COOKIE);
		$text .= self::getTable('SESSION', isset($_SESSION) ? $_SESSION : []);
		$text .= self::getTable('REQUEST', self::getRequestHeaders());
		$text .= self::getTable('RESPONSE', self::getResponseHeaders());
		$text .= self::getTable('SERVER', $_SERVER);
		$array['vars']['TEXT'] = $text;

		// total time
		$array['time']['NAME'] = 'Time: '.self::formatTime($totalTime).'ms';

		return $array;
	}

	/**
	 *
	 * @return string[]
	 */
	private static function getRequestHeaders () {
		if (is_callable('apache_request_headers')) return apache_request_headers();
		return ['UNDEF'];
	}

	/**
	 *
	 * @return string[]
	 */
	private static function getResponseHeaders () {
		if (is_callable('apache_response_headers')) return apache_response_headers();
		$arr = [];
		$headers = headers_list();
		foreach ($headers as $header) {
			$header = explode(':', $header);
			$arr[array_shift($header)] = trim(implode(':', $header));
		}
		return $arr;
	}


	private static function getRequestMode () {
		if (filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') return 2;
			foreach (headers_list() as $header) {
				$h = trim($header);
				if (stripos($h, 'Location') === 0) return 1;
				if (stripos($h, 'Content-Disposition') === 0 || preg_match('/^Content-Type:(?! *text\/html)/i', $h)) return 3;
		}
		return 0;
	}

	/**
	 * Get table with data
	 * @param string $name
	 * @param array $array
	 * @return string
	 */
	private static function getTable ($name, array $array) {
		$text = "<table>\n".'<caption>'.$name."</caption>\n";
		foreach ($array as $key=>$val) $text .= '<tr><td>'.$key.'</td><td>'.htmlspecialchars(print_r($val,true), ENT_QUOTES)."</td></tr>\n";
		return $text.'</table>'."\n";
	}

	/**
	 * Format file name
	 * @param string $filePath
	 * @return string
	 */
	private static function formatFileName ($filePath) {
		$orPath = dirname($filePath);
		if ($orPath === '.') return '<b>'.$filePath.'</b>';
		return $orPath.'<b>'.substr($filePath, strlen($orPath)).'</b>';
	}

	/**
	 * Format memory
	 * @param int $num
	 * @return float
	 */
	private static function formatMemory ($num) {
		return round($num / (1024 * 1024), 3);
	}

	/**
	 * Format time
	 * @param int $time
	 * @param int $num
	 * @return float
	 */
	private static function formatTime ($time, $num = 2) {
		return round($time * 1000, $num);
	}

	/**
	 * Check if file log for current error already exist
	 * @param string $hash
	 * @return bool
	 */
	private static function exeptionFileExists ($hash) {
		foreach (scandir(self::$dir) as $file) {
			if (preg_match('/^'.preg_quote(self::$file).'_[0-9\-]+_'.$hash.'\.html$/', $file)) return true;
		}
		return false;
	}
}