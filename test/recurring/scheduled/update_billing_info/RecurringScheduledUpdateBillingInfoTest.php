<?php

namespace Cardpay\recurring\scheduled\update_billing_info;

use Cardpay\ApiException;
use Cardpay\model\FilingRecurringData;
use Cardpay\model\FilingRequest;
use Cardpay\model\FilingRequestMerchantOrder;
use Cardpay\model\FilingRequestSubscriptionData;
use Cardpay\model\PaymentCreationResponse;
use Cardpay\model\PaymentRequestCard;
use Cardpay\model\PaymentRequestCardAccount;
use Cardpay\model\Request;
use Cardpay\recurring\scheduled\RecurringScheduledTestCase;
use Cardpay\test\Config;
use Constants;
use GuzzleHttp\Exception\GuzzleException;

class RecurringScheduledUpdateBillingInfoTest extends RecurringScheduledTestCase
{
    public function __construct()
    {
        parent::__construct(Config::GATEWAY_TERMINAL_CODE_PROCESS_IMMEDIATELY, Config::GATEWAY_PASSWORD_PROCESS_IMMEDIATELY);
    }

    /**
     * @throws ApiException
     */
    public function testLinkACardToAnExistingSubscription()
    {
        $orderId = microtime();

        // create new plan
        $recurringPlanResponse = $this->recurringPlanUtils->createPlan($this->terminalCode, $this->password);
        $planId = $recurringPlanResponse->getPlanData()->getId();

        self::assertNotEmpty($planId);

        // create scheduled subscription
        $recurringResponse = $this->recurringScheduledUtils
            ->createScheduledSubscriptionInGatewayMode(time(), $planId);

        $this->subscriptionId = $recurringResponse->getRecurringData()->getSubscription()->getId();
        self::assertNotEmpty($this->subscriptionId);

        $filingId = $recurringResponse->getRecurringData()->getFiling()->getId();
        self::assertNotEmpty($filingId);

        // create new card binding
        $request = new Request([
            'id' => microtime(true),
            'time' => date(Constants::DATETIME_FORMAT)
        ]);

        $merchantOrder = new FilingRequestMerchantOrder([
            'id' => $orderId,
            'description' => 'Order description'
        ]);

        $recurringData = new FilingRecurringData([
            'initiator' => Constants::INITIATOR_CIT
        ]);

        $subscriptionData = new FilingRequestSubscriptionData([
            'id' => $this->subscriptionId
        ]);

        $card = new PaymentRequestCard([
            'pan' => Constants::TEST_CARD_PAN,
            'holder' => Constants::TEST_CARD_HOLDER,
            'security_code' => Constants::TEST_CARD_SECURITY_CODE,
            'expiration' => Constants::TEST_CARD_EXPIRATION
        ]);

        $cardAccount = new PaymentRequestCardAccount([
            'card' => $card
        ]);

        $filingRequest = new FilingRequest([
            'request' => $request,
            'merchant_order' => $merchantOrder,
            'payment_method' => Constants::PAYMENT_METHOD,
            'recurring_data' => $recurringData,
            'subscription_data' => $subscriptionData,
            'card_account' => $cardAccount
        ]);

        /** @var PaymentCreationResponse $paymentCreationResponse */
        $paymentCreationResponse = $this->recurringScheduledUtils
            ->getRecurringsApi()
            ->createFiling($filingRequest);

        $redirectUrl = $paymentCreationResponse->getRedirectUrl();
        self::assertNotEmpty($redirectUrl);

        try {
            $this->recurringScheduledUtils->getClient()->request('GET', $redirectUrl);
        } catch (GuzzleException $e) {
            throw new ApiException($e->getMessage());
        }
    }
}