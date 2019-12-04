<?php

namespace Crm\GiftsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\IRow;

/**
 * This component renders button with dropdown listing of gifted subscriptions in listing actions column.
 * Shows modal with gifted subscription detail after click on listing item.
 *
 * @package Crm\ProductsModule\Components
 */
class GiftCoupons extends BaseWidget
{
    private $templateName = 'gift_coupons.latte';

    private $usersRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UsersRepository $usersRepository
    ) {
        parent::__construct($widgetManager);

        $this->usersRepository = $usersRepository;
    }

    public function header($id = '')
    {
        return 'coupon modal';
    }

    public function identifier()
    {
        return 'couponmodal';
    }

    public function render(IRow $payment)
    {
        $giftCoupons = $payment->related('payment_gift_coupons')->fetchAll();
        $users = [];

        if (empty($giftCoupons)) {
            return;
        }

        foreach ($giftCoupons as $giftCoupon) {
            // optimization to avoid search of user by email unless necessary
            if ($giftCoupon->subscription_id) {
                $users[$giftCoupon->email] = $giftCoupon->subscription->user;
            } else {
                $users[$giftCoupon->email] = $this->usersRepository->getByEmail($giftCoupon->email);
            }
        }

        $this->template->users = $users;
        $this->template->payment = $payment;
        $this->template->giftCoupons = $giftCoupons;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
