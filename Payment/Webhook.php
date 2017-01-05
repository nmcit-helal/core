<?php
namespace Df\Payment;
use Df\Core\Exception as DFE;
use Df\Framework\Controller\Result\Text;
use Df\Payment\Settings as S;
use Df\Sales\Model\Order as DfOrder;
use Magento\Framework\Controller\AbstractResult as Result;
use Magento\Sales\Api\Data\OrderInterface as IO;
use Magento\Sales\Api\Data\OrderPaymentInterface as IOP;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OP;
use Magento\Sales\Model\Order\Payment\Transaction as T;
use Magento\Store\Model\Store;
// 2016-07-09
// Портировал из Российской сборки Magento.
abstract class Webhook extends \Df\Core\O {
	/**
	 * 2017-01-01
	 * @used-by handle()
	 * @return void
	 */
	abstract protected function _handle();

	/**
	 * 2016-08-27
	 * 2016-12-31
	 * Перекрытие этого метода позволяет потомкам разом задать набор параметров данного класса.
	 * Такая техника является более лаконичным вариантом,
	 * нежели объявление и перекрытие методов для отдельных параметров.
	 * @used-by configCached()
	 * @return array(string => mixed)
	 */
	abstract protected function config();

	/**
	 * 2017-01-04
	 * Преобразует в глобальный внутренний идентификатор транзакции:
	 * 1) Внешний идентификатор транзакции.
	 * Это случай, когда идентификатор формируется платёжной системой.
	 * 2) Локальный внутренний идентификатор транзакции.
	 * Это случай, когда мы сами ранее сформировали идентификатор запроса к платёжной системе.
	 * Мы намеренно передавали идентификатор локальным (без приставки с именем модуля)
	 * для удобства работы с этими идентификаторами в интерфейсе платёжной системы:
	 * ведь там все идентификаторы имели бы одинаковую приставку.
	 * Такой идентификатор формируется в методах:
	 * @see \Df\PaypalClone\Charge::requestId()
	 * @see \Dfe\AllPay\Charge::requestId()
	 *
	 * Глобальный внутренний идентификатор отличается наличием приставки «<имя модуля>-».
	 * @used-by id()
	 * @param string $externalId
	 * @return string
	 */
	abstract protected function e2i($externalId);

	/**
	 * 2017-01-05
	 * Возвращает наш внутренний идентификатор родительской транзакции в неком «сыром» формате.
	 * В настоящее время этот «сырой» формат бывает 2-х видов:
	 *
	 * 1) Идентификатор платежа в платёжной системе.
	 * Так происходит для Stripe-подобных модулей.
	 * На основе этого идентификатора мы:
	 *     1.1) вычисляем идентификатор родительской транзакции
	 *     (посредством прибавления окончания «-<тип родительской транзакции>»)
	 *     1.2) создаём идентификатор текущей транзакции
	 *     (аналогично, посредством прибавления окончания «-<тип текущей транзакции>»).
	 * @see \Df\StripeClone\Webhook::parentIdRawKey()
	 *
	 * 2) Переданный нами ранее платёжной системе
	 * наш внутренний идентификатор родительской транзакции (т.е., запроса к платёжой системе)
	 * в локальном (коротком) формате (т.е. без приставки «<имя платёжного модуля>-»).
	 * @see \Df\PaypalClone\Webhook::parentIdRawKey()
	 *
	 * @used-by parentIdRaw()
	 * @return string
	 */
	abstract protected function parentIdRawKey();

	/**
	 * 2017-01-04
	 * @used-by testData()
	 * @see \Df\PaypalClone\Webhook::testDataFile()
	 * @see \Df\StripeClone\Webhook::testDataFile()
	 * @return string
	 */
	abstract protected function testDataFile();

	/**
	 * 2016-12-26
	 * @used-by typeLabel()
	 * @used-by \Dfe\AllPay\Webhook::classSuffix()
	 * @used-by \Dfe\AllPay\Webhook::typeLabel()
	 * @return string
	 */
	abstract protected function type();

