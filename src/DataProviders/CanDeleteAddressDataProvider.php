<?php

namespace Crm\GiftsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\GiftsModule\Forms\GiftSubscriptionAddressFormFactory;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\UsersModule\DataProviders\CanDeleteAddressDataProviderInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;

class CanDeleteAddressDataProvider implements CanDeleteAddressDataProviderInterface
{
    private $translator;

    private $paymentMetaRepository;

    public function __construct(
        Translator $translator,
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

        /** @var ActiveRow $address */
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
