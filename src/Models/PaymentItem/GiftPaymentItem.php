<?php

namespace Crm\GiftsModule\Models\PaymentItem;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\ActiveRow;

final class GiftPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'gift';

    public function __construct(
        private int $subscriptionTypeId,
        float $unitPrice,
        string $name,
        int $vat,
        int $count = 1,
        array $meta = [],
    ) {
        $this->name = $name;
        $this->price = $unitPrice;
        $this->vat = $vat;
        $this->count = $count;
        $this->meta = $meta;
    }

    /**
     * @param ActiveRow $paymentItem
     * @return GiftPaymentItem
     * @throws \Exception Thrown if payment item isn't `gift` payment item type.
     */
    public static function fromPaymentItem(ActiveRow $paymentItem): static
    {
        if ($paymentItem->type !== self::TYPE) {
            throw new \Exception("Can not load GiftPaymentItem from payment item of different type. Got [{$paymentItem->type}]");
        }
        return new GiftPaymentItem(
            $paymentItem->subscription_type_id,
            $paymentItem->amount,
            $paymentItem->name,
            $paymentItem->vat,
            $paymentItem->count,
            self::loadMeta($paymentItem),
        );
    }

    public function data(): array
    {
        return [
            'subscription_type_id' => $this->subscriptionTypeId,
        ];
    }
}