	/**
	 * 2017-01-01
	 * @used-by \Df\Payment\WebhookF::i()
	 * @param array(string => mixed) $req
	 * @param array(string => mixed) $extra [optional]
	 */
	final public function __construct(array $req, array $extra = []) {
		parent::__construct();
		$this->_extra = $extra;
		// 2017-01-04
		// Мы можем так писать, потому что test() не вызывае req() (а вызывает extra()),
		// и бесконечной рекурсии не будет.
		$this->_req = !$this->test() ? $req : $this->testData();
	}

	/**
	 * 2016-07-10
	 * 2016-12-31
	 * Возвращает идентификатор текущего платежа в платёжной системе.
	 * Этот идентификатор мы используем двояко:
	 * 1) Для последующих запросов к платёжной системе.
	 * 2) Для отображения администратору магазина
	 * (при возможности — с прямой ссылкой на страницу платежа
	 * в личном кабинете магазина в платёжной системе)
	 * @used-by \Dfe\AllPay\Block\Info::_prepareSpecificInformation()
	 * @used-by \Dfe\SecurePay\Method::_refund()
	 * @used-by id()
	 * @return string
	 */
	final public function externalId() {return $this->cv(self::$externalIdKey);}

	/**
	 * 2016-07-04
	 * @override
	 * @return Result
	 */
	final public function handle() {
		/** @var Result $result */
		try {
			if ($this->ss()->log()) {
				$this->log();
			}
			$this->validate();
			/**
			 * 2017-01-04
			 * Добавил обработку ситуации, когда к нам пришло сообщение,
			 * не предназначенное для нашего магазина.
			 * Такое происходит, например, когда мы проводим тестовый платёж на локальном компьютере,
			 * а платёжная система присылает оповещение на наш сайт mage2.pro/sandbox
			 * В такой ситуации не стоит падать с искючительной ситуацией,
			 * а лучше просто ответить: «The event is not for our store».
			 * Так и раньше вели себя мои Stripe-подобные модули,
			 * теперь же я распространил такое поведение на все мои платёжные модули.
			 */
			if (!$this->ii()) {
				$result = $this->resultNotForUs();
			}
			else {
				$this->addTransaction();
				$this->_handle();
				$result = $this->resultSuccess();
			}
		}
		catch (\Exception $e) {
			$this->log($e);
			/**
			 * 2016-07-15
			 * Раньше тут стояло
					if ($this->_order) {
						$this->_order->cancel();
						$this->_order->save();
					}
			 * На самом деле, исключительная ситуация свидетельствует о сбое в программе,
			 * либо о некорректном запросе якобы от платёжного сервера (хакерской попытке, например),
			 * поэтому отменять заказ тут неразумно.
			 * В случае сбоя платёжная система будет присылать
			 * повторные оповещения — вот пусть и присылает,
			 * авось мы к тому времени уже починим программу, если поломка была на нашей строне
			 */
			$result = static::resultError($e);
		}
		return $result;
	}

	/**
	 * 2016-07-09
	 * 2017-01-04
	 * Возвращает одно из двух:
	 *
	 * 1) Идентификатор платежа в платёжной системе.
	 * Так происходит для Stripe-подобных модулей.
	 * На основе этого идентификатора мы:
	 *     1.1) вычисляем идентификатор родительской транзакции
	 *     (посредством прибавления окончания «-<тип родительской транзакции>»)
	 *     1.2) создаём идентификатор текущей транзакции
	 *     (аналогично, посредством прибавления окончания «-<тип текущей транзакции>»).
	 *
	 * 2) Переданный нами ранее платёжной системе
	 * наш внутренний идентификатор родительской транзакции (т.е., запроса к платёжой системе)
	 * в локальном (коротком) формате (т.е. без приставки «<имя платёжного модуля>-»).
	 *
	 * @used-by parentId()
	 * @used-by \Dfe\AllPay\Block\Info::prepare()
	 * @return string
	 */
	final public function parentIdRaw() {return $this->req($this->parentIdRawKey());}

	/**
	 * 2016-07-10
	 * @used-by \Dfe\SecurePay\Signer\Response::req()
	 * @param string|null $k [optional]
	 * @return array(string => string)|string|null
	 */
	final public function parentInfo($k = null) {return dfak($this, function() {return
		df_trans_raw_details($this->tParent())
	;}, $k);}

