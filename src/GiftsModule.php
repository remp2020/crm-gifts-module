<?php
namespace Crm\GiftsModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\Widget\LazyWidgetManagerInterface;
use Crm\GiftsModule\DataProvider\CanDeleteAddressDataProvider;
use League\Event\Emitter;

class GiftsModule extends CrmModule
{
    public const SUBSCRIPTION_TYPE_GIFT = 'gift';

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand(
            $this->getInstance(\Crm\GiftsModule\Commands\ActivatePurchasedGiftCouponsCommand::class)
        );
    }

    public function registerLazyEventHandlers(\Crm\ApplicationModule\Event\LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            \Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent::class,
            \Crm\GiftsModule\Events\PaymentItemContainerReadyEventHandler::class
        );

        $emitter->addListener(
            \Crm\PaymentsModule\Events\NewPaymentEvent::class,
            \Crm\GiftsModule\Events\CreateGiftCouponNewPaymentEventHandler::class,
            Emitter::P_HIGH
        );

        $emitter->addListener(
            \Crm\UsersModule\Events\UserRegisteredEvent::class,
            \Crm\GiftsModule\Events\SendWelcomeEmailHandler::class
        );

        $emitter->addListener(
            \Crm\SubscriptionsModule\Events\SubscriptionStartsEvent::class,
            \Crm\GiftsModule\Events\SubscriptionsStartsEventHandler::class
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(\Crm\GiftsModule\Seeders\AddressTypesSeeder::class));
        $seederManager->addSeeder($this->getInstance(\Crm\GiftsModule\Seeders\ConfigsSeeder::class));
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.payments.listing.action',
            \Crm\GiftsModule\Components\GiftCoupons::class,
            400
        );

        $widgetManager->registerWidget(
            'payment.address',
            \Crm\GiftsModule\Components\PaymentSuccessGiftSubscriptionAddressWidget::class
        );

        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            \Crm\GiftsModule\Components\GiftPaymentItemsListWidget::class
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.subscription',
            \Crm\GiftsModule\Components\DonatedSubscriptionListingWidget::class
        );

        $widgetManager->registerWidget(
            'admin.products.order.right_column',
            \Crm\GiftsModule\Components\OrderDonatedSubscriptionInfo::class
        );

        $widgetManager->registerWidget(
            'orders.listing.payment_actions',
            \Crm\GiftsModule\Components\GiftCoupons::class
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
