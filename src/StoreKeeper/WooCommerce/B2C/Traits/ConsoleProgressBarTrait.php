<?php

namespace StoreKeeper\WooCommerce\B2C\Traits;

trait ConsoleProgressBarTrait
{
    /* @var \cli\progress\Bar|null $progressBar */
    protected $progressBar;

    /**
     * @return \cli\progress\Bar|null
     */
    public function createProgressBar(int $count, string $message)
    {
        $progressBar = null;
        if (class_exists('WP_CLI')) {
            $progressBar = \WP_CLI\Utils\make_progress_bar($message, $count);
        }
        $this->progressBar = $progressBar;

        if (!is_null($this->progressBar)) {
            $this->progressBar->display();
        }

        return $this->progressBar;
    }

    public function tickProgressBar(): void
    {
        if (!is_null($this->progressBar)) {
            $this->progressBar->tick();
        }
    }

    public function endProgressBar(): void
    {
        if (!is_null($this->progressBar)) {
            $this->progressBar->finish();
        }
    }
}
