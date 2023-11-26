<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShippingMethods;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;

class SyncWoocommerceShippingMethodsTest extends AbstractTest
{
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-shipping-methods';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule.listShippingMethodsForHooks.success.json';

    public function testRun()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY);

        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $originalShippingMethodsData = $file->getReturn()['data'];

        $expectedShippingMethodsPerCountry = [
            'SK_NL' => [
                [
                    'title' => 'Pickup at Store', // Pickup at Store does not have countries set but should use country set on company
                    'cost' => '0',
                ],
                [
                    'title' => 'Junmar Express', // Junmar Express does not have countries set but should use country set on company
                    'cost' => '5',
                ],
                [
                    'title' => 'JNT',
                    'cost' => '0',
                ],
                [
                    'title' => 'NinjaVan',
                    'cost' => '10',
                ],
            ],
            'SK_DE' => [
                [
                    'title' => 'JNT',
                    'cost' => '0',
                ],
            ],
            'SK_PH' => [
                [
                    'title' => 'NinjaVan',
                    'cost' => '10',
                ],
            ],
        ];

        // TODO: Test per shipping type
        $this->runner->execute(SyncWoocommerceShippingMethods::getCommandName());

        $woocommerceShippingZones = \WC_Shipping_Zones::get_zones();
        $actualCountries = [];
        $actualShippingMethodsPerCountry = [];
        foreach ($woocommerceShippingZones as $shippingZone) {
            $woocommerceShippingZone = new \WC_Shipping_Zone($shippingZone['id']);
            $woocommerceShippingLocations = $woocommerceShippingZone->get_zone_locations();
            $woocommerceShippingLocation = $woocommerceShippingLocations[0];
            $isFromStoreKeeper = !is_null(ShippingZoneModel::getByCountryIso2($woocommerceShippingLocation->code));
            if ($isFromStoreKeeper) {
                $this->assertCount(1, $woocommerceShippingZone->get_zone_locations());
                $zoneName = $woocommerceShippingZone->get_zone_name();
                $actualCountries[] = $zoneName;
                $shippingMethods = $woocommerceShippingZone->get_shipping_methods();
                $actualShippingMethodsPerCountry[$zoneName] = [];
                foreach ($shippingMethods as $shippingMethod) {
                    $actualShippingMethodsPerCountry[$zoneName][] = [
                        'title' => $shippingMethod->title,
                        'cost' => $shippingMethod->cost ?? '0',
                    ];
                }
            }
        }

        $this->assertEquals(array_keys($expectedShippingMethodsPerCountry), $actualCountries, 'Shipping zones should match expected values');
        $this->assertEquals($expectedShippingMethodsPerCountry, $actualShippingMethodsPerCountry, 'Shipping methods does not match');
    }
}