	/**
	 * 2017-01-01
	 * @used-by \Dfe\AllPay\Block\Info\ATM::paymentId()
	 * @used-by \Dfe\AllPay\Webhook\BankCard::isInstallment()
	 * @used-by \Dfe\AllPay\Webhook\Offline::expiration()
	 * @param string|string[]|null $k [optional]
	 * @param mixed|null $d [optional]
	 * @return array(string => mixed)|mixed|null
	 */
	final public function req($k = null, $d = null) {return dfak($this->_req, $k, $d);}

	/**
	 * 2016-07-09
	 * 2016-07-14
	 * Раньше метод @see isSuccessful() вызывался из метода validate().
	 * Отныне же validate() проверяет, корректно ли сообщение от платёжной системы.
	 * Даже если оплата завершилась отказом покупателя, но оповещение об этом корректно,
	 * то validate() не возбудит исключительной ситуации.
	 * @see isSuccessful() же проверяет, прошла ли оплата успешно.
	 * @used-by handle()
	 * @return void
	 * @throws \Exception
	 */
	public function validate() {}

	/**
	 * 2016-07-12
	 * @return void
	 */
	final protected function addTransaction() {
		/** @var OP $i */
		$i = $this->ii();
		$i->setTransactionId($this->id());
		dfp_set_transaction_info($i, $this->req());
		/**
		 * 2016-07-12
		 * @used-by \Magento\Sales\Model\Order\Payment\Transaction\Builder::linkWithParentTransaction()
		 */
		$i->setParentTransactionId($this->parentId());
		/**
		 * 2016-07-10
		 * @uses \Magento\Sales\Model\Order\Payment\Transaction::TYPE_PAYMENT —
		 * это единственная транзакции без специального назначения,
		 * и поэтому мы можем безопасно его использовать.
		 *
		 * 2017-01-01
		 * @uses \Magento\Sales\Model\Order\Payment::addTransaction()
		 * создаёт и настраивает объект-транзакцию, но не записывает её в базу данных,
		 * поэтому если мы далее осуществляем операцию @see capture(),
		 * то там будет использована эта же транзакция
		 * (транзакция с этим же идентификатором, этими же данными
		 * и этой же ссылой на родительскую транзакцию), только её тип обновится на
		 * @see \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE
		 * @see \Magento\Sales\Model\Order\Payment\Transaction\Manager::generateTransactionId():
			if (!$payment->getParentTransactionId()
				&& !$payment->getTransactionId() && $transactionBasedOn
			) {
				$payment->setParentTransactionId($transactionBasedOn->getTxnId());
			}
			// generate transaction id for an offline action or payment method that didn't set it
			if (
				($parentTxnId = $payment->getParentTransactionId())
				&& !$payment->getTransactionId()
			) {
				return "{$parentTxnId}-{$type}";
			}
			return $payment->getTransactionId();
		 * https://github.com/magento/magento2/blob/2.0.0/app/code/Magento/Sales/Model/Order/Payment/Transaction/Manager.php#L73-L80
		 */
		$i->addTransaction(T::TYPE_PAYMENT);
	}

	/**
	 * 2016-08-27
	 * @param string $k
	 * @param string|null $d [optional]
	 * @param bool $required [optional]
	 * @return mixed
	 */
	final protected function cv($k = null, $d = null, $required = true) {
		// 2017-01-02
		// Если задано $d (значение по умолчанию),
		// то мы не требуем обязательности присутствия ключа $k.
		$k = $this->c($k ?: df_caller_f(), $required && is_null($d));
		return $k ? $this->req($k) : $d;
	}

	/**
	 * 2016-12-30
	 * @used-by \Df\PaypalClone\Webhook::logTitleSuffix()
	 * @param string|null $k [optional]
	 * @param string|null $d [optional]
	 * @return mixed
	 */
	final protected function cvo($k = null, $d = null) {return $this->cv($k ?: df_caller_f(), $d, false);}

	/**
	 * 2017-01-02
	 * @used-by \Dfe\AllPay\Webhook::test()
	 * @param string|null $k [optional]
	 * @param mixed|null $d [optional]
	 * @return array(string => mixed)|mixed|null
	 */
	final protected function extra($k = null, $d = null) {return dfak($this->_extra, $k, $d);}

