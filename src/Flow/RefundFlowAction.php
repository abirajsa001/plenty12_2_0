<?php
/**
 * This file is used for retrieve the details from the  shop instance
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Flow;

use Plenty\Modules\Flow\Contracts\FlowActionContract;
use Plenty\Modules\Flow\Models\FlowAction;
use Plenty\Modules\Order\Repositories\OrderRepositoryContract;

class RefundFlowAction implements FlowActionContract
{
    public function getIdentifier(): string
    {
        return 'yourplugin.refund';
    }

    public function getName(): string
    {
        return 'Execute Payment Refund';
    }
    
    public function getDescription(): string
    {
        return 'Triggers refund via payment provider';
    }
    
    public function getAllowedObjectTypes(): array
    {
        return ['order'];
    }
    
    public function run(FlowAction $flowAction, array $context)
    {
        $orderId = $context['orderId'] ?? null;
    
        if (!$orderId) {
            return;
        }
    
        $orderRepo = pluginApp(OrderRepositoryContract::class);
        $order = $orderRepo->findOrderById($orderId);
    
        pluginApp(\Novalnet\Services\RefundService::class)
            ->processRefund($order);
    }

}
    
    
