# CRM GiftsModule

[![Translation status @ Weblate](https://hosted.weblate.org/widgets/remp-crm/-/gifts-module/svg-badge.svg)](https://hosted.weblate.org/projects/remp-crm/gifts-module/)

This module provides gift option to [SalesFunnelModule].


## Installing module

We recommend using Composer for installation and update management.

```shell
composer require remp/crm-gifts-module
```


### Enabling module

Add installed extension to your `app/config/config.neon` file:

```neon
extensions:
	- Crm\GiftsModule\DI\GiftsModuleExtension
```

Run service commands to generate CRM internals:

```
php bin/command.php phinx:migrate
php bin/command.php user:generate_access
php bin/command.php api:generate_access
php bin/command.php application:seed
```

## [SalesFunnelModule] integration

1. Add to your sales funnel `payment_metadata` fields _(have to be sent together with other required sales funnel's fields; see [SalesFunnelModule] README)_:
   - `payment_metadata[gift]` _(boolean)_ - if true, indicates that this purchase is gift.
   - `payment_metadata[gift_email]` _(string)_ - email of customer for which is gift purchased. This can be email of existing or new customer. Gift process handles creation of new accounts.
   - `payment_metadata[gift_starts_at]` _(string)_ - date when should gifted subscription start. Use `DateTimeInterface::RFC3339`.

   **All three fields are mandatory**.

2. Put `php bin/command.php gifts:activate_purchased_gift_coupons` into cron. We recommend running it every 5 to 10 minutes.

3. Create event listeners for these `NotificationEvent`s if you want to inform customers about gift related events:
   - `created_payment_gift_coupon` - emitted when payment for gift subscription for confirmed _(paid)_. Send to donor.
   - `welcome_email_gift_coupon` - emitted when gift subscription was created _(gift subscription is created by command from previous step at `gift_starts_at` datetime)_ and donee's account was created in CRM. Send to donee _(email from `gift_email` field)_.
   - `new_subscription_gift` - emitted when gift subscription was created _(gift subscription is created by command from previous step at `gift_starts_at` datetime)_ and donee's account already existed in CRM. Send to donee _(email from `gift_email` field)_.

4. Set gift coupon attachment which should be attached to notification event sent to donor. See application config `gift_subscription_coupon_attachment`, can be found in web administration under category `Subscriptions`.


### How it works

1. `PaymentItemContainerReadyEventHandler` listens to `PaymentItemContainerReadyEvent`.
   - If `payment_metadata` contains valid gift fields _(see above)_, it switches `SubscriptionTypePaymentItem` to `GiftPaymentItem`.

2. `CreateGiftCouponNewPaymentEventHandler` listens to `NewPaymentEvent` _(payment is created; not paid)_.
   - If `payment_meta` contains valid gift fields, it removes `subscription_type_id` from Payment _(to prevent creation of subscription for donor's account)_.
   - Afterwords it creates `payment_gift_coupons` entry from gift fields.
      - _This is created before payment is confirmed for legacy reasons. This helps helpdesk to search and to pair payment with gifts._

3. `GiftPaymentStatusChangeHandler` listens to `PaymentChangeStatusEvent`.
   - If payment is PAID and it is gift subscription purchase _(payment's meta contains `gift === 1`)_, `NotificationEvent(created_payment_gift_coupon)` with coupon attached _(see integration)_ is emitted.
   
4. Command `php bin/command.php gifts:activate_purchased_gift_coupons` is running in background and activating gift subscriptions when time >= `gift_starts_at`.
   - Donee account:
      - If account with `gift_email` exists, it is used for gift.
      - Otherwise new account is created with `user.source = PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON`.
   - New subscription is attached to donee's account with `subscription.type = SubscriptionsRepository::TYPE_GIFT`.
   - You can configure subscription extension method by calling `setExtendMethod` in your configuration file like this:
	```neon
	activatePurchasedGiftCouponsCommand:
		setup:
			- setExtendMethod('extend_same_content_access')
	```

5. `SendWelcomeEmailHandler` listens to `UserRegisteredEvent`.
   - If created user has `source === PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON`, `NotificationEvent(welcome_email_gift_coupon)` is emitted.

6. `SubscriptionsStartsEventHandler` listens to `SubscriptionStartsEvent`.
   - If subscription has `type === SubscriptionsRepository::TYPE_GIFT` and account wasn't created in last 15 minutes _(by command)_, `NotificationEvent(new_subscription_gift)` is emitted.

## Notification email after the payment

Both of the following options require [REMP Mailer](https://github.com/remp2020/mailer-skeleton/) integration, or other custom implementation handling `NotificationEvent`.

### Default email

If you want to just use the defaults provided by the module, you can enable the event handler responsible for sending the notification email. In one of your custom (internal) modules, add this snippet:

```php
<?php

namespace Crm\FooModule;

class FooModule extends CrmModule
{
    public function registerEventHandlers(\League\Event\Emitter\Emitter $emitter)
    {
        // ...
        $emitter->addListener(
            \Crm\PaymentsModule\Events\PaymentChangeStatusEvent::class,
            $this->getInstance(\Crm\GiftsModule\Events\GiftPaymentStatusChangeHandler::class)
        );
        // ...
    }
    // ...
}
```

GiftsModule will try to send an email with template code `created_payment_gift_coupon`. You can use these variables in your template:

- `variable_symbol` (string)
- `donated_to_email` (string)
- `gift_starts_at` (RFC3339-formatted string)

You can also configure `gift_subscription_coupon_attachment` option in the CRM admin configuration. If it's present, the email will use the attachment provided in the config (e.g. with PDF coupon). The attachment is static and not personalized. 

### [ScenariosModule]

If you don't want to use the defaults, you can configure custom scenario to send the emails.

To differentiate gift emails from the regular ones, you need to add a scenario condition that matches your implementation. The two most common are:

- _Payment - Payment has items of type_. Use this if you create the _gift_ payment manually when you create the payment (e.g. in sales funnels). You can then make a condition to send payments with _gift_ items different email.
- _Order - Has product with template name_. Use this if you use our [ProductsModule] (eshop) to sell your gift subscriptions. If you use it, you probably separated your gift subscriptions with their own shop template. You can use it to differentiate these type of orders.

You're free to extend the scenario and your own conditions. Read more in the [ScenariosModule] README.

## DataProviders

### SubscriptionFormDataProvider - validation within subscription's update form

`SubscriptionFormDataProvider` was added to validate update of start time for gifted subscriptions against parent payment's `paid_at` date. For accounting reasons, the subscription must not start before the payment confirmation date.

----

[ProductsModule]: https://github.com/remp2020/crm-products-module/
[SalesFunnelModule]: https://github.com/remp2020/crm-salesfunnel-module/
[ScenariosModule]: https://github.com/remp2020/crm-scenarios-module/
