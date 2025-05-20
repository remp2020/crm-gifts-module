<?php

namespace Crm\GiftsModule\Events;

use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Utils\DateTime;

class CreateGiftCouponNewPaymentEventHandler extends AbstractListener
{
    private $paymentGiftCouponsRepository;

    private $paymentMetaRepository;

    private $paymentsRepository;

    public function __construct(
        PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        PaymentMetaRepository $paymentMetaRepository,
        PaymentsRepository $paymentsRepository,
    ) {
        $this->paymentGiftCouponsRepository = $paymentGiftCouponsRepository;
        $this->paymentMetaRepository = $paymentMetaRepository;
        $this->paymentsRepository = $paymentsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof NewPaymentEvent) {
            throw new \Exception('unexpected type of event, NewPaymentEvent expected: ' . get_class($event));
        }
        $payment = $event->getPayment();
        if (!$payment) {
            throw new \Exception('NewPaymentEvent without payment');
        }

        // create gift coupon entry
        $giftData = $this->paymentMetaRepository->values($payment, 'gift', 'gift_email', 'gift_starts_at')->fetchPairs('key', 'value');
        if (isset($giftData['gift']) && $giftData['gift'] && isset($giftData['gift_email']) && isset($giftData['gift_starts_at'])) {
            $subscriptionTypeID = $payment->subscription_type_id;
            // remove payment's `subscription_type_id` - we don't want to create subscription for buyer
            $this->paymentsRepository->update($payment, ['subscription_type_id' => null]);

            $this->paymentGiftCouponsRepository->add(
                $payment->id,
                $giftData['gift_email'],
                DateTime::createFromFormat(DateTime::RFC3339, $giftData['gift_starts_at']),
                null,
                $subscriptionTypeID,
            );
        }
    }
}
