<?php
declare(strict_types=1);

namespace Crm\GiftsModule\Events;

use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Events\PaymentCopiedEvent;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Exception;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class PaymentCopiedEventHandler extends AbstractListener
{
    public function __construct(
        private readonly PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        private readonly PaymentMetaRepository $paymentMetaRepository,
    ) {
    }

    public function handle(EventInterface $event): void
    {
        if (!($event instanceof PaymentCopiedEvent)) {
            throw new Exception('unexpected type of event, PaymentCopiedEvent expected: ' . get_class($event));
        }

        $sourcePayment = $event->getPayment();
        $newPayment = $event->getNewPayment();

        foreach ($this->paymentGiftCouponsRepository->findByPayment($sourcePayment) as $coupon) {
            $this->paymentGiftCouponsRepository->add(
                $newPayment->id,
                $coupon->email,
                $coupon->starts_at,
                $coupon->product_id,
                $coupon->subscription_type_id,
                $coupon->address_id,
            );
        }

        // gift_email and gift_starts_at are not copied, the copied coupon already holds them.
        $giftMeta = $this->paymentMetaRepository
            ->values($sourcePayment, 'gift', GiftSubscriptionAddressFormFactory::PAYMENT_META_KEY)
            ->fetchPairs('key', 'value');
        foreach ($giftMeta as $key => $value) {
            $this->paymentMetaRepository->add($newPayment, $key, $value);
        }
    }
}
