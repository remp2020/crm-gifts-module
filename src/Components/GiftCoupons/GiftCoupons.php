<?php

namespace Crm\GiftsModule\Components;

use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\GiftsModule\Repository\PaymentGiftCouponsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tracy\Debugger;

/**
 * This component renders button with dropdown listing of gifted subscriptions in listing actions column.
 * Shows modal with gifted subscription detail after click on listing item.
 *
 * @package Crm\ProductsModule\Components
 */
class GiftCoupons extends BaseLazyWidget
{
    private $templateName = 'gift_coupons.latte';

    private $paymentGiftCouponsRepository;

    private $usersRepository;

    private $translator;

    private $userDateHelper;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentGiftCouponsRepository $paymentGiftCouponsRepository,
        UsersRepository $usersRepository,
        Translator $translator,
        UserDateHelper $userDateHelper
    ) {
        parent::__construct($lazyWidgetManager);

        $this->paymentGiftCouponsRepository = $paymentGiftCouponsRepository;
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
        $this->userDateHelper = $userDateHelper;
    }

    public function header($id = '')
    {
        return 'coupon modal';
    }

    public function identifier()
    {
        return 'couponmodal';
    }

    public function render(ActiveRow $payment)
    {
        $giftCoupons = $payment->related('payment_gift_coupons')->fetchAll();
        $users = [];

        if (empty($giftCoupons)) {
            return;
        }

        foreach ($giftCoupons as $giftCoupon) {
            // optimization to avoid search of user by email unless necessary
            if ($giftCoupon->subscription_id) {
                $users[$giftCoupon->email] = $giftCoupon->subscription->user;
            } else {
                $users[$giftCoupon->email] = $this->usersRepository->getByEmail($giftCoupon->email);
            }
        }

        $this->template->users = $users;
        $this->template->payment = $payment;
        $this->template->giftCoupons = $giftCoupons;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    protected function createComponentGiftEditForm()
    {
        return new Multiplier(function ($paymentGiftCouponId) {

            $paymentGiftCoupon = $this->paymentGiftCouponsRepository->find($paymentGiftCouponId);
            if (!$paymentGiftCoupon) {
                throw new \Exception("Unable to find payment gift coupon with ID [{$paymentGiftCouponId}].");
            }
            $formDisabled = false;
            // disable editing if gift was already sent or start time is in less than 5 minutes (just to be sure)
            if ($paymentGiftCoupon->status === PaymentGiftCouponsRepository::STATUS_SENT
                || $paymentGiftCoupon->starts_at < new DateTime('now + 5 minutes')) {
                $formDisabled = true;
            }

            $form = new Form();
            $form->setRenderer(new BootstrapRenderer());
            $form->setTranslator($this->translator);
            $form->addProtection();

            $form->addText('email', 'gifts.components.gift_coupons.email')
                ->setDisabled($formDisabled);

            $form->addText('starts_at', 'gifts.components.gift_coupons.start_at')
                ->setHtmlAttribute('placeholder', 'subscriptions.data.subscriptions.placeholder.start_time')
                ->setRequired('subscriptions.data.subscriptions.required.start_time')
                ->setHtmlAttribute('class', 'flatpickr')
                ->setHtmlAttribute('flatpickr_datetime_seconds', "1")
                ->setHtmlAttribute('flatpickr_mindate', "today")
                ->setHtmlAttribute('flatpickr_allow_invalid_preload', "1")
                ->setDisabled($formDisabled);

            $form->addHidden('id');
            $form->setDefaults($paymentGiftCoupon);

            $form->addSubmit('send', 'system.save')
                ->setDisabled($formDisabled)
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

            $form->onSuccess[] = [$this, 'formSucceeded'];

            return $form;
        });
    }

    public function formSucceeded(Form $form, array $values)
    {
        $paymentGiftCoupon = $this->paymentGiftCouponsRepository->find($values['id']);
        if (!$paymentGiftCoupon) {
            $msg = $this->translator->translate(
                'gifts.components.gift_coupons.errors.payment_gift_coupon_not_found',
                ['paymentGiftCouponId' => $values['id']]
            );
            Debugger::log("Form submitted for payment gift coupon ID {$values['id']} we are unable to find.", Debugger::ERROR);
            $form->addError($msg);
            // TODO: error message duplicated until AJAX submitting is implemented (modal is closed after submit; probably needs bigger refactor)
            $this->getPresenter()->flashMessage($msg, 'error');
            return;
        }

        if ($paymentGiftCoupon->status === PaymentGiftCouponsRepository::STATUS_SENT
            || $paymentGiftCoupon->starts_at < new DateTime()) {
            $msg = $this->translator->translate(
                'gifts.components.gift_coupons.errors.already_sent',
                ['paymentGiftCouponId' => $values['id'], 'email' => $paymentGiftCoupon->email]
            );
            $form->addError($msg);
            // TODO: error message duplicated until AJAX submitting is implemented (modal is closed after submit; probably needs bigger refactor)
            $this->getPresenter()->flashMessage($msg, 'error');
            return;
        }

        // fix flatpickr's incorrect (missing) time zone (https://github.com/flatpickr/flatpickr/issues/1532)
        $startsAt = DateTime::from($values['starts_at'])->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        if ($startsAt < new DateTime()) {
            $msg = $this->translator->translate(
                'gifts.components.gift_coupons.errors.starts_at_in_past',
                ['paymentGiftCouponId' => $values['id'], 'paymentGiftCouponStartsAt' => $this->userDateHelper->process($startsAt)]
            );
            $form->addError($msg);
            // TODO: error message duplicated until AJAX submitting is implemented (modal is closed after submit; probably needs bigger refactor)
            $this->getPresenter()->flashMessage($msg, 'error');
            return;
        }

        $this->paymentGiftCouponsRepository->update($paymentGiftCoupon, [
            'email' => $values['email'],
            'starts_at' => DateTime::from($values['starts_at']),
        ]);

        $this->getPresenter()->flashMessage($this->translator->translate(
            'gifts.components.gift_coupons.success',
            ['paymentGiftCouponId' => $values['id']]
        ));
    }
}
