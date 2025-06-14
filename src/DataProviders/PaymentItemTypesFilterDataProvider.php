<?php

namespace Crm\GiftsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\GiftsModule\Models\PaymentItem\GiftPaymentItem;
use Crm\PaymentsModule\DataProviders\PaymentItemTypesFilterDataProviderInterface;
use Nette\Localization\Translator;

class PaymentItemTypesFilterDataProvider implements PaymentItemTypesFilterDataProviderInterface
{
    private $filterKey = GiftPaymentItem::TYPE;

    private $translator;

    public function __construct(
        Translator $translator,
    ) {
        $this->translator = $translator;
    }

    public function provide(array $params)
    {
        if (!isset($params['paymentItemTypes'])) {
            throw new DataProviderException('missing [paymentItemTypes] within data provider params');
        }
        if (!is_array($params['paymentItemTypes'])) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['paymentItemTypes']));
        }

        if (!isset($params['paymentItemTypesDefaultFilter'])) {
            throw new DataProviderException('missing [paymentItemTypesDefaultFilter] within data provider params');
        }
        if (!is_array($params['paymentItemTypesDefaultFilter'])) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['paymentItemTypesDefaultFilter']));
        }

        $params['paymentItemTypes'][$this->filterKey] = $this->translator->translate("gifts.data_provider.payment_item_types_filter.key.{$this->filterKey}");
        $params['paymentItemTypesDefaultFilter'][] = $this->filterKey;

        return $params;
    }

    public function filter($selectedTypes)
    {
        if (in_array($this->filterKey, $selectedTypes, true)) {
            return "payment_items.type = '{$this->filterKey}'";
        }

        return null;
    }
}
