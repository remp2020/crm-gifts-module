<?php

namespace Crm\GiftsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\UsersModule\DataProvider\CanDeleteAddressDataProviderInterface;
use Nette\Database\Table\IRow;
use Nette\Localization\ITranslator;

class CanDeleteAddressDataProvider implements CanDeleteAddressDataProviderInterface
{
    private $translator;

    private $paymentMetaRepository;

    public function __construct(
        ITranslator $translator,
        PaymentMetaRepository $paymentMetaRepository
    ) {
        $this->translator = $translator;
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    public function provide(array $params): array
    {
        if (!isset($params['address'])) {
            throw new DataProviderException('address param missing');
        }

        /** @var IRow $address */
        $address = $params['address'];

        $paymentsMeta = $this->paymentMetaRepository->findByMeta(GiftSubscriptionAddressFormFactory::PAYMENT_META_KEY, $address->id);
        if ($paymentsMeta) {
            return [
                'canDelete' => false,
                'message' => $this->translator->translate('gifts.admin.address.cant_delete')
            ];
        }

        return [
            'canDelete' => true
        ];
    }
}
