<?php
declare(strict_types=1);

namespace Crm\GiftsModule\Events;

use Crm\SubscriptionsModule\Events\SubscriptionEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class GiftCouponActivatedEvent extends AbstractEvent implements SubscriptionEventInterface
{
    public function __construct(
        private readonly ActiveRow $coupon,
        private readonly ActiveRow $subscription,
    ){}

    public function getCoupon(): ActiveRow
    {
        return $this->coupon;
    }

    public function getSubscription(): ?ActiveRow
    {
        return $this->subscription;
    }
}
