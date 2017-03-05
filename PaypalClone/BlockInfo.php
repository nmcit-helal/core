<?php
namespace Df\PaypalClone;
/**
 * 2016-08-29
 * @method Method m()
 * @see \Dfe\AllPay\Block\Info
 * @see \Dfe\SecurePay\Block\Info
 */
abstract class BlockInfo extends \Df\Payment\Block\Info {
	/**
	 * 2016-11-17
	 * @override
	 * @see \Df\Payment\Block\Info::isWait()
	 * @used-by \Df\Payment\Block\Info::_prepareSpecificInformation()
	 * @return bool
	 */
	protected function isWait() {return parent::isWait() || !$this->responseF();}

	/**
	 * 2016-07-18
	 * @param string|null $k [optional]
	 * @return Webhook|string|null
	 */
	protected function responseF($k = null) {return $this->m()->responseF($k);}

	/**
	 * 2016-07-18
	 * @param string|null $key [optional]
	 * @return Webhook|string|null
	 */
	protected function responseL($key = null) {return $this->m()->responseL($key);}
}