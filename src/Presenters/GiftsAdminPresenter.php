<?php

declare(strict_types=1);

namespace Crm\GiftsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\GiftsModule\Forms\GiftFormFactory;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;

class GiftsAdminPresenter extends AdminPresenter
{
    #[Inject]
    public GiftFormFactory $giftFormFactory;

    #[Inject]
    public UsersRepository $usersRepository;

    /**
     * @admin-access-level write
     */
    public function renderNew($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }
        $this->template->userRow = $user;
    }

    public function createComponentGiftForm()
    {
        $user = null;

        if (isset($this->params['userId'])) {
            $user = $this->usersRepository->find($this->params['userId']);
        }
        if (!$user) {
            throw new BadRequestException();
        }

        $form = $this->giftFormFactory->create($user);

        $this->giftFormFactory->onSave = function ($payment) {
            $this->flashMessage($this->translator->translate('payments.admin.payments.created'));
            $this->redirect(':Payments:PaymentsAdmin:show', $payment->id);
        };

        return $form;
    }
}
