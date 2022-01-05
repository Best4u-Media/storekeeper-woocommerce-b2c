<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class FileExportCategory extends AbstractFileExportCommand
{
    public static function getShortDescription(): string
    {
        return __('Export CSV file for product categories.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for product categories which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'lang',
                'description' => __('The language to which the entities will be exported.', I18N::DOMAIN),
                'optional' => true,
                'default' => Language::getSiteLanguageIso2(),
                'options' => self::LANGUAGE_OPTIONS,
            ],
        ];
    }

    public function getNewFileExportInstance(): IFileExport
    {
        return new CategoryFileExport(new WpCLILogger());
    }
}