	/**
	 * 2016-07-20
	 * 2017-01-04
	 * Внутренний полный идентификатор текущей транзакции.
	 * Он используется лишь для присвоения его транзакции
	 * (чтобы в будущем мы смогли найти эту транзакцию по её идентификатору).
	 * @used-by ii()
	 * @see \Dfe\AllPay\Webhook\Offline::id()
	 * @return string
	 */
	protected function id() {return $this->e2i($this->externalId());}

	/**
	 * 2016-07-10
	 * @used-by \Df\PaypalClone\Confirmation::capture()
	 * 2017-01-04
	 * Добавил возможность возвращения null:
	 * такое происходит, например, когда мы проводим тестовый платёж на локальном компьютере,
	 * а платёжная система присылает оповещение на наш сайт mage2.pro/sandbox
	 * В такой ситуации не стоит падать с искючительной ситуацией,
	 * а лучше просто ответить: «The event is not for our store».
	 * Так и раньше вели себя мои Stripe-подобные модули,
	 * теперь же я распространил такое поведение на все мои платёжные модули.
	 * @return IOP|OP|null
	 */
	final protected function ii() {return dfc($this, function() {return
		dfp_by_trans($this->tParent())
	;});}

	/**
	 * 2016-07-10
	 * @used-by \Df\PaypalClone\Confirmation::_handle()
	 * @return Order|DfOrder
	 */
	final protected function o() {return dfc($this, function() {
		/** @var Order|DfOrder $result */
		$result = $this->tParent()->getOrder();
		/**
		 * 2016-03-26
		 * Иначе будет создан новый объект payment.
		 * @used-by \Magento\Sales\Model\Order::getPayment()
		 */
		$result[IO::PAYMENT] = $this->ii();
		return $result;
	});}

	/**
	 * 2017-01-04
	 * @used-by handle()
	 * @see \Dfe\AllPay\Webhook::resultSuccess()
	 * @return Result
	 */
	protected function resultNotForUs() {return Text::i('The event is not for our store.');}

	/**
	 * 2016-08-27
	 * @used-by handle()
	 * @see \Dfe\AllPay\Webhook::resultSuccess()
	 * @see \Df\StripeClone\Webhook\NotImplemented::resultSuccess()
	 * @return Result
	 */
	protected function resultSuccess() {return Text::i('success');}

	/**
	 * 2016-12-25
	 * @return S
	 */
	final protected function ss() {return dfc($this, function() {return S::conventionB(static::class);});}

	/**
	 * 2016-07-19
	 * @return Store
	 */
	final protected function store() {return $this->o()->getStore();}

	/**
	 * 2017-01-02
	 * @used-by req()
	 * @see \Dfe\AllPay\Webhook::test()
	 * @return bool
	 */
	protected function test() {return !!$this->extra('test');}

	/**
	 * 2016-12-26
	 * @used-by log()
	 * @see \Dfe\AllPay\Webhook::typeLabel()
	 * @return string
	 */
	protected function typeLabel() {return $this->type();}

	/**
	 * 2016-08-27
	 * @used-by cv()
	 * @used-by statusExpected()
	 * @param string|null $k [optional]
	 * @param bool $required [optional]
	 * @return mixed|null
	 */
	protected function c($k = null, $required = true) {return
		dfc($this, function($k, $required = true) {
			/** @var mixed|null $result */
			$result = dfa($this->configCached(), $k);
			if ($required) {
				static::assertKeyIsDefined($k, $result);
			}
			return $result;
		}, [$k ?: df_caller_f(), $required])
	;}

	/**
	 * 2017-01-02
	 * @used-by \Df\Payment\Webhook::log()
	 * @see \Df\PaypalClone\Webhook::logTitleSuffix()
	 * @return string|null
	 */
	protected function logTitleSuffix() {return null;}

	/**
	 * 2016-08-27
	 * @used-by c()
	 * @return array(string => mixed)
	 */
	private function configCached() {return dfc($this, function() {return $this->config();});}

