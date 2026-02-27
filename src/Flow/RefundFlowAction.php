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
use Plenty\Plugin\Log\Loggable;

class RefundFlowAction implements FlowActionContract
{
    use Loggable;

    public function getIdentifier(): string
    {
        return 'Novalnet.refund';
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
        $this->getLogger(__METHOD__)->error('FlowAction run function triggered', [
            'orderId' => $orderId
        ]);
        if (!$orderId) {
            $this->getLogger(__METHOD__)->error('FlowAction orderId not found', [
                'orderId' => $orderId
            ]);
            return;
        }
    
        $orderRepo = pluginApp(OrderRepositoryContract::class);
        $order = $orderRepo->findOrderById($orderId);
        $this->getLogger(__METHOD__)->error('FlowAction order details fetched', [
            'order' => $order
        ]);
        pluginApp(\Novalnet\Procedures\RefundEventProcedure::class)
            ->run($order);
    }

}
    
    
