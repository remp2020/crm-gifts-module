<?php

namespace Crm\GiftsModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class PaymentGiftCouponsRepository extends Repository
{
    const STATUS_NOT_SENT = 'not_sent';
    const STATUS_SENT = 'sent';

    const USER_SOURCE_GIFT_COUPON = 'gift_coupon';

    protected $tableName = 'payment_gift_coupons';

    final public function add(
        int $paymentID,
        string $email,
        DateTime $startsAt,
        ?int $productID = null,
        ?int $subscriptionTypeID = null
    ) {
        return $this->insert([
            'payment_id' => $paymentID,
            'product_id' => $productID,
            'subscription_type_id' => $subscriptionTypeID,
            'email' => $email,
            'starts_at' => $startsAt,
            'status' => self::STATUS_NOT_SENT
        ]);
    }

    final public function getAllNotSentAndActive()
    {
        return $this->getTable()
            ->where(['status' => self::STATUS_NOT_SENT])
            ->where('starts_at <= ?', new DateTime());
    }

    final public function findAllBySubscriptions(array $subscriptonsIDs)
    {
        return $this->getTable()->where(['subscription_id' => $subscriptonsIDs])->fetchAll();
    }

    final public function findByPayment($payment)
    {
        return $this->getTable()->where(['payment_id' => $payment->id]);
    }
}
