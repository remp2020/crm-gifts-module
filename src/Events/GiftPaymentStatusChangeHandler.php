<?php

namespace Crm\GiftsModule\Events;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerationException;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\InvoicesModule\Models\Generator\PaymentNotInvoiceableException;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use Tracy\ILogger;

class GiftPaymentStatusChangeHandler extends AbstractListener
{
    private bool $sendAttachment = true;

    public function __construct(
        private readonly ApplicationConfig $applicationConfig,
        private readonly Emitter $emitter,
        private readonly PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        private readonly InvoiceGenerator $invoiceGenerator,
    ) {
    }

    /*
     * Useful in tests
     */
    public function disableAttachment(): void
    {
        $this->sendAttachment = false;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PaymentChangeStatusEvent)) {
            throw new \Exception('Invalid type of event received, PaymentChangeStatusEvent expected: ' . get_class($event));
        }

        /** @var ActiveRow $payment */
        $payment = $event->getPayment();

        if ($payment->status !== PaymentStatusEnum::Paid->value) {
            return;
        }

        $paymentGiftCoupon = $this->paymentGiftCouponsRepository->findByPayment($payment)->fetch();

        if ($paymentGiftCoupon) {
            $attachmentName = $this->applicationConfig->get('gift_subscription_coupon_attachment');
            $attachments = [];
            if ($this->sendAttachment) {
                // attach coupon
                try {
                    $attachment = file_get_contents($attachmentName);
                    if ($attachment !== false) {
                        $attachments[] = [
                            'file' => 'coupon.pdf',
                            'content' => $attachment,
                            'mime_type' => 'application/pdf',
                        ];
                    } else {
                        Debugger::log(
                            "Coupon attachment [{$attachmentName}] not loaded. Payment ID: [{$payment->id}]",
                            ILogger::ERROR
                        );
                    }
                } catch (\Exception $e) {
                    Debugger::log(
                        "Coupon attachment [{$attachmentName}] load failed. Payment ID: [{$payment->id}]. " .
                        "Exception: {$e->getCode()} - {$e->getMessage()}",
                        ILogger::ERROR
                    );
                }

                // attach invoice
                try {
                    $attachment = $this->invoiceGenerator->renderInvoiceMailAttachment($payment);
                    if ($attachment) {
                        $attachments[] =[
                            'content' => $attachment['content'],
                            'file' => $attachment['file'],
                            'mime_type' => 'application/pdf',
                        ];
                    }
                } catch (PaymentNotInvoiceableException $e) {
                    // Do nothing, no invoice attachment; exception may be raised for valid payments that are not invoiceable
                } catch (InvoiceGenerationException $e) {
                    Debugger::log('Unable to attach invoice, error: ' . $e->getMessage(), ILogger::ERROR);
                }
            }

            $this->emitter->emit(new NotificationEvent(
                emitter: $this->emitter,
                user: $payment->user,
                templateCode: 'created_payment_gift_coupon',
                params: [
                    'variable_symbol' => $payment->variable_symbol,
                    'donated_to_email' => $paymentGiftCoupon->email,
                    'gift_starts_at' => $paymentGiftCoupon->starts_at->format(DATE_RFC3339),
                ],
                attachments: $attachments,
            ));
        }
    }
}
