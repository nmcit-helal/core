<?php
namespace Df\Sales\Model\Order;
use Df\Sales\Model\Order\Invoice as DfInvoice;
use Magento\Sales\Api\Data\OrderInterface as IO;
use Magento\Sales\Model\Order as O;
use Magento\Sales\Model\Order\Payment as OP;
use Magento\Sales\Model\Order\Creditmemo;
/**
 * 2016-03-27
 * @method Creditmemo getCreatedCreditmemo()
 *
 * 2016-07-15
 * @method Invoice|DfInvoice|null getCreatedInvoice()
 *
 * 2016-05-09
 * @method string|null getRefundTransactionId()
 * https://github.com/magento/magento2/blob/ffea3cd/app/code/Magento/Sales/Model/Order/Payment.php#L652
 */
class Payment extends OP {
	/**
	 * 2017-02-09
	 * Код страны, выпустившей банковскую карту.
	 * @used-by \Dfe\Paymill\Facade\Charge::card()
	 * https://github.com/mage2pro/paymill/blob/0.2.0/Method.php?ts=4#L37-L39
	 */
	const COUNTRY = 'country';

	/**
	 * 2016-03-27
	 * https://mage2.pro/t/1031
	 * The methods
	 * @see \Magento\Sales\Model\Order\Payment\Operations\AbstractOperation::getInvoiceForTransactionId()
	 * and @see \Magento\Sales\Model\Order\Payment::_getInvoiceForTransactionId()
	 * duplicate almost the same code
	 * @param IO|O $order
	 * @param int $transactionId.
	 * @return Invoice|null
	 */
	static function getInvoiceForTransactionId(IO $order, $transactionId) {
		/** @var Payment $i */
		$i = df_om()->create(__CLASS__);
		$i->setOrder($order);
		return $i->_getInvoiceForTransactionId($transactionId);
	}

	/**
	 * 2016-05-08
	 * @param OP $op
	 * @param string $action
	 * @param O $order
	 * @return void
	 */
	static function processActionS(OP $op, $action, O $order) {
		$op->processAction($action, $order);
	}

	/**
	 * 2016-05-08
	 * @param OP $op
	 * @param O $order
	 * @param string $orderState
	 * @param string $orderStatus
	 * @param bool $isCustomerNotified
	 * @return void
	 */
	static function updateOrderS(OP $op, O $order, $orderState, $orderStatus, $isCustomerNotified) {
		$op->updateOrder($order, $orderState, $orderStatus, $isCustomerNotified);
	}
}