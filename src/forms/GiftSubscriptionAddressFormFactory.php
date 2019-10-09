<?php

namespace Crm\GiftsModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\AddressChangeRequestsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class GiftSubscriptionAddressFormFactory
{
    private $usersRepository;
    private $addressesRepository;
    private $countriesRepository;
    private $addressChangeRequestsRepository;

    private $dataProviderManager;

    /* callback function */
    public $onSave;

    /** @var IRow */
    private $payment;

    private $translator;

    private $subscriptionsRepository;

    private $paymentsRepository;

    public function __construct(
        Translator $translator,
        UsersRepository $usersRepository,
        AddressesRepository $addressesRepository,
        AddressChangeRequestsRepository $addressChangeRequestsRepository,
        CountriesRepository $countriesRepository,
        DataProviderManager $dataProviderManager,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository
    ) {
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
        $this->addressChangeRequestsRepository = $addressChangeRequestsRepository;
        $this->countriesRepository = $countriesRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
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
            ->setAttribute('style', 'float: right')
            ->setHtml($this->translator->translate('gifts.components.gift_subscription_address.form.label.save'));

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->payment->user;
        $address = $this->payment->address;

        if ($address) {
            $this->addressesRepository->update($address, [
                'first_name' => $values->first_name,
                'last_name' => $values->last_name,
                'number' => $values->number,
                'city' => $values->city,
                'zip' => $values->zip,
                'country_id' => $values->country_id,
                'phone_number' => $values->phone_number,
                'address' => $values->address,
            ]);
        } else {
            $address = $this->addressesRepository->add(
                $user,
                AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE,
                $values->first_name,
                $values->last_name,
                $values->address,
                $values->number,
                $values->city,
                $values->zip,
                $values->country_id,
                $values->phone_number
            );

            $this->paymentsRepository->update($this->payment, [
                'address_id' => $address->id
            ]);
        }

        $this->onSave->__invoke($form, $user);
    }
}
