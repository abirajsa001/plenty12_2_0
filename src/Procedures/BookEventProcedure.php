<?php
/**
 * This file is used for handling the payment refund event procedure
 *
 * @author       Novalnet AG
 * @copyright(C) Novalnet
 * @license      https://www.novalnet.de/payment-plugins/kostenlos/lizenz
 */
namespace Novalnet\Procedures;

use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\SettingsService;
use Novalnet\Services\PaymentService;
use Novalnet\Constants\NovalnetConstants;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Helper\Services\WebstoreHelper;
use Plenty\Plugin\Log\Loggable;

/**
 * Class BookEventProcedure
 *
 * @package Novalnet\Procedures
 */
class BookEventProcedure
{
    use Loggable;

    /**
     * @var PaymentRepositoryContract
     */
    private $paymentRepository;

    /**
     *
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var SettingsService
    */
    private $settingsService;

    /**
     *
     * @var PaymentService
     */
    private $paymentService;

    /**
     *
     * @var Basket
     */
    private $basket;

    /**
     * @var FrontendSessionStorageFactoryContract
     */
    private $sessionStorage;

    /**
     * @var WebstoreHelper
     */
    private $webstoreHelper;

    /**
     * Constructor.
     *
     * @param PaymentRepositoryContract $paymentRepository
     * @param PaymentHelper $paymentHelper
     * @param SettingsService $settingsService
     * @param PaymentService $paymentService
     * @param WebstoreHelper $webstoreHelper
     * @param Basket $basket
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     */
    public function __construct(PaymentRepositoryContract $paymentRepository,
                                PaymentHelper $paymentHelper,
                                SettingsService $settingsService,
                                PaymentService $paymentService,
                                Basket $basket,
                                FrontendSessionStorageFactoryContract $sessionStorage,
                                WebstoreHelper $webstoreHelper)
    {
        $this->paymentRepository = $paymentRepository;
        $this->paymentHelper     = $paymentHelper;
        $this->settingsService   = $settingsService;
        $this->paymentService    = $paymentService;
        $this->basket            = $basket;
        $this->sessionStorage    = $sessionStorage;
        $this->webstoreHelper    = $webstoreHelper;
    }

