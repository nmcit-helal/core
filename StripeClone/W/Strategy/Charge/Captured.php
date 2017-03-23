<?php
namespace Df\StripeClone\W\Strategy\Charge;
use Df\Sales\Model\Order as DFO;
use Df\Sales\Model\Order\Invoice as DfInvoice;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException as LE;
use Magento\Sales\Model\Order as O;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
// 2017-01-06
final class Captured extends \Df\StripeClone\W\Strategy\Charge {
	/**
	 * 2017-01-07
	 * @override
	 * @see \Df\StripeClone\W\Strategy::_handle()
	 * @used-by \Df\StripeClone\W\Strategy::::handle()
	 * @return void
	 */
	protected function _handle() {
		/** @var O|DFO $o */
		$o = $this->o();
		// 2016-12-30
		// Мы не должны считать исключительной ситуацией повторное получение
		// ранее уже полученного оповещения.
		// В документации к Stripe, например, явно сказано:
		// «Webhook endpoints may occasionally receive the same event more than once.
		// We advise you to guard against duplicated event receipts
		// by making your event processing idempotent.»
		// https://stripe.com/docs/webhooks#best-practices
		if (!$o->canInvoice()) {
			$this->resultSet('The order does not allow an invoice to be created.');
		}
		else {
			$o->setCustomerNoteNotify(true)->setIsInProcess(true);
			/** @var Invoice $i */
			df_db_transaction()->addObject($i = $this->invoice())->addObject($o)->save();
			df_invoice_send_email($i);
			$this->resultSet($this->op()->getId());
		}
	}
	
	/**
	 * 2016-03-26
	 * @used-by _handle()
	 * @return Invoice|DfInvoice
	 * @throws LE
	 */
	private function invoice() {
		/** @var InvoiceService $invoiceService */
		$invoiceService = df_o(InvoiceService::class);
		/** @var Invoice|DfInvoice $result */
		if (!($result = $invoiceService->prepareInvoice($this->o()))) {
			throw new LE(__('We can\'t save the invoice right now.'));
		}
		if (!$result->getTotalQty()) {
			throw new LE(__('You can\'t create an invoice without products.'));
		}
		df_register('current_invoice', $result);
		/**
		 * 2016-03-26
		 * @used-by \Magento\Sales\Model\Order\Invoice::register()
		 * https://github.com/magento/magento2/blob/2.1.0/app/code/Magento/Sales/Model/Order/Invoice.php#L599-L609
		 * Используем именно @see \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE,
		 * а не @see \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFINE,
		 * чтобы была создана транзакция capture.
		 */
		$result->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
		$result->register();
		return $result;
	}
}