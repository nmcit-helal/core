<?php
namespace Df\Payment\W;
use Df\Framework\Request as Req;
use Df\Payment\Method as M;
use Df\Payment\W\Exception\Critical;
/**
 * 2017-03-10
 * 2017-03-13
 * Каждый модуль может иметь не больше одного Reader,
 * и Reader должен быть расположен по пути W\Reader.
 * @see \Df\Payment\W\Reader\Json
 * @see \Dfe\AllPay\Reader
 */
class Reader implements IEvent {
	/**
	 * 2017-03-10
	 * $m здесь НЕ СОДЕРЖИТ корректного II.
	 * Для вычисления корректного II нам ещё предстоит провести кучу операций:
	 * 1) Определить, к какой транзакции Magento относится данное событие.
	 * 2) Загрузить эту транзакцию из БД.
	 * 3) По транзакции получить II.
	 * Это всё нам ещё предстоит!
	 * @used-by \Df\Payment\W\F::__construct()
	 * @param M $m
	 * @param array(string => mixed)|null $req [optional]
	 * *) null в качестве значения $req означает, что $req должен быть взят из запроса HTTP,
	 * *) массив в качестве значения $req означает прямую инициализацию $req:
	 * это сценарий @see \Df\PaypalClone\TM::responses()
	 */
	final function __construct(M $m, $req = null) {
		$this->_m = $m;
		$this->_test = is_null($req) ? Req::extra() : [];
		$this->_req = $this->_test ? $this->testData() : (!is_null($req) ? $req : $this->http());
	}

	/**
	 * 2017-03-17
	 * @final I do not use the PHP «final» keyword here to allow refine the return type using PHPDoc.
	 * $m здесь НЕ СОДЕРЖИТ корректного II.
	 * Для вычисления корректного II нам ещё предстоит провести кучу операций:
	 * 1) Определить, к какой транзакции Magento относится данное событие.
	 * 2) Загрузить эту транзакцию из БД.
	 * 3) По транзакции получить II.
	 * Это всё нам ещё предстоит!
	 * @used-by \Df\Payment\W\Event::m()
	 * @return string
	 */
	function m() {return $this->_m;}

	/**
	 * 2017-03-10
	 * @override
	 * @see \Df\Payment\W\IEvent::r()
	 * @used-by error()
	 * @used-by rr()
	 * @used-by \Df\Payment\W\Reader::t()
	 * @param string|null $k
	 * @param string|null $d
	 * @return array(string => mixed)|mixed|null
	 */
	final function r($k = null, $d = null) {return dfak($this->_req, $k, $d);}
	
	/**
	 * 2017-01-12
	 * @used-by \Df\Payment\W\Event::rr()
	 * @used-by \Df\Payment\W\Reader::t()
	 * @param string|string[]|null $k [optional]
	 * @param mixed|null $d [optional]
	 * @return array(string => mixed)|mixed
	 * @throws Critical
	 */
	final function rr($k = null, $d = null) {return
		!is_null($r = $this->r($k, $d)) ? $r : $this->errorP($k)
	;}

	/**
	 * 2017-03-10
	 * Some PSP send only one type of notifications.
	 * In such case, a notification does not denote its own type, and this method returns null.
	 * 2017-03-13
	 * Returns a value in our internal format, not in the PSP format.
	 * @used-by tl()
	 * @used-by \Df\Payment\W\Event::t()
	 * @used-by \Dfe\AllPay\W\Reader::isOffline()
	 * @return string|null
	 */
	final function t() {return dfc($this, function() {return
		is_null($r = $this->tRaw()) ? null : $this->te2i($r)
	;});}

	/**
	 * 2017-03-12
	 * Type label.
	 * @override
	 * @see \Df\Payment\W\IEvent::tl()
	 * @used-by \Df\Payment\W\Action::ignored()
	 * @used-by \Df\Payment\W\Handler::log()
	 * @return string
	 */
	final function tl() {return dfc($this, function() {return $this->tl_($this->t());});}

