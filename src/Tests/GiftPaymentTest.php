<?php

namespace Crm\GiftsModule\Tests;

use Crm\GiftsModule\Events\GiftPaymentStatusChangeHandler;
use Crm\GiftsModule\GiftsModule;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Utils\DateTime;

class GiftPaymentTest extends BaseTestCase
{
    private $paymentGateway;
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;
    private UserManager $userManager;
    private PaymentsRepository $paymentsRepository;

    protected function requiredRepositories(): array
    {
        return array_unique([
            SubscriptionsRepository::class,
            PaymentGiftCouponsRepository::class,
            ...parent::requiredRepositories(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        $this->paymentGateway = $pgr->add('test', 'test', 10, true, true);

        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $this->emitter->addListener(
            PaymentChangeStatusEvent::class,
            $this->inject(GiftPaymentStatusChangeHandler::class),
        );

        /** @var GiftPaymentStatusChangeHandler $giftPaymentStatusChangeHandler */
        $giftPaymentStatusChangeHandler = $this->inject(GiftPaymentStatusChangeHandler::class);
        // Avoid attaching PDF coupon
        $giftPaymentStatusChangeHandler->disableAttachment();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->emitter->removeAllListeners(GiftPaymentStatusChangeHandler::class);
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
        $this->assertEquals('2040-01-01T00:00:00+01:00', $params['gift_starts_at']);
    }

    public function testGiftPaymentRefundStopsActivatedSubscription()
    {
        $buyer = $this->userManager->addNewUser('buyer@example.com', false, 'unknown', null, false);
        $recipient = $this->userManager->addNewUser('recipient@example.com', false, 'unknown', null, false);

        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();

        // Create gift payment and mark as paid
        $payment = $this->addGiftPayment($buyer, $subscriptionType, 'recipient@example.com', new DateTime('2020-01-01 00:00:00'));

        // Simulate gift coupon activation (normally done by ActivatePurchasedGiftCouponsCommand):
        // create subscription for the recipient and link it to the gift coupon
        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $subscription = $subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $recipient,
            GiftsModule::SUBSCRIPTION_TYPE_GIFT,
            new DateTime(),
        );

        /** @var PaymentGiftCouponsRepository $paymentGiftCouponsRepository */
        $paymentGiftCouponsRepository = $this->getRepository(PaymentGiftCouponsRepository::class);
        $giftCoupon = $paymentGiftCouponsRepository->findByPayment($payment)->fetch();
        $this->assertNotNull($giftCoupon, 'Gift coupon should exist for the payment');

        $paymentGiftCouponsRepository->update($giftCoupon, [
            'status' => PaymentGiftCouponsRepository::STATUS_SENT,
            'sent_at' => new DateTime(),
            'subscription_id' => $subscription->id,
        ]);

        // Verify subscription is active (end_time in the future)
        $subscription = $subscriptionsRepository->find($subscription->id);
        $this->assertGreaterThan(new DateTime(), $subscription->end_time);

        // Refund the payment
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Refund->value);

        // Verify the gift subscription was stopped (end_time should now be <= now)
        $subscription = $subscriptionsRepository->find($subscription->id);
        $this->assertLessThanOrEqual(new DateTime(), $subscription->end_time);
    }

    public function testGiftPaymentRefundBeforeActivationDoesNotFail()
    {
        $buyer = $this->userManager->addNewUser('buyer2@example.com', false, 'unknown', null, false);

        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();

        // Create gift payment with future start date (not yet activated)
        $payment = $this->addGiftPayment($buyer, $subscriptionType, 'future-recipient@example.com', new DateTime('2040-01-01 00:00:00'));

        /** @var PaymentGiftCouponsRepository $paymentGiftCouponsRepository */
        $paymentGiftCouponsRepository = $this->getRepository(PaymentGiftCouponsRepository::class);
        $giftCoupon = $paymentGiftCouponsRepository->findByPayment($payment)->fetch();
        $this->assertNotNull($giftCoupon);
        $this->assertNull($giftCoupon->subscription_id, 'Gift coupon should not have subscription_id before activation');

        // Refund the payment - should not throw any errors
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Refund->value);

        // Gift coupon still exists but has no subscription to stop
        $giftCoupon = $paymentGiftCouponsRepository->findByPayment($payment)->fetch();
        $this->assertNotNull($giftCoupon);
        $this->assertNull($giftCoupon->subscription_id);
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
                'gift_starts_at'=> $giftStartsAt->format(DateTime::RFC3339),
            ],
        );

        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $this->paymentsRepository->update($payment, ['paid_at' => new DateTime()]);
        return $this->paymentsRepository->find($payment->id);
    }
}
