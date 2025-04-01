<?php

namespace Crm\GiftsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class PaymentGiftCouponsRepository extends Repository
{
    const STATUS_NOT_SENT = 'not_sent';
    const STATUS_SENT = 'sent';

    const USER_SOURCE_GIFT_COUPON = 'gift_coupon';

    protected $tableName = 'payment_gift_coupons';

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function add(
        int $paymentID,
        string $email,
        DateTime $startsAt,
        ?int $productID = null,
        ?int $subscriptionTypeID = null,
        ?int $addressID = null
    ) {
        return $this->insert([
            'payment_id' => $paymentID,
            'product_id' => $productID,
            'subscription_type_id' => $subscriptionTypeID,
            'email' => $email,
            'starts_at' => $startsAt,
            'status' => self::STATUS_NOT_SENT,
            'address_id' => $addressID
        ]);
    }

    final public function getAllNotSentAndActive()
    {
        return $this->getTable()
            ->where(['payment_gift_coupons.status' => self::STATUS_NOT_SENT])
            ->where('starts_at <= ?', new DateTime())
            ->where('payment.status IN ?', [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value]);
    }

    final public function findAllBySubscriptions(array $subscriptonsIDs)
    {
        return $this->getTable()->where(['subscription_id' => $subscriptonsIDs])->fetchAll();
    }

    final public function findByPayment($payment): Selection
    {
        return $this->getTable()->where(['payment_id' => $payment->id]);
    }

    final public function findBySubscription(ActiveRow $subscription): Selection
    {
        return $this->getTable()->where(['subscription_id' => $subscription->id]);
    }
}
