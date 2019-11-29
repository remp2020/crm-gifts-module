# CRM GiftsModule


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


## How it works

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

5. `SendWelcomeEmailHandler` listens to `UserCreatedEvent`.
   - If created user has `source === PaymentGiftCouponsRepository::USER_SOURCE_GIFT_COUPON`, `NotificationEvent(welcome_email_gift_coupon)` is emitted.

6. `SubscriptionsStartsEventHandler` listens to `SubscriptionStartsEvent`.
   - If subscription has `type === SubscriptionsRepository::TYPE_GIFT` and account wasn't created in last 15 minutes _(by command)_, `NotificationEvent(new_subscription_gift)` is emitted.


[SalesFunnelModule]: https://github.com/remp2020/crm-salesfunnel-module/
