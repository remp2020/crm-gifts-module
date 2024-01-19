<?php

namespace Crm\GiftsModule\Events;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\GiftsModule\Repositories\PaymentGiftCouponsRepository;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use Tracy\ILogger;

class GiftPaymentStatusChangeHandler extends AbstractListener
{
    private $applicationConfig;

    private $emitter;

    private $paymentGiftCouponsRepository;

    private $sendAttachment = true;

    public function __construct(
        ApplicationConfig $applicationConfig,
        Emitter $emitter,
        PaymentGiftCouponsRepository $paymentGiftCouponsRepository
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->emitter = $emitter;
        $this->paymentGiftCouponsRepository = $paymentGiftCouponsRepository;
    }

    /*
     * Useful in tests
     */
    public function disableAttachment()
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

        if ($payment->status != PaymentsRepository::STATUS_PAID) {
            return;
        }

        $paymentGiftCoupon = $this->paymentGiftCouponsRepository->findByPayment($payment)->fetch();

        if ($paymentGiftCoupon) {
            $attachmentName = $this->applicationConfig->get('gift_subscription_coupon_attachment');
            $attachments = [];
            if ($this->sendAttachment) {
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
            }

            $this->emitter->emit(new NotificationEvent(
                $this->emitter,
                $payment->user,
                'created_payment_gift_coupon',
                [
                    'variable_symbol' => $payment->variable_symbol,
                    'donated_to_email' => $paymentGiftCoupon->email,
                    'gift_starts_at' => $paymentGiftCoupon->starts_at->format(DATE_RFC3339),
                ],
                null,
                $attachments
            ));
        }
    }
}
