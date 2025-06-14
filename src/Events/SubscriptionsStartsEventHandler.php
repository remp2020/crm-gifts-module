<?php

namespace Crm\GiftsModule\Events;

use Crm\GiftsModule\GiftsModule;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Utils\DateTime;

class SubscriptionsStartsEventHandler extends AbstractListener
{
    private $emitter;

    public function __construct(
        Emitter $emitter,
    ) {
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof SubscriptionStartsEvent)) {
            throw new \Exception("unable to handle event, expected SubscriptionStartsEvent but got other");
        }

        $subscription = $event->getSubscription();

        if ($subscription->type !== GiftsModule::SUBSCRIPTION_TYPE_GIFT) {
            return;
        }

        // new users received `welcome_email_gift_coupon` email
        if ($subscription->user->created_at >= DateTime::from('-15 minutes')) {
            return;
        }

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $subscription->user,
            'new_subscription_gift',
            [],
            "new_subscription.{$subscription->id}",
        ));
    }
}
