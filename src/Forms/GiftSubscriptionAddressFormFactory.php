<?php

namespace Crm\GiftsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GiftSubscriptionAddressFormFactory
{
    const PAYMENT_META_KEY = 'gift_subscription_address';

    private $usersRepository;
    private $addressesRepository;
    private $countriesRepository;
    private $addressChangeRequestsRepository;

    private $dataProviderManager;

    /* callback function */
    public $onSave;

    /** @var ActiveRow */
    private $payment;

    private $translator;

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $paymentMetaRepository;

    public function __construct(
        Translator $translator,
        UsersRepository $usersRepository,
        AddressesRepository $addressesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        CountriesRepository $countriesRepository,
        DataProviderManager $dataProviderManager,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository,
        PaymentMetaRepository $paymentMetaRepository
    ) {
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->countriesRepository = $countriesRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    public function create(ActiveRow $payment): Form
    {
        $form = new Form;

        $this->payment = $payment;
        $user = $this->payment->user;

        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->getElementPrototype()->addClass('ajax');

        $form->addText('first_name', 'gifts.components.gift_subscription_address.form.label.name')
            ->setRequired('gifts.components.gift_subscription_address.form.required.name');
        $form->addText('last_name', 'gifts.components.gift_subscription_address.form.label.last_name')
            ->setRequired('gifts.components.gift_subscription_address.form.required.last_name');
        $form->addText('phone_number', 'gifts.components.gift_subscription_address.form.label.phone_number');
        $form->addText('address', 'gifts.components.gift_subscription_address.form.label.address')
            ->setRequired('gifts.components.gift_subscription_address.form.required.address');
        $form->addText('number', 'gifts.components.gift_subscription_address.form.label.number')
            ->setRequired('gifts.components.gift_subscription_address.form.required.number');
        $form->addText('zip', 'gifts.components.gift_subscription_address.form.label.zip')
            ->setRequired('gifts.components.gift_subscription_address.form.required.zip');
        $form->addText('city', 'gifts.components.gift_subscription_address.form.label.city')
            ->setRequired('gifts.components.gift_subscription_address.form.required.city');
        $form->addSelect('country_id', 'gifts.components.gift_subscription_address.form.label.country_id', $this->countriesRepository->getDefaultCountryPair())
            ->setRequired('gifts.components.gift_subscription_address.form.required.country_id');

        $form->addHidden('VS', $payment->variable_symbol);

        $form->addHidden('done', 0)->setHtmlId('giftSubscriptionAddressDone');

        $form->addSubmit('send', 'gifts.components.gift_subscription_address.form.label.save')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-success')
            ->setAttribute('style', 'float: right');

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->payment->user;

        if (isset($values->first_name)) {
            // rewrite old address only if it's linked to this payment & type is correct
            $oldAddress = false;
            $addressID = $this->paymentMetaRepository->findByPaymentAndKey($this->payment, self::PAYMENT_META_KEY);
            if ($addressID) {
                $address = $this->addressesRepository->find($addressID);
                if ($address && $address->type === AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE) {
                    $oldAddress = $address;
                }
            }

            $changeRequest = $this->addressChangeRequestsRepository->add(
                $user,
                $oldAddress,
                $values->first_name,
                $values->last_name,
                null,
                $values->address,
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
                $this->paymentMetaRepository->add($this->payment, self::PAYMENT_META_KEY, $newAddress->id, true);
            }
        }

        $this->onSave->__invoke($form, $user);
    }
}
