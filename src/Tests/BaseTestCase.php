<?php

namespace Crm\GiftsModule\Tests;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\GiftsModule\GiftsModule;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeContentAccess;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Tests\TestNotificationHandler;
use League\Event\Emitter;

abstract class BaseTestCase extends DatabaseTestCase
{
    /** @var Emitter */
    protected $emitter;

    /** @var TestNotificationHandler */
    protected $testNotificationHandler;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            LoginAttemptsRepository::class,
            // To work with subscriptions, we need all these tables
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            // Payments + recurrent payments
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            RecurrentPaymentsRepository::class,
            // Content access
            ContentAccessRepository::class,
            SubscriptionTypeContentAccess::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class
        ];
    }

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->emitter = $this->inject(Emitter::class);

        // Email notification is going to be handled by test handler
        $this->testNotificationHandler = new TestNotificationHandler();
        $this->emitter->addListener(NotificationEvent::class, $this->testNotificationHandler);
        
        // Register required event handlers
        $giftsModule = new GiftsModule($this->container, $this->inject(Translator::class));
        $giftsModule->registerEventHandlers($this->emitter);
    }

    /**
     * @param $email
     *
     * @return NotificationEvent[]
     */
    public function notificationsSentTo($email): array
    {
        return $this->testNotificationHandler->notificationsSentTo($email);
    }
}
