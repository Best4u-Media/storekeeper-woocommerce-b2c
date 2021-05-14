<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\Tools\AttributeTranslator;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WooCommerceAttributeMetadata;

class Activator
{
    public function run()
    {
        $this->setWooCommerceToken();
        $this->setWooCommerceUuid();
        $this->setOrderPrefix();
        $this->setMainCategoryId();
        $this->createWebhookLogsTable();
        $this->createTaskTable();
        $this->createAttributeMetadataTable();
        $this->createAttributeTranslationTable();
        $this->createOrdersPaymentsTable();
        $this->createRedirectTable();
        $this->setVersion();
    }

    private function setWooCommerceToken($length = 64)
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::WOOCOMMERCE_TOKEN)) {
            $random_bytes = openssl_random_pseudo_bytes($length / 2);
            $token = bin2hex($random_bytes);
            WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_TOKEN, $token);
        }
    }

    private function setWooCommerceUuid()
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::WOOCOMMERCE_UUID)) {
            $uuid = wp_generate_uuid4();
            WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_UUID, $uuid);
        }
    }

    private function createAttributeTranslationTable()
    {
        AttributeTranslator::createTable(); // Create the table if it doesn't exist
    }

    private function createAttributeMetadataTable()
    {
        WooCommerceAttributeMetadata::createTable(); // Create the attribute metadata table if it doesn't exists
    }

    private function createOrdersPaymentsTable()
    {
        PaymentGateway::createTable(); // Create the orders payments table if it doesn't exists
    }

    private function createRedirectTable()
    {
        RedirectHandler::createTable(); // Create the table if it doesn't exist
    }

    private function setOrderPrefix()
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::ORDER_PREFIX)) {
            WooCommerceOptions::set(WooCommerceOptions::ORDER_PREFIX, 'WC');
        }
    }

    private function setMainCategoryId()
    {
        if (!StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID)) {
            StoreKeeperOptions::set(StoreKeeperOptions::MAIN_CATEGORY_ID, 0);
        }
    }

    private function setVersion()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::INSTALLED_VERSION, STOREKEEPER_WOOCOMMERCE_B2C_VERSION);
    }

    private function createWebhookLogsTable()
    {
        WebhookLogModel::ensureTable();
    }

    private function createTaskTable()
    {
        TaskModel::ensureTable();
    }
}
