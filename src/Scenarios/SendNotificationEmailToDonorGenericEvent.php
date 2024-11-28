<?php

namespace Crm\GiftsModule\Scenarios;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\ScenariosModule\Events\NotificationTemplateParamsTrait;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Tracy\Debugger;
use Tracy\ILogger;

class SendNotificationEmailToDonorGenericEvent implements ScenarioGenericEventInterface
{
    use NotificationTemplateParamsTrait;

    private array $allowedMailTypeCodes = [];

    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly Emitter $emitter,
        private readonly MailTemplatesRepository $mailTemplatesRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsResolver $recurrentPaymentsResolver,
        private readonly ApplicationConfig $applicationConfig,
        private readonly PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
    ) {
    }

    public function addAllowedMailTypeCodes(string ...$mailTypeCodes): void
    {
        foreach ($mailTypeCodes as $mailTypeCode) {
            $this->allowedMailTypeCodes[$mailTypeCode] = $mailTypeCode;
        }
    }

    public function getLabel(): string
    {
        return 'Send notification email to the gift donor';
    }

    public function getParams(): array
    {
        $mailTemplates = $this->mailTemplatesRepository->all($this->allowedMailTypeCodes);

        $mailTemplateOptions = [];
        foreach ($mailTemplates as $mailTemplate) {
            $mailTemplateOptions[$mailTemplate->code] = $mailTemplate->name;
        }

        return [
            new StringLabeledArrayParam('email_codes', 'Email codes', $mailTemplateOptions, 'and'),
        ];
    }

    public function createEvents($options, $params): array
    {
        $templateParams = $this->getNotificationTemplateParams($params);

        $subscription = $this->getSubscription($params);
        $giftCoupon = $this->paymentGiftCouponsRepository->findBySubscription($subscription)->fetch();
        if (!$giftCoupon) {
            Debugger::log("Attempt to send notification email to donor on a non-gift subscription ID '{$subscription->id}'", ILogger::WARNING);
            return [];
        }

        $donorUser = $giftCoupon->payment->user;
        $templateParams['donor_user'] = [
            'id' => $donorUser->id,
            'uuid' => $donorUser->uuid,
            'email' => $donorUser->email,
        ];

        $events = [];

        foreach ($options['email_codes']->selection as $emailCode) {
            $events[] = new NotificationEvent(
                $this->emitter,
                $donorUser,
                $emailCode,
                $templateParams
            );
        }

        return $events;
    }
}
