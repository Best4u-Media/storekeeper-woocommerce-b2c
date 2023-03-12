<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;

class V_20230312_154050_refund extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!PaymentModel::hasTable()) {
            $name = PaymentModel::getTableName();

            $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `wc_order_id` bigint(20) NOT NULL,
        `wc_refund_id` bigint(20) NOT NULL,
        `sk_refund_id` bigint(20) NULL,
        `amount` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }
    }
}
