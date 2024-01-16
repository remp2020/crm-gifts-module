<?php

namespace Crm\GiftsModule\Components\GiftPaymentItemsListWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\GiftsModule\Models\PaymentItem\GiftPaymentItem;
use Nette\Database\Table\ActiveRow;

class GiftPaymentItemsListWidget extends BaseLazyWidget
{
    private $templateName = 'gift_payment_items_list_widget.latte';

    public function identifier()
    {
        return 'gitfpaymentitemslistwidget';
    }

    public function render(ActiveRow $paymentItem)
    {
        if ($paymentItem->type !== GiftPaymentItem::TYPE) {
            return;
        }

        $this->template->paymentItem = $paymentItem;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
