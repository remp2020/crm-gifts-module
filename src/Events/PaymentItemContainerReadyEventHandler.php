<?php

namespace Crm\GiftsModule\Events;

use Crm\GiftsModule\Models\PaymentItem\GiftPaymentItem;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Localization\Translator;

class PaymentItemContainerReadyEventHandler extends AbstractListener
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PaymentItemContainerReadyEvent)) {
            throw new \Exception("unable to handle event, expected PaymentItemContainerReadyEvent but got other");
        }

        $paymentData = $event->getPaymentData();
        if ($paymentData === null) {
            return;
        }

        if (!isset($paymentData['payment_metadata'])) {
            return;
        }

        $paymentMeta = $paymentData['payment_metadata'];

        // validate required payment's meta fields
        if (!isset($paymentMeta['gift']) || !filter_var($paymentMeta['gift'], FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        if (!isset($paymentMeta['gift_starts_at'])) {
            return;
        }
        if (!isset($paymentMeta['gift_email'])) {
            return;
        }

        // switch SubscriptionTypePaymentItem for GiftPaymentItem
        $paymentItemContainer = $event->getPaymentItemContainer();
        foreach ($paymentItemContainer->items() as $key => $paymentItem) {
            if ($paymentItem->type() === SubscriptionTypePaymentItem::TYPE) {
                $data = $paymentItem->data();
                $paymentItemContainer->switchItem($key, $paymentItem, new GiftPaymentItem(
                    subscriptionTypeId: $data['subscription_type_id'],
                    unitPrice: $paymentItem->unitPrice(),
                    name: $this->translator->translate('gifts.gift_payment_item.prefix') . $paymentItem->name(),
                    vat: $paymentItem->vat(),
                    count: $paymentItem->count(),
                    meta: [],
                    subscriptionTypeItemId: $data['subscription_type_item_id'] ?? null,
                ));
            }
        }
    }
}
