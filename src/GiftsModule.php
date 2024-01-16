<?php
namespace Crm\GiftsModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Event\LazyEventEmitter;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\Widget\LazyWidgetManagerInterface;
use Crm\GiftsModule\Commands\ActivatePurchasedGiftCouponsCommand;
use Crm\GiftsModule\Components\DonatedSubscriptionListingWidget;
use Crm\GiftsModule\Components\GiftCoupons;
use Crm\GiftsModule\Components\GiftPaymentItemsListWidget;
use Crm\GiftsModule\Components\OrderDonatedSubscriptionInfo;
use Crm\GiftsModule\Components\PaymentSuccessGiftSubscriptionAddressWidget;
use Crm\GiftsModule\DataProvider\CanDeleteAddressDataProvider;
use Crm\GiftsModule\Events\CreateGiftCouponNewPaymentEventHandler;
use Crm\GiftsModule\Events\PaymentItemContainerReadyEventHandler;
use Crm\GiftsModule\Events\SendWelcomeEmailHandler;
use Crm\GiftsModule\Events\SubscriptionsStartsEventHandler;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\GiftsModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use League\Event\Emitter;

class GiftsModule extends CrmModule
{
    public const SUBSCRIPTION_TYPE_GIFT = 'gift';

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand(
            $this->getInstance(ActivatePurchasedGiftCouponsCommand::class)
        );
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            PaymentItemContainerReadyEvent::class,
            PaymentItemContainerReadyEventHandler::class
        );

        $emitter->addListener(
            NewPaymentEvent::class,
            CreateGiftCouponNewPaymentEventHandler::class,
            Emitter::P_HIGH
        );

        $emitter->addListener(
            UserRegisteredEvent::class,
            SendWelcomeEmailHandler::class
        );

        $emitter->addListener(
            SubscriptionStartsEvent::class,
            SubscriptionsStartsEventHandler::class
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(AddressTypesSeeder::class));
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.payments.listing.action',
            GiftCoupons::class,
            400
        );

        $widgetManager->registerWidget(
            'payment.address',
            PaymentSuccessGiftSubscriptionAddressWidget::class
        );

        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            GiftPaymentItemsListWidget::class
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.subscription',
            DonatedSubscriptionListingWidget::class
        );

        $widgetManager->registerWidget(
            'admin.products.order.right_column',
            OrderDonatedSubscriptionInfo::class
        );

        $widgetManager->registerWidget(
            'orders.listing.payment_actions',
            GiftCoupons::class
        );
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.address.can_delete',
            $this->getInstance(CanDeleteAddressDataProvider::class),
            200
        );
    }
}
