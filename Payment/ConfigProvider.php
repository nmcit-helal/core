<?php
namespace Df\Payment;
use Df\Payment\Settings as S;
use Magento\Checkout\Model\ConfigProviderInterface;
// 2016-08-04
abstract class ConfigProvider implements ConfigProviderInterface {
	/**
	 * 2016-02-27
	 * 2016-08-24
	 * Этот метод вызывается не только на странице оформления заказа, но и на странице корзицы.
	 * Однако нам на странице корзины не нужно вычислять настройки наших способов оплаты:
	 * ведь они там не отображаются, а вычисление настрое расходует ресурсы:
	 * в частности, мой модуль Stripe при этом делает 2 запроса к API Stripe.
	 * Поэтому на странице корзины ничего не делаем:
	 * Magento потом всё равно вызовет этот метод повторно на странице оформления заказа.
	 *
	 * Обратите внимание, что оформление заказа состоит из нескольких шагов,
	 * но переключение между ними происходит без перезагрузки страницы,
	 * поэтому этот метод вызывается лишь единожды на самом первом шаге
	 * (обычно это шаг выбора адреса и способа доставки).
	 *
	 * Обеспечиваем наличие ключа «payment»,
	 * чтобы не приходилось проверять его наличие на стороне JavaScript.
	 * @used-by \Magento\Checkout\Model\CompositeConfigProvider::getConfig()
	 *
	 * @override
	 * @see \Magento\Checkout\Model\ConfigProviderInterface::getConfig()
	 * https://github.com/magento/magento2/blob/cf7df72/app/code/Magento/Checkout/Model/ConfigProviderInterface.php#L15-L20
	 * @return array(string => mixed)
	 */
	final public function getConfig() {return ['payment' =>
		!df_is_checkout() || !$this->s()->enable() ? [] : [dfp_method_code($this) => $this->config()]
	];}

	/**
	 * 2016-08-04
	 * @used-by \Df\Payment\ConfigProvider::getConfig()
	 * @return array(string => mixed)
	 */
	protected function config() {return [
		'askForBillingAddress' => $this->s()->askForBillingAddress()
		,'isTest' => $this->s()->test()
		,'route' => df_route($this)
		,'titleBackend' => dfp_method_call_s($this, 'titleBackendS')
	];}

	/**
	 * 2016-08-27
	 * @return S
	 */
	protected function s() {
		if (!isset($this->{__METHOD__})) {
			$this->{__METHOD__} = S::convention($this);
		}
		return $this->{__METHOD__};
	}
}