<?php

namespace StoreKeeper\WooCommerce\B2C\Tools\Export;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\ProductAttributes;
use WC_Product_Attribute;

class AttributeExport
{
    const TYPE_CUSTOM_ATTRIBUTE = 'type_custom_attribute';
    const TYPE_SYSTEM_ATTRIBUTE = 'type_system_attribute';
    const ATTRIBUTE_TERM_PREFIX = 'pa_';

    public static function getAllAttributes(): array
    {
        return array_merge(
            self::getAttributes(),
            self::getCustomProductAttributes()
        );
    }

    public static function getAllNonFeaturedAttributes(): array
    {
        return array_merge(
            self::getAttributes(true),
            self::getCustomProductAttributes(true)
        );
    }

    public static function getCustomProductAttributes(bool $excludeFeatured = false): array
    {
        $attributeMap = [];
        foreach (ProductAttributes::getCustomProductAttributeOptions() as $attributeName => $attribute) {
            $attributeKey = self::getAttributeKey($attributeName, self::TYPE_CUSTOM_ATTRIBUTE, $isFeatured);
            if (empty($attributeMap[$attributeKey])) {
                if (!$isFeatured || !$excludeFeatured) {
                    $attributeMap[$attributeKey] = [
                        'id' => 0,
                        'name' => $attributeKey,
                        'label' => $attribute['name'],
                        'options' => false,
                    ];
                }
            }
        }

        return array_values($attributeMap);
    }

    protected static function getAttributes(bool $excludeFeatured = false): array
    {
        $attributes = [];
        $items = wc_get_attribute_taxonomies();
        foreach ($items as $item) {
            $attributeKey = self::getAttributeKey($item->attribute_name, self::TYPE_SYSTEM_ATTRIBUTE, $isFeatured);

            if (!$isFeatured || !$excludeFeatured) {
                $attributes[] = [
                    'id' => $item->attribute_id,
                    'name' => $attributeKey,
                    'label' => $item->attribute_label,
                    'options' => true,
                ];
            }
        }

        return $attributes;
    }

    public static function getAttributeOptions()
    {
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $name = wc_attribute_taxonomy_name($attribute->attribute_name);
            $exportKey = AttributeExport::getAttributeKey(
                $attribute->attribute_name,
                AttributeExport::TYPE_SYSTEM_ATTRIBUTE
            );
            $isFeatured = FeaturedAttributeOptions::isFeatured($exportKey);
            if (!$isFeatured || FeaturedAttributeOptions::isOptionsAttribute($exportKey)) {
                $attributeOptions = get_terms($name, ['hide_empty' => false]);
                foreach ($attributeOptions as $attributeOption) {
                    yield [
                        'name' => $attributeOption->slug,
                        'label' => $attributeOption->name,
                        'attribute_name' => $exportKey,
                        'attribute_label' => $attribute->attribute_label,
                    ];
                }
            }
        }
    }

    public static function getProductAttributeKey(WC_Product_Attribute $attribute): string
    {
        $name = self::cleanAttributeTermPrefix($attribute->get_name());
        $type = self::getProductAttributeType($attribute);

        return self::getAttributeKey($name, $type);
    }

    public static function getAttributeKey(string $attributeName, string $type, ?bool &$isFeatured = null): string
    {
        $name = self::cleanAttributeTermPrefix($attributeName);
        $prefix = self::getPrefix($type);

        $attributeKey = sanitize_title($prefix.$name);
        $isFeatured = FeaturedAttributeOptions::isFeatured($attributeKey);
        $attributeKey = FeaturedAttributeOptions::getFeaturedNameIfPossible($attributeKey);

        return $attributeKey;
    }

    private static function getProductAttributeType(WC_Product_Attribute $attribute): string
    {
        return $attribute->get_id() <= 0 ? self::TYPE_CUSTOM_ATTRIBUTE : self::TYPE_SYSTEM_ATTRIBUTE;
    }

    public static function cleanAttributeTermPrefix(string $name): string
    {
        if (0 === strpos($name, self::ATTRIBUTE_TERM_PREFIX)) {
            return substr($name, strlen(self::ATTRIBUTE_TERM_PREFIX));
        }

        return $name;
    }

    /**
     * We add a prefix to the name based on if the attribute:
     * Is a custom attribute for that product (ca-)
     * Is a system attribute for the whole system (no prefix).
     */
    private static function getPrefix(string $type): string
    {
        if (self::TYPE_SYSTEM_ATTRIBUTE === $type) {
            return 'sa_';
        }

        return 'ca_';
    }

    public static function getProductAttributeOptions(WC_Product_Attribute $attribute): array
    {
        if ($attribute->get_id() <= 0) {
            return array_map(
                function ($name) {
                    return [
                        'alias' => sanitize_title($name),
                        'title' => $name,
                    ];
                },
                $attribute->get_options()
            );
        } else {
            return array_map(
                function ($term) {
                    return [
                        'alias' => $term->slug,
                        'title' => $term->name,
                    ];
                },
                get_terms($attribute->get_name(), ['hide_empty' => false])
            );
        }
    }

    public static function getProductAttributeLabel(WC_Product_Attribute $attribute): string
    {
        $label = $attribute->get_name();

        if ($attribute->get_id() > 0) {
            $label = wc_attribute_label($label);
        }

        return $label;
    }
}
