<?php
/**
 * This file is used for handling the payment refund event procedure
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Procedures;

use Plenty\Modules\EventProcedures\Models\EventProcedure;

class RefundProcedure
{
    public function handle(EventProcedure $eventProcedure)
    {
        $orderId = $eventProcedure->getOrderId();

        pluginApp(\Novalnet\Services\RefundService::class)
            ->processRefundByOrderId($orderId);
    }
}