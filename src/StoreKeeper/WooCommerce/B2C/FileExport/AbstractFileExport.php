<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;

abstract class AbstractFileExport implements IFileExport
{
    static function getExportDir(){
        return WP_CONTENT_DIR.'/uploads/storekeeper-exports';
    }

    /**
     * @var string
     */
    protected $filePath;

    /**
     * Sets the export path.
     */
    private function setFilePath()
    {
        $export_dir = self::getExportDir();
        if (!file_exists($export_dir)) {
            if (!mkdir($export_dir, 0777, true)) {
                throw new Exception('Failed to create export dir @ '.$export_dir);
            }
        }

        $filename = $this->getType().'-'.time().'.'.$this->getFileType();
        $this->filePath = $export_dir.'/'.$filename;
    }

    protected function getFileType()
    {
        return 'zip';
    }

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * AbstractFileExport constructor.
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->setFilePath();
    }

    /**
     * Maps an simple array where the $getKeyCallable returns the key,
     * and the value of the array is the value of the map.
     */
    protected function keyValueMapArray(array $array, callable $getKeyCallable): array
    {
        $map = [];

        foreach ($array as $item) {
            $key = $getKeyCallable($item);
            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * Returns a relative file path for url usage.
     */
    public function getDownloadUrl(): string
    {
        $filename = basename($this->filePath);
        $wpContentPath = WP_CONTENT_DIR;
        $relativePath = substr(dirname($this->filePath), strlen($wpContentPath));

        return content_url("$relativePath/$filename");
    }

    protected function reportUpdate(int $total, int $index, string $description)
    {
        $current = $index + 1;
        $percentage = $current / $total * 100;
        $nicePercentage = number_format($percentage, 2, '.', '');
        $this->logger->info("($nicePercentage%) $description");
    }
}
