<?php

namespace Crm\GiftsModule\Commands;

use Crm\GiftsModule\Events\GiftCouponActivatedEvent;
use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\GiftsModule\GiftsModule;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\ProductsModule\Repositories\OrdersRepository;
use Crm\ProductsModule\Repositories\ProductPropertiesRepository;
use Crm\SubscriptionsModule\Models\Extension\ExtensionInterface;
use Crm\SubscriptionsModule\Models\Extension\ExtensionMethodFactory;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class ActivatePurchasedGiftCouponsCommand extends Command
{
    private ExtensionInterface $extender;

    public function __construct(
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly UserManager $userManager,
        private readonly ProductPropertiesRepository $productPropertiesRepository,
        private readonly PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        private readonly PaymentMetaRepository $paymentMetaRepository,
        private readonly OrdersRepository $ordersRepository,
        private readonly ExtensionMethodFactory $extensionMethodFactory,
        private readonly Emitter $emitter,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('gifts:activate_purchased_gift_coupons')
            ->setDescription('Activates all gift coupons (and creates accounts) purchased via shop');
    }

    public function setExtendMethod(string $extendMethod)
    {
        $this->extender = $this->extensionMethodFactory->getExtension($extendMethod);
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

        return Command::SUCCESS;
    }

    private function processCoupon($coupon, OutputInterface $output)
    {
        $output->writeln("Processing gift for email <info>{$coupon->email}</info>");
        $prefixError = "Unable to activate gift subscription coupon #[{$coupon->id}] from payment ID [{$coupon->payment_id}]: ";

        if ($coupon->product_id) {
            $subscriptionTypeCode = $this->productPropertiesRepository->getPropertyByCode($coupon->product, 'subscription_type_code');
            if (!$subscriptionTypeCode) {
                $errorMsg = $prefixError . "Missing product.subscription_type_code from product ID #{$coupon->product->id}.";
                Debugger::log($errorMsg, ILogger::ERROR);
                $output->writeln("<error>{$errorMsg}</error>");
                return;
            }

            $subscriptionType = $this->subscriptionTypesRepository->findByCode($subscriptionTypeCode);
            if (!$subscriptionType) {
                $errorMsg = $prefixError . "Unable to find subscription type with code [{$subscriptionTypeCode}].";
                Debugger::log($errorMsg, ILogger::ERROR);
                $output->writeln("<error>{$errorMsg}</error>");
                return;
            }
        } elseif ($coupon->subscription_type_id) {
            $subscriptionType = $this->subscriptionTypesRepository->find($coupon->subscription_type_id);
            if (!$subscriptionType) {
                $errorMsg = $prefixError . "Unable to find subscription type with ID #[{$coupon->subscription_type_id}].";
                Debugger::log($errorMsg, ILogger::ERROR);
                $output->writeln("<error>{$errorMsg}</error>");
                return;
            }
        } else {
            $errorMsg = $prefixError . "Coupon is missing `product_id` and `subscription_type_id`.";
            Debugger::log($errorMsg, ILogger::ERROR);
            $output->writeln("<error>{$errorMsg}</error>");
            return;
        }

        try {
            list($user, $userRegistered) = $this->createUserIfNotExists($coupon->email);
        } catch (\Exception $exception) {
            $errorMsg = $prefixError . "Unable to create user [{$coupon->email}]: {$exception->getMessage()}.";
            Debugger::log($errorMsg, ILogger::ERROR);
            $output->writeln("<error>{$errorMsg}</error>");
            return;
        }

        $output->writeln("User <info>{$coupon->email}</info> - " . ($userRegistered ? "created" : "exists"));

        $address = $this->changeAddressOwner($user, $coupon);

        $startTime = new DateTime();
        if ($this->extender) {
            $startTime = $this->extender->getStartTime($user, $subscriptionType)->getDate();
        }

        $subscription = $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            true,
            $user,
            GiftsModule::SUBSCRIPTION_TYPE_GIFT,
            $startTime,
            null,
            null,
            $address
        );

        if (!$subscription) {
            Debugger::log("Error while creating gift subscription {$subscriptionType->name}", ILogger::ERROR);
            $output->writeln("<error>Error while creating gift subscription {$subscriptionType->name}</error>");
            return;
        }
        $this->paymentGiftCouponsRepository->update($coupon, [
            'status' => PaymentGiftCouponsRepository::STATUS_SENT,
            'sent_at' => new DateTime(),
            'subscription_id' => $subscription->id
        ]);

        // update status of order to delivered (if this gift was purchased in shop)
        $order = $this->ordersRepository->findByPayment($coupon->payment);
        if ($order) {
            $this->ordersRepository->update($order, ['status' => OrdersRepository::STATUS_DELIVERED]);
        }

        $this->emitter->emit(new GiftCouponActivatedEvent($coupon, $subscription));

        $output->writeln("Subscription <info>{$subscriptionType->name}</info> - created\n");

        return;
    }

    private function createUserIfNotExists($email)
    {
        $user = $this->usersRepository->getByEmail($email);
        $userRegistered = false;
        if (!$user) {
            $user = $this->userManager->addNewUser($email, true, PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON);
            $userRegistered = true;
        }
        if (!$user->active) {
            $this->usersRepository->update($user, [
                'active' => true,
            ]);
        }
        return [$user, $userRegistered];
    }

    private function changeAddressOwner(ActiveRow $user, ActiveRow $coupon)
    {
        $payment = $coupon->payment;
        $address = $coupon->address;

        // if there is no address in coupon
        if (is_null($address)) {
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
        }

        if ($address) {
            $defaultAddress = $this->addressesRepository->address($user, 'print', true);
            $this->addressesRepository->update($address, [
                'user_id' => $user->id,
                'type' => 'print',
                'is_default' => !$defaultAddress,
            ]);

            $changeRequests = $this->addressChangeRequestsRepository->getTable()
                ->where('address_id = ?', $address->id);

            foreach ($changeRequests as $changeRequest) {
                $this->addressChangeRequestsRepository->update($changeRequest, [
                    'user_id' => $user->id,
                    'type' => 'print',
                ]);
            }
        }

        return $address;
    }
}