	/**
	 * 2016-12-26
	 * @used-by handle()
	 * @used-by resultError()
	 * @param \Exception|null $e [optional]
	 * @return void
	 */
	private function log(\Exception $e = null) {
		/** @var string $data */
		$data = df_json_encode_pretty($this->req());
		/** @var string $method */
		$code = dfp_method_code($this);
		/** @var string $title */
		$title = dfp_method_title($this);
		/** @var \Exception|string $v */
		/** @var string $suffix */
		if ($e) {
			list($v, $suffix) = [$e, 'exception'];
		}
		else {
			/** @var string $type */
			$type = $this->typeLabel();
			$v = df_ccc(': ', sprintf("[%s] {$type}", $title), $this->logTitleSuffix());
			$suffix = df_fs_name($type);
		}
		df_sentry_m()->user_context(['id' => $title]);
		df_sentry($v, [
			'extra' => ['Payment Data' => $data, 'Payment Method' => $title]
			,'tags' => ['Payment Method' => $title]
		]);
		df_report(df_ccc('--', "mage2.pro/$code-{date}--{time}", $suffix) .  '.log', $data);
	}

	/**
	 * 2016-08-14
	 * @return Method
	 */
	private function m() {return dfc($this, function() {return
		df_ar($this->ii()->getMethodInstance(), Method::class)
	;});}

	/**
	 * 2016-07-10
	 * @used-by addTransaction()
	 * @used-by tParent()
	 * @return string
	 */
	private function parentId() {return dfc($this, function() {return $this->e2i($this->parentIdRaw());});}

	/**
	 * 2016-07-12
	 * @used-by \Df\Payment\Webhook::__construct()
	 * @return array(string => string)
	 */
	private function testData() {
		/** @var string $module */
		$module = df_module_name_short($this);
		/** @var string $file */
		$file = BP . df_path_n_real("/_my/test/{$module}/{$this->testDataFile()}.json");
		if (!file_exists($file)) {
			df_error("Please place the webhook's test data to the «%s» file.", $file);
		}
		return df_json_decode(file_get_contents($file));
	}

	/**
	 * 2016-07-10
	 * 2016-12-30
	 * Возвращает транзакцию Magento, породившую данное оповещение от платёжной системы (webhook event).
	 * В то же время не каждое оповещение от платёжной системы инициируется запросом от Magento:
	 * например, оповещение могло быть инициировано некими действиями администратора магазина
	 * в административном интерфейсе магазина в платёжной системе.
	 * Однако первичная транзакция всё равно должна в Magento присутствовать.
	 * @used-by ii()
	 * @used-by o()
	 * @used-by parentInfo()
	 * @return T
	 */
	private function tParent() {return dfc($this, function() {return df_transx($this->parentId());});}

	/**
	 * 2017-01-02
	 * @used-by __construct()
	 * @used-by extra()
	 * @var array(string => mixed)
	 */
	private $_extra;

	/**
	 * 2017-01-02
	 * @used-by __construct()
	 * @used-by req()
	 * @var array(string => mixed)
	 */
	private $_req;

	/**
	 * 2016-08-27
	 * @used-by handle()
	 * @used-by \Df\Payment\Action\Webhook::error()
	 * @see \Dfe\AllPay\Webhook::resultError()
	 * @param \Exception $e
	 * @return Text
	 */
	public static function resultError(\Exception $e) {return
		Text::i(df_lets($e))->setHttpResponseCode(500)
	;}

	/**
	 * 2016-08-27
	 * 2016-12-31
	 * Название ключа в сообщении от платёжной системы,
	 * содержащего идентификатор платежа в платёжной системе.
	 * @used-by externalId()
	 * @var string
	 */
	protected static $externalIdKey = 'externalIdKey';

	/**
	 * 2016-08-27
	 * @var string
	 */
	protected static $needCapture = 'needCapture';

	/**
	 * 2016-12-30
	 * @used-by c()
	 * @param string $key
	 * @param mixed $value
	 * @throws DFE
	 */
	private static function assertKeyIsDefined($key, $value) {
		if (is_null($value)) {
			df_error("The class %s should define a value for the parameter «%s».",
				static::class, $key
			);
		}
	}
}