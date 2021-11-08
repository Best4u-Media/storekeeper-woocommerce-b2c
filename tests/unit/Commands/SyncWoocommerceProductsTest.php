<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFeaturedAttributes;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceSingleProduct;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;

class SyncWoocommerceProductsTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-products';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.72e551759ae4651bdb99611a255078af300eb8b787c2a8b9a216b800b8818b06.json';
    const DATADUMP_PRODUCT_21_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.success.613db2c03f849.json';
    const DATADUMP_CONFIGURABLE_OPTIONS_FILE = 'moduleFunction.ShopModule::getConfigurableShopProductOptions.5e20566c4b0dd01fa60732d6968bc565b60fbda96451d989d00e35cc6d46e04a.json';

    /**
     * Initialize the tests by following these steps:
     * 1. Initialize the API connection and the mock API calls
     * 2. Make sure there are no products imported
     * 3. Run the 'wp sk sync-woocommerce-products' command
     * 4. Run the 'wp sk process-all-tasks' command to process the tasks spawned by the import ( parent recalculation ).
     *
     * @throws \Throwable
     */
    protected function initializeTest($storekeeperId = null)
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');
        $this->runner->execute(SyncWoocommerceFeaturedAttributes::getCommandName());

        // Tests whether there are no products before import
        $wc_products = wc_get_products([]);
        $this->assertEquals(
            0,
            count($wc_products),
            'Test was not ran in an empty environment'
        );

        if (!is_null($storekeeperId)) {
            $this->runner->addCommandClass(SyncWoocommerceSingleProduct::class);
            $this->runner->execute(SyncWoocommerceSingleProduct::getCommandName(), [
            ], [
                'storekeeper_id' => $storekeeperId,
            ]);
        } else {
            // Run the product import command
            $this->runner->execute(SyncWoocommerceProducts::getCommandName());
        }

        // Process all the tasks that get spawned by the product import command
        $this->runner->execute(ProcessAllTasks::getCommandName());
    }

    /**
     * Fetch the data from the datadump source file.
     */
    protected function getReturnData($file = self::DATADUMP_SOURCE_FILE): array
    {
        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.$file);

        return $file->getReturn()['data'];
    }

    public function testSimpleProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the simple products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_SIMPLE
        );

        // Retrieve all synchronised simple products
        $wc_simple_products = wc_get_products(['type' => self::WC_TYPE_SIMPLE]);
        $this->assertEquals(
            count($original_product_data),
            count($wc_simple_products),
            'Amount of synchronised simple products doesn\'t match source data'
        );

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wc_products = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );
            $this->assertEquals(
                1,
                count($wc_products),
                'More then one product found with the provided storekeeper_id'
            );

            // Get the simple product with the storekeeper_id
            $wc_simple_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_SIMPLE,
                $wc_simple_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_simple_product);
        }
    }

    public function testOrderableSimpleProductStock()
    {
        $productStorekeeperId = 22;
        $this->initializeTest($productStorekeeperId);

        $wooCommerceProducts = wc_get_products(['type' => self::WC_TYPE_SIMPLE]);

        $this->assertCount(1, $wooCommerceProducts, 'Error in test, multiple products imported');
        $wooCommerceProduct = $wooCommerceProducts[0];
        $expected = [
            'sku' => 'MWVR2ORDERABLE',
            'manage_stock' => true,
            'stock_quantity' => 0, // in reality -15, but we force set to 0
            'stock_status' => 'onbackorder', // because "backorder_enabled": true,
        ];
        $actual = [
            'sku' => $wooCommerceProduct->get_sku(),
            'manage_stock' => $wooCommerceProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
            'stock_quantity' => $wooCommerceProduct->get_stock_quantity(),
            'stock_status' => $wooCommerceProduct->get_stock_status(),
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testOrderableConfigurableProductStock()
    {
        $productStorekeeperId = 23;
        $this->initializeTest($productStorekeeperId);

        $wooCommerceProducts = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);

        $this->assertCount(1, $wooCommerceProducts, 'Error in test, multiple products imported');
        $wooCommerceProduct = $wooCommerceProducts[0];
        $expected = [
            'sku' => 'MWVR2OCONFIG',
            'manage_stock' => true,
            'stock_quantity' => 75,
            'stock_status' => 'instock',
        ];
        $actual = [
            'sku' => $wooCommerceProduct->get_sku(),
            'manage_stock' => $wooCommerceProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
            'stock_quantity' => $wooCommerceProduct->get_stock_quantity(),
            'stock_status' => $wooCommerceProduct->get_stock_status(),
        ];
        $this->assertEquals($expected, $actual, 'stock of configurable product');

        $assigned_stock_expected = [
            'MWVR2-in-stock-75' => [
                'manage_stock' => true,
                'stock_quantity' => 75,
                'stock_status' => 'instock',
            ],
            'MWVR2-out-of-stock' => [
                'manage_stock' => true,
                'stock_quantity' => 0, // in reality -10
                'stock_status' => 'onbackorder', // because "backorder_enabled": true,
            ],
        ];
        $assigned_stock_actual = [];
        foreach ($wooCommerceProduct->get_visible_children() as $childId) {
            $variationProduct = new \WC_Product_Variation($childId);
            $assigned_stock_actual[$variationProduct->get_sku()] = [
                'manage_stock' => $variationProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
                'stock_quantity' => $variationProduct->get_stock_quantity(),
                'stock_status' => $variationProduct->get_stock_status(),
            ];
        }
        ksort($assigned_stock_actual);

        $this->assertEquals($assigned_stock_expected, $assigned_stock_actual, 'stock of assigned products');
    }

    public function testAttributesAndOptionsOrder()
    {
        $productStorekeeperId = 21;
        $this->initializeTest($productStorekeeperId);

        // Create a collection with all of the variations of configurable products from WooCommerce
        $actualAttributesPosition = [];
        $wooCommerceConfigurableProduct = current(wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]));

        $parentProduct = new \WC_Product_Variable($wooCommerceConfigurableProduct->get_id());
        $wooCommerceAttributes = $parentProduct->get_attributes();
        foreach ($wooCommerceAttributes as $wooCommerceAttribute) {
            /* @var $wooCommerceAttribute \WC_Product_Attribute */
            $attributeName = $this->cleanAttributeName($wooCommerceAttribute->get_name());
            $actualAttributesPosition[$attributeName] = $wooCommerceAttribute->get_position();
        }

        $this->assertEquals([
            'barcode' => 4,
            'kleur' => 3,
        ], $actualAttributesPosition);

        $actualAttributeOptionPosition = [];
        foreach ($parentProduct->get_visible_children() as $childId) {
            $variationProduct = new \WC_Product_Variation($childId);
            $actualAttributeOptionPosition[$variationProduct->get_sku()] = $variationProduct->get_menu_order();
        }
        $this->assertEquals(
            [
                'MWVR2-wit' => 3,
                'MWVR2-zwart' => 4,
            ],
            $actualAttributeOptionPosition,
            'Menu order for '.$variationProduct->get_title()
        );
    }

    public function cleanAttributeName($name)
    {
        $name = str_replace(['attribute_', 'pa_'], '', $name);

        return strtolower(trim($name));
    }

    public function getAttributeWithPositions($products)
    {
        $positions = [];
        foreach ($products as $product) {
            $dot = new Dot($product);
            $contentVars = $dot->get('flat_product.content_vars');
            foreach ($contentVars as $data) {
                $contentVar = new Dot($data);
                $attributeName = $this->cleanAttributeName($contentVar->get('name'));
                $positions[$attributeName] = $contentVar->get('attribute_order');
            }
        }

        return $positions;
    }

    public function testConfigurableProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the configurable products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_CONFIGURABLE
        );

        // Retrieve all synchronised configurable products
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        $this->assertEquals(
            count($original_product_data),
            count($wc_configurable_products),
            'Amount of synchronised configurable products doesn\'t match source data'
        );

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wc_products = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );
            $this->assertEquals(
                1,
                count($wc_products),
                'More then one product found with the provided storekeeper_id'
            );

            // Get the simple product with the storekeeper_id
            $wc_configurable_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_CONFIGURABLE,
                $wc_configurable_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_configurable_product);
        }
    }

    public function testAssignedProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the assigned products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_ASSIGNED
        );

        // Create a collection with all of the variations of configurable products from WooCommerce
        $wc_assigned_products = [];
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        foreach ($wc_configurable_products as $wc_configurable_product) {
            $parent_product = new \WC_Product_Variable($wc_configurable_product->get_id());
            foreach ($parent_product->get_visible_children() as $index => $visible_child_id) {
                $wc_variation_product = new \WC_Product_Variation($visible_child_id);
                $storekeeper_id = $wc_variation_product->get_meta('storekeeper_id');
                $wc_assigned_products[$storekeeper_id] = $wc_variation_product;
            }
        }

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve assigned product with the storekeeper_id from the source data
            $wc_assigned_product = $wc_assigned_products[$original->get('id')];
            $this->assertNotEmpty($wc_assigned_product, 'No assigned product with the given storekeeper id');

            $this->assertProduct($original, $wc_assigned_product);
        }
    }
}
