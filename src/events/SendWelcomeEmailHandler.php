<?php

namespace Crm\GiftsModule\Events;

use Crm\GiftsModule\Repository\PaymentGiftCouponsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\UserCreatedEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class SendWelcomeEmailHandler extends AbstractListener
{
    private $emitter;

    public function __construct(
        Emitter $emitter
    ) {
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserCreatedEvent)) {
            throw new \Exception("Unable to handle event, expected UserCreatedEvent, received [" . get_class($event) . "]");
        }
        if (!$event->sendEmail()) {
            return;
        }

        $user = $event->getUser();
        if ($user->source !== PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON) {
            return;
        }

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $user,
            'welcome_email_gift_coupon',
            [
                'email' => $user->email,
                'password' => $event->getOriginalPassword(),
            ],
            "registration.welcome_email.{$user->id}"
        ));
    }
}
