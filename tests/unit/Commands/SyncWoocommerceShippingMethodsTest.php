<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShippingMethods;
use StoreKeeper\WooCommerce\B2C\Imports\ShippingMethodImport;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;

class SyncWoocommerceShippingMethodsTest extends AbstractTest
{
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-shipping-methods';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule.listShippingMethodsForHooks.success.json';

    public function testRun()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY);

        $this->assertShippingMethodCreation();
    }

    public function testUpdate()
    {
        $this->assertShippingMethodCreation();
        // TODO: Create unit test for update
    }

    public function testDelete()
    {
        // TODO: Delete unit test
    }

    private function assertShippingMethodCreation(): void
    {
        $expectedShippingMethodsPerCountry = [
            'SK_NL' => [
                [
                    'title' => 'Pickup at Store', // Pickup at Store does not have countries set but should use country set on company
                    'cost' => '0',
                    'type' => \WC_Shipping_Local_Pickup::class,
                ],
                [
                    'title' => 'Junmar Express', // Junmar Express does not have countries set but should use country set on company
                    'cost' => '5',
                    'type' => \WC_Shipping_Flat_Rate::class,
                ],
                [
                    'title' => 'JNT',
                    'min_amount' => '10',
                    'requires' => ShippingMethodImport::FREE_SHIPPING_REQUIRES,
                    'type' => \WC_Shipping_Free_Shipping::class,
                ],
                [
                    'title' => 'NinjaVan',
                    'cost' => '10',
                    'type' => \WC_Shipping_Flat_Rate::class,
                ],
            ],
            'SK_DE' => [
                [
                    'title' => 'JNT',
                    'min_amount' => '10',
                    'requires' => ShippingMethodImport::FREE_SHIPPING_REQUIRES,
                    'type' => \WC_Shipping_Free_Shipping::class,
                ],
            ],
            'SK_PH' => [
                [
                    'title' => 'NinjaVan',
                    'cost' => '10',
                    'type' => \WC_Shipping_Flat_Rate::class,
                ],
            ],
        ];

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
                $this->assertCount(1, $woocommerceShippingZone->get_zone_locations(), 'Only one country should be set for shipping zone');
                $zoneName = $woocommerceShippingZone->get_zone_name();
                $actualCountries[] = $zoneName;
                $shippingMethods = $woocommerceShippingZone->get_shipping_methods();
                $actualShippingMethodsPerCountry[$zoneName] = [];
                foreach ($shippingMethods as $shippingMethod) {
                    $actualShippingMethodData = [
                        'title' => $shippingMethod->title,
                    ];

                    if ($shippingMethod instanceof \WC_Shipping_Free_Shipping) {
                        $actualShippingMethodData['min_amount'] = $shippingMethod->min_amount;
                        $actualShippingMethodData['requires'] = $shippingMethod->requires;
                    } else {
                        $actualShippingMethodData['cost'] = $shippingMethod->cost;
                    }

                    $actualShippingMethodData['type'] = get_class($shippingMethod);

                    $actualShippingMethodsPerCountry[$zoneName][] = $actualShippingMethodData;
                }
            }
        }

        $this->assertEquals(array_keys($expectedShippingMethodsPerCountry), $actualCountries, 'Shipping zones should match expected values');
        $this->assertEquals($expectedShippingMethodsPerCountry, $actualShippingMethodsPerCountry, 'Shipping methods does not match');
    }
}
