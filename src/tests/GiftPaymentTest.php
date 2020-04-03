<?php

namespace Crm\GiftsModule\Tests;

use Crm\GiftsModule\Events\GiftPaymentStatusChangeHandler;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Auth\UserManager;
use Nette\Utils\DateTime;

class GiftPaymentTest extends BaseTestCase
{
    private $paymentGateway;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var UserManager */
    private $userManager;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        $this->paymentGateway = $pgr->add('test', 'test', 10, true, true);

        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        /** @var GiftPaymentStatusChangeHandler $giftPaymentStatusChangeHandler */
        $giftPaymentStatusChangeHandler = $this->inject(GiftPaymentStatusChangeHandler::class);
        // Avoid attaching PDF coupon
        $giftPaymentStatusChangeHandler->disableAttachment();
    }

    public function testGiftPayment()
    {
        $user = $this->userManager->addNewUser('test@example.com', false, 'unknown', null, false);

        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();

        $payment = $this->addGiftPayment($user, $subscriptionType, 'friend@example.com', new DateTime('2040-01-01 00:00:00'));

        $notifications = $this->notificationsSentTo('test@example.com');
        $this->assertCount(1, $notifications);

        $notification = $notifications[0];
        $params = $notification->getParams();
        $this->assertEquals($payment->variable_symbol, $params['variable_symbol']);
        $this->assertEquals('friend@example.com', $params['donated_to_email']);
    }

    private function addGiftPayment($user, $subscriptionType, $giftEmail, DateTime $giftStartsAt)
    {

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $this->paymentGateway,
            $user,
            new PaymentItemContainer(),
            null,
            1,
            null,
            null,
            null,
            0,
            null,
            null,
            null,
            false,
            [
                'gift' => 1,
                'gift_email' => $giftEmail,
                'gift_starts_at'=> $giftStartsAt->format(DateTime::RFC3339)
            ]
        );

        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
        $this->paymentsRepository->update($payment, ['paid_at' => new DateTime()]);
        return $this->paymentsRepository->find($payment->id);
    }
}
