<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class GiftsModuleInitMigration extends AbstractMigration
{
    public function up()
    {
        // update table with new column (in case products module was enabled)
        if ($this->table('payment_gift_coupons')->exists()) {
            if (!$this->table('payment_gift_coupons')->hasColumn('subscription_type_id')) {
                $this->table('payment_gift_coupons')
                    ->addColumn('subscription_type_id', 'integer', ['after' => 'product_id', 'null' => true, 'default' => null])
                    ->addForeignKey('subscription_type_id', 'subscription_types', 'id')
                    ->changeColumn('product_id', 'integer', ['null' => true, 'default' => null])
                    ->update();
            }
        } else {
        // create table otherwise
            $sql = <<<SQL
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE `payment_gift_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `subscription_type_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `starts_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `product_id` (`product_id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `subscription_type_id` (`subscription_type_id`),
  CONSTRAINT `payment_gift_coupons_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `payment_gift_coupons_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `payment_gift_coupons_ibfk_3` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
  CONSTRAINT `payment_gift_coupons_ibfk_4` FOREIGN KEY (`subscription_type_id`) REFERENCES `subscription_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;

            $this->execute($sql);
        }
    }

    public function down()
    {
        throw new IrreversibleMigrationException('Cannot remove column `subscription_type_id` or change `product_id` to mandatory. It could create broken gift coupons. Check `PaymentGiftCouponsAddSubscriptionType` migration to see how to migrate anyway.');

        /**
         * Manual rollback to `payment_gift_coupons` with products only:
         *  - removes all `payment_gift_coupons` without `product_id`
         *  - removes foreign key & column `subscription_type_id`
         *  - switches column `product_id` back to mandatory
         *
         * WARNING: This will destroy existing gift coupons created with `subscription_type`.
         *
         * ```sql
         * DELETE FROM `payment_gift_coupons` WHERE `product_id` IS NULL;
         * ALTER TABLE `payment_gift_coupons` DROP FOREIGN KEY `payment_gift_coupons_ibfk_4`;
         * ALTER TABLE `payment_gift_coupons` DROP COLUMN `subscription_type_id`;
         * ALTER TABLE `payment_gift_coupons` MODIFY `product_id` INT(11) NOT NULL;
         * ```
         */
    }
}
