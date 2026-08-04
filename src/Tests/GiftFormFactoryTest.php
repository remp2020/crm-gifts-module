<?php

declare(strict_types=1);

namespace Crm\GiftsModule\Tests;

use Crm\GiftsModule\Forms\GiftFormFactory;
use Crm\GiftsModule\Models\PaymentItem\GiftPaymentItem;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\TestPaymentGatewaysSeeder;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Crm\PaymentsModule\Tests\Gateways\TestSingleGateway;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\SelectBox;
use Nette\Utils\ArrayHash;
use PHPUnit\Framework\Attributes\DataProvider;

class GiftFormFactoryTest extends BaseTestCase
{
    private GiftFormFactory $giftFormFactory;
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;
    private PaymentsRepository $paymentsRepository;
    private PaymentGatewaysRepository $paymentGatewaysRepository;
    private ActiveRow $paymentGateway;
    private ActiveRow $user;

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            PaymentGiftCouponsRepository::class,
            ...parent::requiredRepositories(),
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            TestPaymentGatewaysSeeder::class,
            ...parent::requiredSeeders(),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->giftFormFactory = $this->inject(GiftFormFactory::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);

        $this->paymentGateway = $this->paymentGatewaysRepository->findByCode(TestSingleGateway::GATEWAY_CODE);
        $this->user = $this->inject(UserManager::class)->addNewUser('donor@example.com', false, 'unknown', null, false);
    }

    public static function customAmountDataProvider(): array
    {
        return [
            'MixedVatItems_SpreadsProportionallyToExactSum' => [
                'subscriptionTypeItems' => [
                    ['name' => 'digital', 'amount' => 79.00, 'vat' => 23],
                    ['name' => 'print', 'amount' => 18.00, 'vat' => 5],
                ],
                'customAmount' => 80.00,
                'expectedItemAmounts' => [65.16, 14.84],
            ],
            'SameVatEqualItems_RemainderGoesToFirstItem' => [
                'subscriptionTypeItems' => [
                    ['name' => 'first', 'amount' => 10.00, 'vat' => 23],
                    ['name' => 'second', 'amount' => 10.00, 'vat' => 23],
                    ['name' => 'third', 'amount' => 10.00, 'vat' => 23],
                ],
                'customAmount' => 10.00,
                'expectedItemAmounts' => [3.34, 3.33, 3.33],
            ],
            'AmountAboveDefaultPrice_ScalesUp' => [
                'subscriptionTypeItems' => [
                    ['name' => 'digital', 'amount' => 50.00, 'vat' => 23],
                    ['name' => 'print', 'amount' => 25.00, 'vat' => 5],
                ],
                'customAmount' => 150.00,
                'expectedItemAmounts' => [100.00, 50.00],
            ],
        ];
    }

    #[DataProvider('customAmountDataProvider')]
    public function testCustomAmountSpreadsAcrossPaymentItems(
        array $subscriptionTypeItems,
        float $customAmount,
        array $expectedItemAmounts,
    ): void {
        $subscriptionType = $this->createSubscriptionType($subscriptionTypeItems);

        $payment = $this->submitGiftForm([
            'subscription_type_id' => $subscriptionType->id,
            'custom_amount' => $customAmount,
        ]);

        $this->assertEqualsWithDelta($customAmount, $payment->amount, 0.001);

        $paymentItems = array_values($payment->related('payment_items')->order('id')->fetchAll());
        $this->assertCount(count($expectedItemAmounts), $paymentItems);

        foreach ($paymentItems as $index => $paymentItem) {
            $this->assertSame(GiftPaymentItem::TYPE, $paymentItem->type);
            $this->assertEqualsWithDelta($expectedItemAmounts[$index], $paymentItem->amount, 0.001);
            $this->assertEqualsWithDelta($subscriptionTypeItems[$index]['vat'], $paymentItem->vat, 0.001);
        }
    }

    public function testWithoutCustomAmountKeepsDefaultPrices(): void
    {
        $subscriptionType = $this->createSubscriptionType([
            ['name' => 'digital', 'amount' => 79.00, 'vat' => 23],
            ['name' => 'print', 'amount' => 18.00, 'vat' => 5],
        ]);

        $payment = $this->submitGiftForm([
            'subscription_type_id' => $subscriptionType->id,
        ]);

        $this->assertEqualsWithDelta(97.00, $payment->amount, 0.001);

        $paymentItems = array_values($payment->related('payment_items')->order('id')->fetchAll());
        $this->assertCount(2, $paymentItems);
        $this->assertEqualsWithDelta(79.00, $paymentItems[0]->amount, 0.001);
        $this->assertEqualsWithDelta(18.00, $paymentItems[1]->amount, 0.001);
    }

    public function testCustomAmountWithFreeSubscriptionTypeAddsFormError(): void
    {
        $subscriptionType = $this->createSubscriptionType([
            ['name' => 'free', 'amount' => 0, 'vat' => 0],
        ]);

        $form = $this->giftFormFactory->create($this->user);
        $this->giftFormFactory->onSave = function () {
            $this->fail('onSave must not be called when form has errors');
        };

        $this->giftFormFactory->formSucceeded($form, ArrayHash::from($this->formValues([
            'subscription_type_id' => $subscriptionType->id,
            'custom_amount' => 10.00,
        ])));

        $this->assertTrue($form->hasErrors());
        $this->assertCount(0, $this->paymentsRepository->userPayments($this->user->id)->fetchAll());
    }

    public function testFormOffersOnlyNonRecurrentPaymentGateways(): void
    {
        $recurrentGateway = $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE);

        $form = $this->giftFormFactory->create($this->user);

        /** @var SelectBox $gatewaySelect */
        $gatewaySelect = $form['payment_gateway_id'];
        $gatewayOptions = $gatewaySelect->getItems();
        $this->assertArrayHasKey($this->paymentGateway->id, $gatewayOptions);
        $this->assertArrayNotHasKey($recurrentGateway->id, $gatewayOptions);
    }

    private function createSubscriptionType(array $items): ActiveRow
    {
        $builder = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(array_sum(array_column($items, 'amount')))
            ->setLength(365);

        foreach ($items as $item) {
            $builder->addSubscriptionTypeItem($item['name'], $item['amount'], $item['vat']);
        }

        return $builder->save();
    }

    private function submitGiftForm(array $values): ActiveRow
    {
        $form = $this->giftFormFactory->create($this->user);

        $payment = null;
        $this->giftFormFactory->onSave = function (ActiveRow $savedPayment) use (&$payment) {
            $payment = $savedPayment;
        };

        $this->giftFormFactory->formSucceeded($form, ArrayHash::from($this->formValues($values)));

        $this->assertFalse($form->hasErrors(), implode(', ', $form->getErrors()));
        $this->assertNotNull($payment);

        return $payment;
    }

    private function formValues(array $values): array
    {
        return $values + [
            'payment_gateway_id' => $this->paymentGateway->id,
            'custom_amount' => null,
            'gift_email' => 'giftee@example.com',
            'gift_starts_at' => '2040-01-01 00:00:00',
            'address_id' => null,
            'note' => 'test gift payment',
            'user_id' => $this->user->id,
        ];
    }
}
