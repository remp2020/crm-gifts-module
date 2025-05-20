<?php

declare(strict_types=1);

namespace Crm\GiftsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\UI\Form;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Events\PaymentItemContainerReadyEvent;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Forms\Controls\AddressesSelectItemsBuilder;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GiftFormFactory
{
    public $onSave;

    public $onUpdate;

    public function __construct(
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressesSelectItemsBuilder $addressesSelectItemsBuilder,
        private readonly Emitter $emitter,
        private readonly OneStopShop $oneStopShop,
        private readonly PaymentGatewaysRepository $paymentGatewaysRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
        private readonly Translator $translator,
        private readonly UsersRepository $usersRepository,
        private User $user,
    ) {
    }

    public function create(ActiveRow $user): Form
    {
        $form = new Form();

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $paymentGateways = $this->paymentGatewaysRepository->all()->fetchPairs('id', 'name');
        $form->addSelect('payment_gateway_id', 'gifts.forms.gift_form.payment_gateway.label', $paymentGateways)
            ->setRequired()
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        $subscriptionTypeId = $form->addSelect(
            'subscription_type_id',
            'gifts.forms.gift_form.subscription_type.label',
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypes),
        )
            ->setRequired()
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addText('gift_email', 'gifts.forms.gift_form.gift_email.label')
            ->setRequired()
            ->setOption('description', 'gifts.forms.gift_form.gift_email.description')
            ->setHtmlAttribute('placeholder', 'gifts.forms.gift_form.gift_email.placeholder');

        $form->addText('gift_starts_at', 'gifts.forms.gift_form.gift_starts_at.label')
            ->setRequired()
            ->setOption('description', 'gifts.forms.gift_form.gift_starts_at.description')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime_seconds', "1")
            ->setHtmlAttribute('flatpickr_mindate', "today");

        $addressTypeTranslated = $this->translator->translate('gifts.seeders.address_types.gift_subscription_type');
        $form->addSelect(
            'address_id',
            'gifts.forms.gift_form.address.label',
            $this->addressesSelectItemsBuilder->buildSimpleWithTypes($user, AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE),
        )
            ->setPrompt('--')
            ->setOption('description', $this->translator->translate(
                'gifts.forms.gift_form.address.description',
                ['addressType' => $addressTypeTranslated],
            ))
            ->getControlPrototype()->addAttributes(['class' => 'select2'])
        ;

        $form->addTextArea('note', 'gifts.forms.gift_form.note.label')
            ->setRequired() // GDPR reasons: we should have email / phone record in case new account will be created
            ->setHtmlAttribute('placeholder', 'gifts.forms.gift_form.note.placeholder')
            ->getControlPrototype()
            ->addAttributes(['class' => 'autosize']);

        $form->addHidden('user_id', $user->id);

        $form->addSubmit('send', 'system.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($values['payment_gateway_id']);
        $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
        $donorUser = $this->usersRepository->find($values['user_id']);

        $address = null;
        if ($values->address_id) {
            $address = $this->addressesRepository->find($values->address_id);
        }

        $giftStartsAt = DateTime::from(strtotime($values['gift_starts_at']));

        $paymentMetaData = [
            'gift' => true,
            'gift_email' => $values['gift_email'],
            'gift_starts_at' => $giftStartsAt->format(\DateTimeInterface::RFC3339),
            // logging who added gift payment directly to payment meta
            // (GDPR reasons: we should have email / phone record in case new account will be created)
            'created_by_user_id' => $this->user->id,
        ];

        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        // let GiftsModule\..\PaymentItemContainerReadyEventHandler update payment container as needed
        $this->emitter->emit(new PaymentItemContainerReadyEvent(
            $paymentItemContainer,
            $donorUser,
            ['payment_metadata' => $paymentMetaData],
        ));

        $resolvedCountry = null;
        try {
            $resolvedCountry = $this->oneStopShop->resolveCountry(
                user: $donorUser,
                paymentAddress: $address,
                paymentItemContainer: $paymentItemContainer,
            );
        } catch (OneStopShopCountryConflictException $e) {
            $form->addError('gifts.forms.gift_form.unable_to_create_payment_one_stop_shop');
            return;
        }

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $paymentGateway,
            user: $donorUser,
            paymentItemContainer: $paymentItemContainer,
            address: $address,
            metaData: $paymentMetaData,
            paymentCountry: $resolvedCountry?->country,
            paymentCountryResolutionReason: $resolvedCountry?->getReasonValue(),
            note: $values['note'],
        );

        $this->onSave->__invoke($payment);
    }
}
