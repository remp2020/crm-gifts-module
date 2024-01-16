<?php

namespace Crm\GiftsModule\Components\DonatedSubscriptionListingWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

/**
 * This widget fetches payment gift coupons from subscription
 * and renders simple badge showing subscriptions donor e-mail in listings.
 *
 * @package Crm\ProductsModule\Components
 */
class DonatedSubscriptionListingWidget extends BaseLazyWidget
{
    private $templateName = 'donated_subscription_listing_widget.latte';

    public function identifier()
    {
        return 'donatedsubscriptionwidget';
    }

    public function render(ActiveRow $subscription)
    {
        $giftCoupon = $subscription->related('payment_gift_coupons')->fetch();
        if (!$giftCoupon) {
            return;
        }

        $this->template->donor = $giftCoupon->payment->user;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
