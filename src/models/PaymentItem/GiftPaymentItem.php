<?php

namespace Crm\GiftsModule\PaymentItem;

use Crm\PaymentsModule\PaymentItem\PaymentItemInterface;
use Nette\Database\Table\IRow;

class GiftPaymentItem implements PaymentItemInterface
{
    const TYPE = 'gift';

    private $subscriptionTypeID;

    private $unitPrice;

    private $name;

    private $vat;

    private $count;

    public function __construct(
        int $subscriptionTypeID,
        float $unitPrice,
        string $name,
        int $vat,
        int $count = 1
    ) {
        $this->subscriptionTypeID = $subscriptionTypeID;
        $this->unitPrice = $unitPrice;
        $this->name = $name;
        $this->vat = $vat;
        $this->count = $count;
    }

    /**
     * @param IRow $paymentItem
     * @return GiftPaymentItem
     * @throws \Exception Thrown if payment item isn't `gift` payment item type.
     */
    public static function fromPaymentItem(IRow $paymentItem)
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

    public function type(): string
    {
        return self::TYPE;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unitPrice(): float
    {
        return $this->unitPrice;
    }

    public function totalPrice(): float
    {
        return $this->unitPrice() * $this->count();
    }

    public function vat(): int
    {
        return $this->vat;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function data(): array
    {
        return [
            'subscription_type_id' => $this->subscriptionTypeID,
        ];
    }
}
