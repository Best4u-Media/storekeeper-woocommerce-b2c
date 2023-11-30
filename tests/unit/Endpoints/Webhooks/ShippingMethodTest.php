<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class ShippingMethodTest extends AbstractTest
{
    use CommandRunnerTrait;

    const CREATE_DATADUMP_DIRECTORY = 'events/shippingMethod/create';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createShippingMethod.json';
    const CREATE_DATADUMP_SHIPPING_METHOD = 'moduleFunction.ShopModule.listShippingMethodsForHooks.success.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/shippingMethod/update';
    const UPDATE_DATADUMP_HOOK = 'events/hook.events.updateShippingMethod.json';
    const UPDATE_DATADUMP_SHIPPING_METHOD = 'moduleFunction.ShopModule.listShippingMethodsForHooks.success.json';

    const DELETE_DATADUMP_HOOK = 'events/hook.events.deleteShippingMethod.json';

    public function testCreate()
    {
        $this->initApiConnection();

        $this->createShippingMethod();

        $skShippingMethodFile = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_SHIPPING_METHOD);
        $skShippingMethod = $skShippingMethodFile->getReturn()['data'];
        $skShippingMethodData = new Dot(current($skShippingMethod));

        $this->assertShippingMethod($skShippingMethodData);
    }

    public function testUpdate()
    {
        $this->initApiConnection();

        $this->createShippingMethod();

        $this->mockApiCallsFromDirectory(self::UPDATE_DATADUMP_DIRECTORY);
        $this->executeWebhook(self::UPDATE_DATADUMP_HOOK);

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $skShippingMethodFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_SHIPPING_METHOD);
        $skShippingMethod = $skShippingMethodFile->getReturn()['data'];
        $skShippingMethodData = new Dot(current($skShippingMethod));

        $this->assertShippingMethod($skShippingMethodData);
    }

    public function testDelete()
    {
        $this->initApiConnection();

        $this->createShippingMethod();

        $this->executeWebhook(self::DELETE_DATADUMP_HOOK);
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $woocommerceShippingZones = \WC_Shipping_Zones::get_zones();
        $this->assertCount(0, $woocommerceShippingZones, 'No shipping zones should be left');
        $this->assertEquals(0, ShippingZoneModel::count(), 'No shipping zone entity should be left');
        $this->assertEquals(0, ShippingMethodModel::count(), 'No shipping method entity should be left');
    }

    private function createShippingMethod()
    {
        $this->mockApiCallsFromDirectory(self::CREATE_DATADUMP_DIRECTORY);
        $this->executeWebhook(self::CREATE_DATADUMP_HOOK);

        $this->runner->execute(ProcessAllTasks::getCommandName());
    }

    private function executeWebhook(string $hookFile): \WP_REST_Response
    {
        $dumpFile = $this->getHookDataDump($hookFile);
        $request = $this->getRestWithToken($dumpFile);

        return $this->handleRequest($request);
    }

    private function assertShippingMethod(Dot $skShippingMethodData): void
    {
        $woocommerceShippingZones = \WC_Shipping_Zones::get_zones();
        $expectedZones = $skShippingMethodData->get('country_iso2s');

        $this->assertCount(count($expectedZones), $woocommerceShippingZones, 'Zones created should match expected count');
        $this->assertEquals(count($expectedZones), ShippingZoneModel::count(), 'Shipping zone model should match expected count');
        $this->assertEquals(count($expectedZones), ShippingMethodModel::count(), 'Shipping methods should be the same as the zones count');

        // Check at least 1 shipping zone, it contains the same shipping method anyway
        $woocommerceShippingZoneId = current($woocommerceShippingZones)['id'];
        $woocommerceShippingZone = new \WC_Shipping_Zone($woocommerceShippingZoneId);

        $woocommerceShippingMethods = $woocommerceShippingZone->get_shipping_methods();
        $this->assertCount(1, $woocommerceShippingMethods, 'Only one shipping method should be create for this zone');
        $woocommerceShippingMethod = current($woocommerceShippingMethods);

        // Expected to be free shipping based on datadump
        $this->assertInstanceOf(\WC_Shipping_Free_Shipping::class, $woocommerceShippingMethod, 'Shipping method is expected to be instance of free shipping');
        $this->assertEquals($skShippingMethodData->get('shipping_method_price_flat_strategy.free_from_value_wt'), $woocommerceShippingMethod->min_amount, 'Minimum amount should match expected value');
        $this->assertEquals($skShippingMethodData->get('name'), $woocommerceShippingMethod->title, 'Shipping method title should match expected');
    }
}
