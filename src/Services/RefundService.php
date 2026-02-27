<?php
/**
 * This file is act as helper for the Novalnet payment plugin
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Services;

use Plenty\Modules\Order\Repositories\OrderRepositoryContract;
use Plenty\Modules\Payment\Repositories\PaymentRepositoryContract;
use Plenty\Modules\Payment\Services\PaymentService;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Plugin\Log\Loggable;

class RefundService
{
    use Loggable;

    const MOP_ID = 6083; // your payment method ID

    public function processRefundByOrderId(int $creditNoteId)
    {
        $this->getLogger(__METHOD__)->error('Refund Service create', [
            'creditNoteId' => $creditNoteId
        ]);

        $orderRepo = pluginApp(OrderRepositoryContract::class);
        $paymentRepo = pluginApp(PaymentRepositoryContract::class);

        // 1️⃣ Get credit note
        $creditNote = $orderRepo->findOrderById($creditNoteId);

        if (!$creditNote || $creditNote->typeId != 4) {
            return;
        }

        // 2️⃣ Get original order
        $originalOrder = $orderRepo->findOrderById(
            $creditNote->originOrderId
        );

        if (!$originalOrder) {
            return;
        }

        // 3️⃣ Validate payment method
        if ($originalOrder->methodOfPaymentId != self::MOP_ID) {
            return;
        }

        // 4️⃣ Get original payment
        $payments = $paymentRepo->getPaymentsByOrderId($originalOrder->id);

        $incoming = null;
        foreach ($payments as $payment) {
            if ($payment->type === 'incoming') {
                $incoming = $payment;
                break;
            }
        }

        if (!$incoming) {
            return;
        }

        $paidAmount = $incoming->amount;

        // 5️⃣ Calculate already refunded
        $alreadyRefunded = 0;
        foreach ($payments as $payment) {
            if ($payment->type === 'refund') {
                $alreadyRefunded += $payment->amount;
            }
        }

        $refundAmount = $creditNote->amounts[0]->invoiceTotal;

        // 6️⃣ Prevent over-refund
        if (($alreadyRefunded + $refundAmount) > $paidAmount) {
            return;
        }

        // 7️⃣ Call provider API
        $response = $this->callRefundApi(
            $incoming->transactionId,
            $refundAmount
        );

        if ($response['status'] !== 'SUCCESS') {
            return;
        }

        // 8️⃣ Create refund payment entry
        $this->createRefundPayment(
            $originalOrder->id,
            $refundAmount
        );
    }

    private function callRefundApi($transactionId, $amount)
    {
        // TODO: Replace with real API integration
        return [
            'status' => 'SUCCESS'
        ];
    }

    private function createRefundPayment($orderId, $amount)
    {
        $payment = pluginApp(Payment::class);

        $payment->mopId = self::MOP_ID;
        $payment->orderId = $orderId;
        $payment->amount = $amount;
        $payment->type = 'refund';
        $payment->status = 2; // booked

        pluginApp(PaymentService::class)->createPayment($payment);
    }
}

