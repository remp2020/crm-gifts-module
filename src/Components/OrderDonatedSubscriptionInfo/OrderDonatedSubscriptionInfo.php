<?php

namespace Crm\GiftsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;

class OrderDonatedSubscriptionInfo extends BaseWidget
{
    private $templateName = 'order_donated_subscription_info.latte';

    private $usersRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UsersRepository $usersRepository
    ) {
        parent::__construct($widgetManager);
        $this->usersRepository = $usersRepository;
    }

    public function identifier()
    {
        return 'orderdonatedsubscriptioninfo';
    }

    public function render(ActiveRow $order)
    {
        $giftCoupons = $order->payment->related('payment_gift_coupons')->fetchAll();
        if (empty($giftCoupons)) {
            return;
        }

        $users = [];
        foreach ($giftCoupons as $giftCoupon) {
            // optimization to avoid search of user by email unless necessary
            if ($giftCoupon->subscription_id) {
                $users[$giftCoupon->email] = $giftCoupon->subscription->user;
            } else {
                $users[$giftCoupon->email] = $this->usersRepository->getByEmail($giftCoupon->email);
            }
        }

        $this->template->giftCoupons = $giftCoupons;
        $this->template->users = $users;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
