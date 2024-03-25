<?php

namespace Crm\GiftsModule\DataProviders;

use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\DataProviders\SubscriptionFormDataProviderInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Tracy\Debugger;

class SubscriptionFormDataProvider implements SubscriptionFormDataProviderInterface
{
    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        private SubscriptionsRepository $subscriptionsRepository,
        private Translator $translator,
        private UserDateHelper $userDateHelper,
    ) {
    }

    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('form param missing');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('form is not instance of \Nette\Application\UI\Form');
        }

        /** @var Form $form */
        $form = $params['form'];

        // do nothing if no subscription is attached to form
        // (new subscription without payment is being created)
        $subscriptionId = $form->getComponent('subscription_id', false);
        if ($subscriptionId === null) {
            return $form;
        }

        $subscription = $this->subscriptionsRepository->find((int) $subscriptionId->getValue());
        if (!$subscription) {
            Debugger::log("Subscription with ID [{$subscriptionId}] provided by SubscriptionForm doesn't exist.", Debugger::ERROR);
            return $form;
        }

        // do nothing if subscription has payment
        // (this scenario is handled by Crm\PaymentsModule\DataProviders\SubscriptionFormDataProvider)
        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment) {
            return $form;
        }
        unset($payment); // unset (it's null) so it screams if used by accident later in this provider

        // load parent payment from gift coupon
        $paymentGiftCoupon = $this->paymentGiftCouponsRepository->findBySubscription($subscription)->fetch();
        if (!$paymentGiftCoupon) {
            return $form;
        }

        $parentPayment = $paymentGiftCoupon->payment;

        // attach description and rule for "start time after parent payment's paid at" to element
        $elementName = 'start_time';
        if ($form->getComponent($elementName) !== null) {
            $description = $this->translator->translate(
                'gifts.data_provider.subscription_form.start_time_after_payment.description',
                ['payment_paid' => $this->userDateHelper->process($parentPayment->paid_at)],
            );

            // load & translate original description
            // - if translation string is single value of description, it is translated automatically
            // - if there are multiple strings, translation has to be done manually
            $originalDescription = $form->getComponent($elementName)->getOption('description');
            if ($originalDescription !== null) {
                $description = $this->translator->translate($originalDescription) . "\n" . $description;
            }

            // attach description to element
            $form->getComponent($elementName)
                ->setOption(
                    'description',
                    Html::el('span', ['class' => 'help-block'])->setHtml($description)
                );

            $form->getComponent($elementName)
                ->addRule(
                    validator: function (TextInput $field, DateTime $paymentPaidAt) {
                        $subscriptionStartAt = DateTime::from($field->getValue());
                        // subscription's start has to be after parent payment's paid at
                        return $subscriptionStartAt >= $paymentPaidAt;
                    },
                    errorMessage: 'gifts.data_provider.subscription_form.start_time_after_payment.error',
                    arg: $parentPayment->paid_at,
                );
        }

        return $form;
    }
}
