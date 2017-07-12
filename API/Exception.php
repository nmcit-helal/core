<?php
namespace Df\API;
/**
 * 2017-07-09
 * Unfortunately, PHP allows to throw only the @see \Exception descendants.
 * @see \Df\API\Response\Validator
 */
abstract class Exception extends \Df\Core\Exception {
	/**
	 * 2017-07-09
	 * @used-by \Df\API\Client::p()
	 * @see \Df\ZohoBI\API\Validator::long()
	 * @see \Dfe\Dynamics365\API\Validator\JSON::long()
	 * @return string
	 */
	abstract function long();

	/**
	 * 2017-07-09
	 * @used-by \Df\API\Client::p()
	 * @see \Df\ZohoBI\API\Validator::short()
	 * @see \Dfe\Dynamics365\API\Validator\JSON::short()
	 * @return string
	 */
	abstract function short();
}