    /**
     * @param EventProceduresTriggered $eventTriggered
     *
     */
    public function run(EventProceduresTriggered $eventTriggered)
    {
        try {
            /* @var $order Order */
            $order = $eventTriggered->getOrder();
            $parentOrderId = $order->id;
            // Checking order type and set the parent and child Order Id
            if($order->typeId == OrderType::TYPE_CREDIT_NOTE) {
                foreach($order->orderReferences as $orderReference) {
                    $parentOrderId = $orderReference->originOrderId;
                    $childOrderId = $orderReference->orderId;
                }
            }
            // Get the payment details
            $paymentDetails = $this->paymentRepository->getPaymentsByOrderId($parentOrderId);
            // Get the payment currency
            foreach($paymentDetails as $paymentDetail) {
                $paymentCurrency = $paymentDetail->currency;
            }
            // Get the proper order amount even the system currency and payment currency are differ
            if(count($order->amounts) > 1) {
                foreach($order->amounts as $amount) {
                    if($paymentCurrency == $amount->currency) { 
                       $refundAmount = (float) $amount->invoiceTotal; // Get the refunding amount
                    }
                }
            } else {
                 $refundAmount = (float) $order->amounts[0]->invoiceTotal; // Get the refunding amount
            }
            // Load the order language
            foreach($order->properties as $orderProperty) {
                if($orderProperty->typeId == '6' ) {
                $orderLanguage = $orderProperty->value;
                }
            }
            // Get necessary information for the refund process
            $transactionDetails = $this->paymentService->getDetailsFromPaymentProperty($parentOrderId);
            $this->getLogger(__METHOD__)->error('Booking Event Procedure', [
                'bookTransactionDetails' => $transactionDetails,
                'paymentDetails' => $paymentDetails,
                'order' => $order,
                'invoiceTotal' => (float) $order->amounts[0]->invoiceTotal
            ]);

     // Get the customer billing and shipping details
     $billingAddressId = $this->basket->customerInvoiceAddressId;
     $shippingAddressId = $this->basket->customerShippingAddressId;
     // Get the billing and shipping address Id from session during the reinititiate payment process
     if(empty($billingAddressId)) {
         $billingAddressId = $this->sessionStorage->getPlugin()->getValue('nnBillingAddressId');
     }
     if(empty($shippingAddressId)) {
         $shippingAddressId = $this->sessionStorage->getPlugin()->getValue('nnShippingAddressId');
     }
     $billingAddress = $this->paymentHelper->getCustomerAddress((int) $billingAddressId);

     $this->getLogger(__METHOD__)->error('Booking Payment basket details', [
        'billingAddressId'      => $billingAddressId,
        'shippingAddressId'     => $shippingAddressId,
        'initialBillingAddress' => $billingAddress,
        'basketDetails'         => $this->basket
    ]);

     $shippingAddress = $billingAddress;
     if(!empty($shippingAddressId)) {
         $shippingAddress = $this->paymentHelper->getCustomerAddress((int) $shippingAddressId);
     }

     // Get the customer name if the salutation as Person
     $customerName = $this->paymentService->getCustomerName($billingAddress);
     $this->getLogger(__METHOD__)->error('Booking Payment billing, shipping', [
        'billingAddress' => $billingAddress,
        'shippingAddress' => $shippingAddress,
        'customerName' => $customerName
    ]);
     // Get the customerId
     $account = pluginApp(AccountService::class);
     $customerId = $account->getAccountContactId();

     // Get the testMode value
     $paymentKeyLower = strtolower((string) $paymentKey);

     /** @var \Plenty\Modules\Frontend\Services\VatService $vatService */
     $vatService = pluginApp(\Plenty\Modules\Frontend\Services\VatService::class);

     //we have to manipulate the basket because its stupid and doesnt know if its netto or gross
     if(!count($vatService->getCurrentTotalVats())) {
         $basket->itemSum = $basket->itemSumNet;
         $basket->shippingAmount = $basket->shippingAmountNet;
         $basket->basketAmount = $basket->basketAmountNet;
     }

     $this->getLogger(__METHOD__)->error('Booking Payment account, vat', [
        'customerId' => $customerId,
        'shippingAddress' => $shippingAddress,
        'basketAmount' => $basket->basketAmount
    ]);

        // Build the Payment Request Parameters
        $paymentRequestData = [];
        // Building the merchant Data
        $paymentRequestData['merchant'] = [
            'signature'    => $this->settingsService->getPaymentSettingsValue('novalnet_public_key'),
            'tariff'       => $this->settingsService->getPaymentSettingsValue('novalnet_tariff_id')
        ];
        // Building the customer Data
        $paymentRequestData['customer'] = [
            'first_name'   => !empty($billingAddress->firstName) ? $billingAddress->firstName : $customerName['firstName'],
            'last_name'    => !empty($billingAddress->lastName) ? $billingAddress->lastName : $customerName['lastName'],
            'gender'       => !empty($billingAddress->gender) ? $billingAddress->gender : 'u',
            'email'        => $billingAddress->email,
            'customer_no'  => !empty($customerId) ? $customerId : 'guest',
            'customer_ip'  => $this->paymentHelper->getRemoteAddress()
        ];
        if(!empty($billingAddress->phone)) { // Check if phone field is given
            $paymentRequestData['customer']['tel'] = $billingAddress->phone;
        }
        // Obtain the required billing and shipping details from the customer address object
        $billingShippingDetails = $this->paymentHelper->getBillingShippingDetails($billingAddress, $shippingAddress);
        $paymentRequestData['customer'] = array_merge($paymentRequestData['customer'], $billingShippingDetails);
        // If the billing and shipping are equal, we notify it too
        if($paymentRequestData['customer']['billing'] == $paymentRequestData['customer']['shipping']) {
            $paymentRequestData['customer']['shipping']['same_as_billing'] = '1';
        }
        if(!empty($billingAddress->companyName) && ($this->settingsService->getPaymentSettingsValue('allow_b2b_customer', $paymentKeyLower) == true)) { // Check if company field is given in the shipping address
            $paymentRequestData['customer']['billing']['company']  = $billingAddress->companyName;
        }
        if(!empty($billingAddress->state)) { // Check if state field is given in the billing address
            $paymentRequestData['customer']['billing']['state']     = $billingAddress->state;
        }
        if(!empty($shippingAddress->companyName) && ($this->settingsService->getPaymentSettingsValue('allow_b2b_customer', $paymentKeyLower) == true)) { // Check if company field is given in the shipping address
            $paymentRequestData['customer']['shipping']['company']  = $shippingAddress->companyName;
        }
        if(!empty($shippingAddress->state)) { // Check if state field is given in the shipping address
            $paymentRequestData['customer']['shipping']['state']    = $shippingAddress->state;
        }

        // Unset the shipping details if the billing and shipping details are same
        if(!empty($paymentRequestData['customer']['shipping']['same_as_billing'])) {
            unset($paymentRequestData['customer']['shipping']);
            $paymentRequestData['customer']['shipping']['same_as_billing'] = '1';
        }
        // Building the transaction Data
        $paymentRequestData['transaction'] = [
            'test_mode'         => ($this->settingsService->getPaymentSettingsValue('test_mode', $paymentKeyLower) == true) ? 1 : 0,
            'amount'            => !empty($orderAmount) ? $orderAmount : $this->paymentHelper->convertAmountToSmallerUnit($basket->basketAmount),
            'currency'          => !empty($this->sessionStorage->getPlugin()->getValue('orderCurrency')) ? $this->sessionStorage->getPlugin()->getValue('orderCurrency') : $basket->currency,
            'system_name'       => 'Plentymarkets',
            'system_version'    => NovalnetConstants::PLUGIN_VERSION,
            'system_url'        => $this->webstoreHelper->getCurrentWebstoreConfiguration()->domainSsl,
            'system_ip'         => $_SERVER['SERVER_ADDR']
        ];


        $this->getLogger(__METHOD__)->error('Booking Payment Request', [
            'requestData' => $paymentRequestData,
        ]);
        $privateKey = $this->settingsService->getPaymentSettingsValue('novalnet_private_key');
        $paymentResponseData = $this->paymentHelper->executeCurl($paymentRequestData, NovalnetConstants::PAYMENT_URL, $privateKey);

        $this->getLogger(__METHOD__)->error('Booking Payment Response', [
            'responseData' => $paymentResponseData,
        ]);

        $paymentResponseData['bookingText'] = sprintf($this->paymentHelper->getTranslatedText('refund_message_new_tid', $orderLanguage));

        // Insert the refund details into Novalnet DB
        $this->paymentService->insertPaymentResponse($paymentResponseData);

        // Get the Novalnet payment methods Id
        $mop = $this->paymentHelper->getPaymentMethodByKey(strtoupper($transactionDetails['paymentName']));
        $paymentResponseData['mop'] = $mop[0];
        // Create the payment to the plenty order
        $this->paymentHelper->createPlentyPayment($paymentResponseData);

        } catch(\Exception $e) {
            $this->getLogger(__METHOD__)->error('Novalnet::Refund failed ' . $transactionDetails['order_no'], $e);
        }
    }
}
