<?php

namespace Crm\GiftsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\UI\Form;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GiftSubscriptionAddressFormFactory
{
    public const PAYMENT_META_KEY = 'gift_subscription_address';

    public $onSave;

    private $payment;

    public function __construct(
        private readonly Translator $translator,
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly PaymentMetaRepository $paymentMetaRepository,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
    ) {
    }

    public function create(ActiveRow $payment): Form
    {
        $form = new Form;

        $this->payment = $payment;

        $defaults = [];
        $oldAddress = $this->getExistingAddress($this->payment);
        if ($oldAddress) {
            $defaults = $oldAddress->toArray();
            if (!$defaults['country_id']) {
                $defaults['country_id'] = $this->countriesRepository->defaultCountry()->id;
            }
        }

        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addText('first_name', 'gifts.components.gift_subscription_address.form.label.name')
            ->setRequired('gifts.components.gift_subscription_address.form.required.name');
        $form->addText('last_name', 'gifts.components.gift_subscription_address.form.label.last_name')
            ->setRequired('gifts.components.gift_subscription_address.form.required.last_name');
        $form->addText('phone_number', 'gifts.components.gift_subscription_address.form.label.phone_number');
        $form->addText('street', 'gifts.components.gift_subscription_address.form.label.street')
            ->setRequired('gifts.components.gift_subscription_address.form.required.street');
        $form->addText('number', 'gifts.components.gift_subscription_address.form.label.number')
            ->setRequired('gifts.components.gift_subscription_address.form.required.number');
        $form->addText('zip', 'gifts.components.gift_subscription_address.form.label.zip')
            ->setRequired('gifts.components.gift_subscription_address.form.required.zip');
        $form->addText('city', 'gifts.components.gift_subscription_address.form.label.city')
            ->setRequired('gifts.components.gift_subscription_address.form.required.city');
        $form->addSelect('country_id', 'gifts.components.gift_subscription_address.form.label.country_id', $this->countriesSelectItemsBuilder->getDefaultCountryPair())
            ->setRequired('gifts.components.gift_subscription_address.form.required.country_id');

        $form->addHidden('VS', $payment->variable_symbol);

        $form->addHidden('done', 0)->setHtmlId('giftSubscriptionAddressDone');

        $form->addSubmit('send', 'gifts.components.gift_subscription_address.form.label.save')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-success')
            ->setAttribute('style', 'float: right');

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    private function getExistingAddress(ActiveRow $payment): ?ActiveRow
    {
        $oldAddressId = $this->paymentMetaRepository
            ->findByPaymentAndKey($payment, self::PAYMENT_META_KEY)
            ?->value;

        if ($oldAddressId) {
            $address = $this->addressesRepository->find($oldAddressId);
            if ($address && $address->type === AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE) {
                return $address;
            }
        }
        return null;
    }

    public function formSucceeded(Form $form, ArrayHash $values)
    {
        $user = $this->payment->user;

        if (isset($values->first_name)) {
            $oldAddress = $this->getExistingAddress($this->payment);

            $changeRequest = $this->addressChangeRequestsRepository->add(
                $user,
                $oldAddress, // rewrite old address only if it's linked to this payment and type is correct
                $values->first_name,
                $values->last_name,
                null,
                $values->street,
                $values->number,
                $values->city,
                $values->zip,
                $values->country_id,
                null,
                null,
                null,
                $values->phone_number,
                AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE
            );

            if ($changeRequest) {
                $newAddress = $this->addressChangeRequestsRepository->acceptRequest($changeRequest);

                // link to payment for later use (switching user from donor to donee)
                $this->paymentMetaRepository->add($this->payment, self::PAYMENT_META_KEY, $newAddress->id);
            }
        }

        $this->onSave->__invoke($form, $user);
    }
}
