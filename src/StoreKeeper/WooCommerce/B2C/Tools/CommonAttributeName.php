<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class CommonAttributeName
{
    const TYPE_CUSTOM_ATTRIBUTE = 'custom';
    const TYPE_SYSTEM_ATTRIBUTE = 'system';

    const SYSTEM_ATTR_PREFIX = 'sa_';
    const CUSTOM_ATTRIBUTE_PREFIX = 'ca_';
    const ATTRIBUTE_TERM_PREFIX = 'pa_';

    private static function getPrefix(string $type): string
    {
        if (self::TYPE_SYSTEM_ATTRIBUTE === $type) {
            return self::SYSTEM_ATTR_PREFIX;
        }

        return self::CUSTOM_ATTRIBUTE_PREFIX;
    }

    public static function cleanAttributeTermPrefix(string $name): string
    {
        if (0 === strpos($name, self::ATTRIBUTE_TERM_PREFIX)) {
            return substr($name, strlen(self::ATTRIBUTE_TERM_PREFIX));
        }

        return $name;
    }

    public static function getName(string $attributeName, string $type): string
    {
        $name = self::cleanAttributeTermPrefix($attributeName);
        $prefix = self::getPrefix($type);

        return sanitize_title($prefix.$name);
    }
    public static function getSystemName(string $attributeName): string
    {
        return self::getName($attributeName, self::TYPE_SYSTEM_ATTRIBUTE);
    }
    public static function getCustomName(string $attributeName): string
    {
        return self::getName($attributeName, self::TYPE_CUSTOM_ATTRIBUTE);
    }
}
