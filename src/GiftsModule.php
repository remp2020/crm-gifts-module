<?php
namespace Crm\GiftsModule;

use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use League\Event\Emitter;

class GiftsModule extends CrmModule
{
    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand(
            $this->getInstance(\Crm\GiftsModule\Commands\ActivatePurchasedGiftCouponsCommand::class)
        );
    }

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\PaymentsModule\Events\PaymentChangeStatusEvent::class,
            $this->getInstance(\Crm\GiftsModule\Events\GiftPaymentStatusChangeHandler::class)
        );

        $emitter->addListener(
            \Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent::class,
            $this->getInstance(\Crm\GiftsModule\Events\PaymentItemContainerReadyEventHandler::class)
        );

        $emitter->addListener(
            \Crm\PaymentsModule\Events\NewPaymentEvent::class,
            $this->getInstance(\Crm\GiftsModule\Events\CreateGiftCouponNewPaymentEventHandler::class),
            Emitter::P_HIGH
        );

        $emitter->addListener(
            \Crm\SubscriptionsModule\Events\SubscriptionStartsEvent::class,
            $this->getInstance(\Crm\GiftsModule\Events\SubscriptionsStartsEventHandler::class)
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(\Crm\GiftsModule\Seeders\AddressTypesSeeder::class));
        $seederManager->addSeeder($this->getInstance(\Crm\GiftsModule\Seeders\ConfigsSeeder::class));
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.payments.listing.action',
            $this->getInstance(\Crm\GiftsModule\Components\GiftCoupons::class),
            400
        );

        $widgetManager->registerWidget(
            'frontend.payment.success.forms',
            $this->getInstance(\Crm\GiftsModule\Components\PaymentSuccessGiftSubscriptionAddressWidget::class)
        );

        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            $this->getInstance(\Crm\GiftsModule\Components\GiftPaymentItemsListWidget::class)
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.subscription',
            $this->getInstance(\Crm\GiftsModule\Components\DonatedSubscriptionListingWidget::class)
        );
    }
}
