<?php
/**
 * This file is used for registering the Novalnet payment methods
 * and Event procedures
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Plugin\Log\Loggable;

class EventProcedureServiceProvider extends ServiceProvider
{
    use Loggable;

    public function boot(EventProceduresService $service)
    {
        $this->getLogger(__METHOD__)->error('EventProcedureServiceProvider Triggered', [
            'service' => $service
        ]);
        $service->registerProcedure(
            'novalnetRefund',
            EventType::ORDER,
            [
                'de' => 'Zahlung Rückerstattung ausführen',
                'en' => 'Execute Payment Refund'
            ],
            '\Novalnet\Procedures\RefundProcedure@run'
        );
    }
}