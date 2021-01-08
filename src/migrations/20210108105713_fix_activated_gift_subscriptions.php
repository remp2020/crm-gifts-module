<?php

use Crm\GiftsModule\Seeders\AddressTypesSeeder;
use Phinx\Migration\AbstractMigration;

class FixActivatedGiftSubscriptions extends AbstractMigration
{
    public function up()
    {
        $giftSubscriptionType = AddressTypesSeeder::GIFT_SUBSCRIPTION_ADDRESS_TYPE;
        $sql = <<<SQL
UPDATE address_change_requests
JOIN addresses on address_id = addresses.id
SET address_change_requests.user_id = addresses.user_id,
    address_change_requests.type = addresses.type
WHERE address_change_requests.user_id != addresses.user_id
    AND address_change_requests.type = '{$giftSubscriptionType}'
SQL;
        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
