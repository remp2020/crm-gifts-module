<?php

namespace Crm\GiftsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class PaymentGiftCouponsRepository extends Repository
{
    const STATUS_NOT_SENT = 'not_sent';
    const STATUS_SENT = 'sent';

    const USER_SOURCE_GIFT_COUPON = 'gift_coupon';

    protected $tableName = 'payment_gift_coupons';

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        AuditLogRepository $auditLogRepository
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
            ->where('payment.status IN ?', [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID]);
    }

    final public function findAllBySubscriptions(array $subscriptonsIDs)
    {
        return $this->getTable()->where(['subscription_id' => $subscriptonsIDs])->fetchAll();
    }

    final public function findByPayment($payment): Selection
    {
        return $this->getTable()->where(['payment_id' => $payment->id]);
    }
}
