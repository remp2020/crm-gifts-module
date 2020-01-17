<?php

namespace Crm\GiftsModule\Commands;

use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\GiftsModule\Repository\PaymentGiftCouponsRepository;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ProductsModule\Repository\ProductPropertiesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActivatePurchasedGiftCouponsCommand extends Command
{
    private $addressesRepository;

    private $subscriptionsRepository;

    private $usersRepository;

    private $paymentGiftCouponsRepository;

    private $userManager;

    private $paymentMetaRepository;

    private $productPropertiesRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        AddressesRepository $addressesRepository,
        SubscriptionsRepository $subscriptionsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        UsersRepository $usersRepository,
        UserManager $userManager,
        ProductPropertiesRepository $productPropertiesRepository,
        PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        PaymentMetaRepository $paymentMetaRepository
    ) {
        parent::__construct();
        $this->addressesRepository = $addressesRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->usersRepository = $usersRepository;
        $this->paymentGiftCouponsRepository = $paymentGiftCouponsRepository;
        $this->userManager = $userManager;
        $this->productPropertiesRepository = $productPropertiesRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function configure()
    {
        $this->setName('gifts:activate_purchased_gift_coupons')
            ->setAliases([
                // DEPRECATED: remove `products:activate_purchased_gift_coupons` command alias
                'products:activate_purchased_gift_coupons'
                ])
            ->setDescription('Activates all gift coupons (and creates accounts) purchased via shop');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $output->writeln('');
        $output->writeln('<info>***** Activating purchased gift coupons *****</info>');
        $output->writeln('');

        foreach ($this->paymentGiftCouponsRepository->getAllNotSentAndActive() as $row) {
            $this->processCoupon($row, $output);
        }

        $end = microtime(true);
        $duration = $end - $start;

        $output->writeln('');
        $output->writeln('<info>All done. Took ' . round($duration, 2) . ' sec.</info>');
        $output->writeln('');
    }

    private function processCoupon($coupon, OutputInterface $output)
    {
        if ($coupon->payment->status !== PaymentsRepository::STATUS_PAID) {
            return 0;
        }

        $output->writeln("Processing gift for email <info>{$coupon->email}</info>");

        if ($coupon->product_id) {
            $subscriptionTypeCode = $this->productPropertiesRepository->getPropertyByCode($coupon->product, 'subscription_type_code');
            if (!$subscriptionTypeCode) {
                $output->writeln("<error>Missing assigned 'Subscription type code' for product {$coupon->product->name}</error>");
                return 1;
            }

            $subscriptionType = $this->subscriptionTypesRepository->findByCode($subscriptionTypeCode);

            if (!$subscriptionType) {
                $output->writeln("<error>No subscription assigned for code <info>{$subscriptionTypeCode}</info></error>");
                return 1;
            }
        } elseif ($coupon->subscription_type_id) {
            $subscriptionType = $this->subscriptionTypesRepository->find($coupon->subscription_type_id);
            if (!$subscriptionType) {
                $output->writeln("<error>Unable to find subscription type with ID <info>{$coupon->subscription_type_id}</info></error>");
                return 1;
            }
        } else {
            $output->writeln("<error>Coupon with ID <info>{$coupon->id}</info> is missing `product_id` and `subscription_type_id`</error>");
            return 1;
        }

        list($user, $userCreated) = $this->createUserIfNotExists($coupon->email);
        $output->writeln("User <info>{$coupon->email}</info> - " . ($userCreated ? "created" : "exists"));

        $address = $this->changeAddressOwner($user, $coupon->payment);

        $subscription = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            $user,
            SubscriptionsRepository::TYPE_GIFT,
            new DateTime(),
            null,
            null,
            $address
        );

        if (!$subscription) {
            $output->writeln("<error>Error while creating subscription {$subscriptionType->name}</error>");
            return 1;
        }
        $this->paymentGiftCouponsRepository->update($coupon, [
            'status' => PaymentGiftCouponsRepository::STATUS_SENT,
            'sent_at' => new DateTime(),
            'subscription_id' => $subscription->id
        ]);
        $output->writeln("Subscription <info>{$subscriptionType->name}</info> - created\n");

        return 0;
    }

    private function createUserIfNotExists($email)
    {
        $user = $this->usersRepository->getByEmail($email);
        $userCreated = false;
        if (!$user) {
            $user = $this->userManager->addNewUser($email, true, PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON);
            $userCreated = true;
        }
        return [$user, $userCreated];
    }

    private function changeAddressOwner(IRow $user, IRow $payment)
    {
        $address = null;

        // check if it's linked directly to payment
        if ($payment->address && $payment->address->type === AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE) {
            $address = $payment->address;
        }

        // or via payment meta
        $paymentMetaGiftAddress = $this->paymentMetaRepository
            ->findByPaymentAndKey($payment, GiftSubscriptionAddressFormFactory::PAYMENT_META_KEY);
        if (isset($paymentMetaGiftAddress->value)) {
            $giftAddress = $this->addressesRepository->find($paymentMetaGiftAddress->value);
            if ($giftAddress && $giftAddress->type === AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE) {
                $address = $giftAddress;
            }
        }

        if ($address) {
            $this->addressesRepository->update($address, [
                'user_id' => $user->id,
                'type' => 'print',
            ]);
        }

        return $address;
    }
}