	/**
	 * 2017-03-10
	 * @used-by tl()
	 * @used-by \Df\Payment\W\Event::tl_()
	 * @param string|null $t
	 * @return string
	 */
	final function tl_($t) {return !is_null($t) ? $t : 'Confirmation';}

	/**
	 * 2017-03-13
	 * Returns a value in the PSP format.
	 * @used-by t()
	 * @used-by \Df\Payment\W\Event::tl()
	 * @return string|null
	 */
	final function tRaw() {return $this->tt() ?: (is_null($kt = $this->kt()) ? null : $this->rr($kt));}

	/**
	 * 2017-03-10
	 * @used-by __construct()
	 * @see \Df\Payment\W\Reader\Json::http()
	 * @return array(string => mixed)
	 */
	protected function http() {return Req::clean();}

	/**
	 * 2017-03-10
	 * @used-by \Df\Payment\W\Reader::t()
	 * @see \Dfe\AllPay\Reader::kt()
	 * @see \Dfe\Omise\W\Reader::kt()
	 * @see \Dfe\Paymill\W\Reader::kt()
	 * @see \Dfe\Stripe\W\Reader::kt()
	 * @return string|null
	 */
	protected function kt() {return null;}

	/**
	 * 2017-03-12
	 * Converts an event type from the PSP format to our internal format.
	 * @used-by t()
	 * @param string $t
	 * @return string
	 */
	protected function te2i($t) {return $t;}

	/**
	 * 2017-03-10
	 * @used-by errorP()
	 * @param string $reason
	 * @throws Critical
	 */
	private function error($reason) {
		($r = $this->r()) ? df_sentry_extra($this, 'Request', $r) : null;
		throw new Critical($this->_m, $this, "The request is invalid because $reason.");
	}

	/**
	 * 2017-03-15
	 * @used-by rr()
	 * @param $k
	 */
	private function errorP($k) {$this->error("the required parameter «{$k}» is absent");}

	/**
	 * 2017-03-10
	 * @used-by testData()
	 * @used-by tt()
	 * @param string|null $k
	 * @param string|null $d
	 * @return array(string => mixed)|mixed|null
	 */
	private function test($k = null, $d = null) {return dfak($this->_test, $k, $d);}

	/**
	 * 2017-03-11
	 * @used-by __construct()
	 * @return array(string => mixed)
	 */
	private function testData() {
		/** @var string $module */
		$module = df_module_name_short($this->_m);
		/** @var string $baseName */
		$baseName = df_ccc('-', $this->tt(true), $this->test('case'));
		/** @var string $file */
		if (!file_exists($file = BP . df_path_n_real("/_my/test/{$module}/{$baseName}.json"))) {
			df_error("Place your test data to the «{$file}» file.");
		}
		return df_json_decode(file_get_contents($file));
	}

	/**
	 * 2017-03-11
	 * @used-by testData()
	 * @used-by tRaw()
	 * @param bool $required [optional]
	 * @return string|null
	 */
	private function tt($required = false) {return
		!is_null($r = $this->test($k = 'type')) || !$required ? $r : $this->errorP("df-$k")
	;}

	/**
	 * 2017-03-11
	 * $m здесь НЕ СОДЕРЖИТ корректного II.
	 * Для вычисления корректного II нам ещё предстоит провести кучу операций:
	 * 1) Определить, к какой транзакции Magento относится данное событие.
	 * 2) Загрузить эту транзакцию из БД.
	 * 3) По транзакции получить II.
	 * Это всё нам ещё предстоит!
	 * @used-by __construct()
	 * @used-by error()
	 * @used-by m()
	 * @used-by testData()
	 * @var M
	 */
	private $_m;

	/**
	 * 2017-03-10
	 * @used-by __construct()
	 * @used-by r()
	 * @var array(string => mixed)
	 */
	private $_req;

	/**
	 * 2017-03-10
	 * @used-by __construct()
	 * @used-by test()
	 * @var array(string => mixed)
	 */
	private $_test;
}