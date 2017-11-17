<?php
use Df\Framework\W\AbstractResult as DfResult;
use Magento\Framework\App\Action\Action as Controller;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\HttpInterface as IHttpResponse;
use Magento\Framework\App\Response\RedirectInterface as IResponseRedirect;
use Magento\Framework\App\ResponseInterface as IResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultInterface as IResult;
use Magento\Store\App\Response\Redirect as ResponseRedirect;

/**
 * 2017-05-10
 * @return Controller|null
 */
function df_controller() {return df_state()->controller();}

/**
 * 2015-11-29
 * @used-by \Df\Framework\App\Action\Image::execute()
 * @param string $contents
 * @return Raw
 */
function df_controller_raw($contents) {
	$r = df_new_om(Raw::class); /** @var Raw $r */
	return $r->setContents($contents);
}

/**
 * 2017-11-17
 * @return bool
 */
function df_is_redirect() {return df_response()->isRedirect();}

/**
 * 2017-11-16
 * I have implemented it by analogy with @see \Magento\Framework\App\Action\Action::_redirect():
 *		protected function _redirect($path, $arguments = []) {
 *			$this->_redirect->redirect($this->getResponse(), $path, $arguments);
 *			return $this->getResponse();
 *		}
 * https://github.com/magento/magento2/blob/2.2.1/lib/internal/Magento/Framework/App/Action/Action.php#L159-L170
 * @used-by df_redirect_to_payment()
 * @used-by df_redirect_to_success()
 * @param string $path
 * @param array(string => mixed) $p [optional]
 */
function df_redirect($path, $p = []) {
	$r = df_controller()->getResponse(); /** @var IResponse|HttpResponse $r */
	/** @var IResponseRedirect|ResponseRedirect $responseRedirect */
	$responseRedirect = df_o(IResponseRedirect::class);
	/**
	 * 2017-11-17
	 * @uses \Magento\Framework\App\Response\Http::setRedirect():
	 *		public function setRedirect($url, $code = 302) {
	 *			$this
	 *				->setHeader('Location', $url, true)
	 *				->setHttpResponseCode($code)
	 *			;
	 *			return $this;
	 *		}
	 * https://github.com/magento/magento2/blob/2.2.1/lib/internal/Magento/Framework/HTTP/PhpEnvironment/Response.php#L113-L122
	 *
	 * We can then check whether a redirect is set using
	 * @see \Magento\Framework\HTTP\PhpEnvironment\Response::isRedirect():
	 *		public function isRedirect() {
	 *			return $this->isRedirect;
	 *		}
	 * https://github.com/magento/magento2/blob/2.2.1/lib/internal/Magento/Framework/HTTP/PhpEnvironment/Response.php#L162-L170
	 *
	 * It does work because of the
	 * @see \Magento\Framework\HTTP\PhpEnvironment\Response::setHttpResponseCode() implementation:
	 * 		 $this->isRedirect = (300 <= $code && 307 >= $code) ? true : false;
	 * https://github.com/magento/magento2/blob/2.2.1/lib/internal/Magento/Framework/HTTP/PhpEnvironment/Response.php#L124-L137
	 */
	$responseRedirect->redirect($r, $path, $p);
}

/**
 * 2017-11-17
 * @used-by \Df\Payment\CustomerReturn::execute()
 * @used-by \Df\Payment\W\Strategy\ConfirmPending::_handle()
 */
function df_redirect_to_payment() {df_redirect('checkout', ['_fragment' => 'payment']);}

/**
 * 2017-11-17
 * @used-by \Df\Payment\CustomerReturn::execute()
 * @used-by \Df\Payment\W\Strategy\ConfirmPending::_handle()
 */
function df_redirect_to_success() {df_redirect('checkout/onepage/success');}

/**
 * 2017-02-01
 * Добавил параметр $r.
 * IResult и DfResult не родственны IResponse и HttpResponse.
 * @used-by df_is_redirect()
 * @used-by df_response_ar()
 * @used-by df_response_code()
 * @used-by df_response_content_type()
 * @param IResult|DfResult|IResponse|HttpResponse|null $r [optional]
 * @return IResponse|IHttpResponse|HttpResponse|IResult|DfResult
 */
function df_response($r = null) {return $r ?: df_o(IResponse::class);}

/**
 * 2017-02-01
 * @used-by df_response_sign()
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a1 [optional]
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a2 [optional]
 * @return array(array(string => string), IResult|DfResult|IHttpResponse|HttpResponse)
 */
function df_response_ar($a1 = null, $a2 = null) {return
	is_array($a1) ? [$a1, df_response($a2)] : (
		is_array($a2) ? [$a2, df_response($a1)] : (
			is_object($a1) ? [[], $a1] : (
				is_object($a2) ? [[], $a2] :
					[[], df_response()]
			)
		)
	)
;}

/** 2015-12-09 */
function df_response_cache_max() {df_response_headers([
	'Cache-Control' => 'max-age=315360000'
	,'Expires' => 'Thu, 31 Dec 2037 23:55:55 GMT'
	// 2015-12-09
	// Если не указывать заголовок Pragma, то будет добавлено Pragma: no-cache.
	// Так и не разобрался, кто его добавляет. Может, PHP или веб-сервер.
	// Простое df_response()->clearHeader('pragma') не позволяет от него избавиться.
	// http://stackoverflow.com/questions/11992946
	,'Pragma' => 'cache'
]);}

/**
 * 2015-11-29
 * @used-by \Df\Framework\App\Action\Image::execute()
 * @used-by \Dfe\CheckoutCom\Handler::p()
 * @used-by \Dfe\TwoCheckout\Handler::p()
 * @param int $value
 */
function df_response_code($value) {df_response()->setHttpResponseCode($value);}

/**
 * При установке заголовка HTTP «Content-Type»
 * надёжнее всегда добавлять 3-й параметр: $replace = true,
 * потому что заголовок «Content-Type» уже ранее был установлен методом
 * @used-by \Df\Framework\App\Action\Image::execute()
 * @used-by \Df\Framework\W\Response\Text::render()
 * @used-by \Dfe\Qiwi\Response::render()
 * @used-by \Dfe\YandexKassa\Response::render()
 * @param string $contentType
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null $r [optional]
 */
function df_response_content_type($contentType, $r = null) {df_response($r)->setHeader(
	'Content-Type', $contentType, true
);}

/**
 * 2015-11-29
 * 2017-02-01
 * @used-by df_response_sign()
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a1 [optional]
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a2 [optional]
 * @return IResult|DfResult|IHttpResponse|HttpResponse
 */
function df_response_headers($a1 = null, $a2 = null) {
	/** @var array(string => string) $a */
	/** @var IResult|DfResult|IHttpResponse|HttpResponse $r */
	list($a, $r) = df_response_ar($a1, $a2);
	array_walk($a, function($v, $k) use($r) {$r->setHeader($k, $v, true);});
	return $r;
}

/**
 * 2017-02-01
 * @used-by \Df\Core\Controller\Index\Index::execute()
 * @used-by \Df\Payment\W\Action::execute()
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a1 [optional]
 * @param IResult|DfResult|IHttpResponse|HttpResponse|null|array(string => string) $a2 [optional]
 * @return IResult|DfResult|IHttpResponse|HttpResponse
 */
function df_response_sign($a1 = null, $a2 = null) {
	/** @var array(string => string) $a */ /** @var IResult|DfResult|IHttpResponse|HttpResponse $r */
	list($a, $r) = df_response_ar($a1, $a2);
	return df_response_headers($r, df_headers($a));
}