<?php

declare(strict_types=1);

namespace Crm\GiftsModule\Components\GiftSubscriptionAdminButtonWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;

/**
 * This widget displays button to open form which allows creation of gift subscription.
 */
class GiftSubscriptionAdminButtonWidget extends BaseLazyWidget
{
    private $templateName = 'gift_subscription_admin_button_widget.latte';

    public function render($id)
    {
        $this->template->userId = $id;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
