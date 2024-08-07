<?php

namespace Crm\GiftsModule\Models\PaymentItem;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemTrait;
use Nette\Database\Table\ActiveRow;

class GiftPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'gift';

    private int $subscriptionTypeID;

    public function __construct(
        int $subscriptionTypeID,
        float $unitPrice,
        string $name,
        int $vat,
        int $count = 1
    ) {
        $this->subscriptionTypeID = $subscriptionTypeID;
        $this->price = $unitPrice;
        $this->name = $name;
        $this->vat = $vat;
        $this->count = $count;
    }

    /**
     * @param ActiveRow $paymentItem
     * @return GiftPaymentItem
     * @throws \Exception Thrown if payment item isn't `gift` payment item type.
     */
    public static function fromPaymentItem(ActiveRow $paymentItem)
    {
        if ($paymentItem->type != self::TYPE) {
            throw new \Exception("Can not load GiftPaymentItem from payment item of different type. Got [{$paymentItem->type}]");
        }
        return new GiftPaymentItem(
            $paymentItem->subscription_type_id,
            $paymentItem->amount,
            $paymentItem->name,
            $paymentItem->vat,
            $paymentItem->count
        );
    }

    public function data(): array
    {
        return [
            'subscription_type_id' => $this->subscriptionTypeID,
        ];
    }

    public function meta(): array
    {
        return [];
    }

    public function forceVat(int $vat): static
    {
        $this->vat = $vat;
        return $this;
    }
}
