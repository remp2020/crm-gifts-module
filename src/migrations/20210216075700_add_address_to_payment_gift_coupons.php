<?php

use Phinx\Migration\AbstractMigration;

class AddAddressToPaymentGiftCoupons extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_gift_coupons')
            ->addColumn('address_id', 'integer', ['null' => true])
            ->addForeignKey('address_id', 'addresses')
            ->update();
    }
}
