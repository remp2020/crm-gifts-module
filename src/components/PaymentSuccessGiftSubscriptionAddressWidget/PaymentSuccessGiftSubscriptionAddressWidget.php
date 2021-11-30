<?php

namespace Crm\GiftsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\GiftsModule\PaymentItem\GiftPaymentItem;
use Crm\PaymentsModule\Gateways\BankTransfer;
use Crm\PaymentsModule\Presenters\BankTransferPresenter;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SalesFunnelModule\Presenters\SalesFunnelPresenter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

class PaymentSuccessGiftSubscriptionAddressWidget extends BaseWidget
{
    private $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'payment_success_gift_subscription_address_widget.latte';

    public function identifier()
    {
        return 'paymentsuccessgiftsubscriptionaddresswidget';
    }

    /**
     * @throws \Exception
     */
    public function render()
    {
        $payment = $this->getPayment();
        if ($payment->status !== PaymentsRepository::STATUS_PAID && $payment->payment_gateway->code !== BankTransfer::GATEWAY_CODE) {
            return;
        }

        if (!$this->isGiftSubscriptionAddressRequired($payment)) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile($this->templatePath);
        $this->template->render();
    }

    /**
     * @param GiftSubscriptionAddressFormFactory $factory
     * @return mixed
     * @throws \Exception
     */
    public function createComponentGiftSubscriptionAddressForm(GiftSubscriptionAddressFormFactory $factory)
    {
        $payment = $this->getPayment();

        $form = $factory->create($payment);
        $factory->onSave = function ($form, $user) {
            $form['done']->setValue(1);
            $form['send']->setDisabled(1);
            $this->redrawControl('giftSubscriptionFormSnippet');
        };

        return $form;
    }

    public function isGiftSubscriptionAddressRequired(IRow $payment): bool
    {
        // check if subscription is print & gift is enabled (gift = 1)
        $giftPayment = $payment->related('payment_meta')
            ->where('key', 'gift')
            ->where('value', '1')
            ->fetch();

        if (!$giftPayment) {
            return false;
        }

        foreach ($payment->related('payment_items') as $paymentItem) {
            // check if address is needed only for for gift payment items
            if ($paymentItem->type !== GiftPaymentItem::TYPE) {
                continue;
            }

            $subscriptionType = $paymentItem->subscription_type;
            if (!$subscriptionType) {
                continue;
            }
            if ($subscriptionType->ask_address) {
                return true;
            }
        }

        return false;
    }

    public function getPayment(): ActiveRow
    {
        $presenter = $this->getPresenter();
        if ($presenter instanceof SalesFunnelPresenter || $presenter instanceof BankTransferPresenter) {
            return $presenter->getPayment();
        }

        throw new \Exception('PaymentSuccessPrintWidget used within not allowed presenter: ' . get_class($presenter));
    }
}
