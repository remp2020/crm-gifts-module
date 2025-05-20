<?php

namespace Crm\GiftsModule\Tests;

use Crm\GiftsModule\Events\GiftPaymentStatusChangeHandler;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Models\Auth\UserManager;
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
