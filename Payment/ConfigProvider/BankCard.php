<?php
namespace Df\Payment\ConfigProvider;
use Df\Payment\Settings\BankCard as S;
class BankCard extends \Df\Payment\ConfigProvider {
	/**
	 * 2016-08-22
	 * @override
	 * @see \Df\Payment\ConfigProvider::config()
	 * @used-by \Df\Payment\ConfigProvider::getConfig()
	 * @return array(string => mixed)
	 */
	protected function config() {return [
		'prefill' => $this->s()->prefill(), 'savedCards' => $this->savedCards()
	] + parent::config();}

	/**
	 * 2016-11-10
	 * @override
	 * @see \Df\Payment\ConfigProvider::s()
	 * @return S
	 */
	protected function s() {return dfc($this, function() {return df_ar(parent::s(), S::class);});}

	/**
	 * 2016-08-22
	 * @used-by \Df\Payment\ConfigProvider\BankCard::config()
	 * @return array(string => string)
	 */
	protected function savedCards() {return [];}
}