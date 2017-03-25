<?php
namespace Df\Sso;
use Df\Sso\Settings\Button as S;
use Df\Sso\Source\Button\Type\UNL;
use Magento\Framework\View\Element\AbstractBlock as _P;
/**
 * 2016-11-23
 * @see \Df\Sso\Button\Js
 * @see \Dfe\BlackbaudNetCommunity\Button
 */
abstract class Button extends _P {
	/**
	 * 2016-11-27
	 * @used-by attributes()
	 * @return string
	 */
	abstract protected function lHref();

	/**
	 * 2016-11-23
	 * @override
	 * @see _P::_toHtml()
	 * @used-by \Magento\Framework\View\Element\AbstractBlock::toHtml()
	 * @return string
	 */
	final protected function _toHtml() {
		/** @var string $result */
		$result =
			Css::isAccConfirmation() || Css::isRegCompletion()
			|| !self::sModule()->enable() || !$this->s()->enable() ? '' :
				(df_customer_logged_in() ? $this->loggedIn() : $this->loggedOut())
		;
		/**
		 * 2016-11-23
		 * Ссылки в шапке надо обязательно обрамлять в <li>, потому что они выводятся внутри <ul>:
		 *		$html = '<ul' . ($this->hasCssClass() ? ' class="' . $this->escapeHtml(
		 *			$this->getCssClass()
		 *		) . '"' : '') . '>';
		 *		foreach ($this->getLinks() as $link) {
		 *			$html .= $this->renderLink($link);
		 *		}
		 *		$html .= '</ul>';
		 * @see \Magento\Framework\View\Element\Html\Links::_toHtml()
		 * https://github.com/magento/magento2/blob/2.1.2/lib/internal/Magento/Framework/View/Element/Html/Links.php#L76-L82
		 */
		return df_tag_if($result, $result && $this->isInHeader(), 'li', "df-sso-li-{$this->s()->type()}");
	}

	/**
	 * 2016-11-26
	 * @used-by loggedOut()
	 * @see \Df\Sso\Button\Js::attributes()
	 * @see \Dfe\FacebookLogin\Button::attributes()
	 * @return array(string => string)
	 */
	protected function attributes() {return
		($this->isNative() ? $this->attributesN() :
			['href' => $this->lHref(), 'title' => $this->s()->label()]
		)
		+ [
			'class' => df_cc_s(
				'df-sso-button', $this->cssClass(), $this->s()->type(), $this->cssClass2()
			)
			,'id' => df_uid(4, "{$this->cssClass()}-")
			,'rel' => 'nofollow'
		]
	;}

	/**
	 * 2016-11-29
	 * @used-by attributes()
	 * @return array(string => string)
	 */
	protected function attributesN() {return [];}

	/**
	 * 2016-11-25
	 * @used-by id()
	 * @used-by attributes()
	 * @used-by \Df\Sso\Button\Js::jsOptions()
	 * @return string
	 */
	final protected function cssClass() {return dfc($this, function() {return implode(
		'-', df_explode_class_lc_camel($this)
	);});}

	/**
	 * 2016-11-29
	 * @used-by attributes()
	 * @see \Dfe\FacebookLogin\Button::cssClass2()
	 * @return string
	 */
	protected function cssClass2() {return '';}

	/**
	 * 2016-11-29
	 * @used-by attributes()
	 * @used-by \Dfe\FacebookLogin\Button::cssClass2()
	 * @return bool
	 */
	final protected function isNative() {return dfc($this, function() {return UNL::isNative(
		$this->s()->type()
	);});}

	/**
	 * 2016-11-23
	 * @used-by _toHtml()
	 * @return string
	 */
	protected function loggedIn() {return '';}

	/**
	 * 2016-11-23
	 * @see \Dfe\FacebookLogin\Button::loggedOut()
	 * @used-by _toHtml()
	 * @return string
	 */
	protected function loggedOut() {$unified = UNL::isUnified($this->s()->type()); return
		df_tag('a', $this->attributes(), df_tag_if($this->s()->label(), $unified, 'span'))
		.(!$unified ? '' : df_fa_link())
		// 2016-11-30
		// Наши кнопки больше по высоте стандартных ссылок в шапке,
		// поэтому именьшаем отступ от кнопок до низа шапки,
		// чтобы шапка не была слишком большой по высоте.
		.(!$this->isInHeader() || UNL::isLink($this->s()->type()) || self::$_inlineCssB ? '' :
			self::$_inlineCssB = df_style_inline(
				'.df-theme-luma .page-header .header.panel {padding-bottom: 0.4rem;}'
			)
		)
	;}
	
	/**
	 * 2016-11-24
	 * 2016-11-27
	 * @final I do not use the PHP «final» keyword here to allow refine the return type using PHPDoc.
	 * @return S
	 */
	protected function s() {return dfc($this, function() {return df_new(
		df_con_heir($this, S::class), $this['dfConfigPrefix']
	);});}

	/**
	 * 2016-11-30
	 * Другой алгоритм: $this->getParentBlock() instanceof Links
	 *@used-by _toHtml()
	 * @used-by loggedOut()
	 * @return string
	 */
	private function isInHeader() {return dfc($this, function() {return
		'header.links' === df_parent_name($this)
	;});}

	/**
	 * 2016-11-23
	 * Для каждого класса кнопок система будет создавать несколько экземпляров:
	 * для каждого из местоположений кнопки.
	 * Поэтому кэшируем результат вызова @uses \Df\Sso\Settings::convention() для класса
	 * (но при этом используем static::class, чтобы разные классы имели разные значения кэша).
	 * 2016-11-24
	 * Передаём static::class как аргумент, чтобы потомки этого класса имели индивидуальный кэш:
	 * https://github.com/mage2pro/core/blob/ab34df/Core/lib/cache.php?ts=4#L151-L160
	 * @return \Df\Sso\Settings
	 */
	private static function sModule() {return dfcf(function($c) {return
		Settings::convention($c)
	;}, [static::class]);}

	/**
	 * 2016-12-01
	 * https://3v4l.org/Ns95Q
	 * @used-by loggedOut()
	 * @var string
	 */
	private static $_inlineCssB;
}