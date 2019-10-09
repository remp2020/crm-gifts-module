<?php

namespace Crm\GiftsModule\Components;

use Crm\ApplicationModule\Widget\WidgetFactoryInterface;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UsersRepository;

class GiftCouponsFactoryInterface implements WidgetFactoryInterface
{
    protected $widgetManager;

    protected $usersRepository;

    public function __construct(
        WidgetManager $widgetManager,
        UsersRepository $usersRepository
    ) {
        $this->widgetManager = $widgetManager;
        $this->usersRepository = $usersRepository;
    }

    public function create()
    {
        $giftCoupons = new GiftCoupons(
            $this->widgetManager,
            $this->usersRepository
        );
        return $giftCoupons;
    }
}
