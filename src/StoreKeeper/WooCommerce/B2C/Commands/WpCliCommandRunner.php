<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Monolog\Logger;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Factories\WpCliHandler;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;
use Symfony\Component\Process\Process;

class WpCliCommandRunner extends CommandRunner
{
    const command_prefix = 'sk';
    const SINGLE_PROCESS = 'single-process';

    public function __construct()
    {
        // WP_ADMIN constant is being defined here as it causes synchronization issue of attribute types due to external plugins
        // @see known plugin: https://themesinfo.com/wordpress-plugins/wordpress-wpa-woocommerce-variation-swatch-plugin-dksd/latest
        // @see issue: https://app.clickup.com/t/1tm4vav
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function load()
    {
        foreach ($this->getCommands() as $name => $command) {
            $this->registerCommand($name);
        }
    }

    /**
     * @throws \Exception
     */
    private function registerCommand($name)
    {
        \WP_CLI::add_command(
            self::command_prefix.' '.$name,
            function (array $arguments, array $assoc_arguments) use ($name) {
                if (isset($assoc_arguments[self::SINGLE_PROCESS])) {
                    $this->shouldSpawnSubProcess = false;
                } else {
                    $this->shouldSpawnSubProcess = $this->isSubProcessesAvailable($name);
                }
                $this->execute($name, $arguments, $assoc_arguments);
            }
        );
    }

    private function isSubProcessesAvailable(string $name): bool
    {
        try {
            $testCommand = 'whoami';
            $testProcess = new Process([$testCommand]);
            $testProcess->run();

            if (!$testProcess->isSuccessful()) {
                throw new SubProcessException($testProcess, $testCommand);
            }

            return true;
        } catch (\Throwable $e) {
            echo $e->getMessage()."\n";
            $debug = "=== Exception debug ====\n";
            $debug .= BaseException::getAsString($e);
            $this->logger->warning(
                'Command will not spawn subprocesses:'.$e->getMessage(),
                [
                    'name' => $name,
                    'exception' => $debug,
                ]);

            return false;
        }
    }

    /**
     * @param $name
     *
     * @throws \WP_CLI\ExitException
     */
    public function execute($name, array $arguments = [], array $assoc_arguments = []): int
    {
        try {
            $this->setConsoleLogger();
            $this->prepareExecution();
            $return = parent::execute($name, $arguments, $assoc_arguments);
            \WP_CLI::success('Command '.$name);
        } catch (\Throwable $e) {
            $this->exitWithException($name, $e);
        }

        return $return;
    }

    public function setConsoleLogger(): Logger
    {
        $log_level = LoggerFactory::getLogLevel();
        $logger = new Logger('wp-cli');
        $logger->pushHandler(
            new WpCliHandler($log_level)
        );
        $this->setLogger($logger);

        return $logger;
    }

    /**
     * this one will KILL the script.
     *
     * @throws \WP_CLI\ExitException
     */
    protected function exitWithException(string $name, \Throwable $e)
    {
        $debug = "=== Exception debug ====\n";
        $debug .= BaseException::getAsString($e);
        $this->logger->emergency(
            'Command failed with:'.$e->getMessage(),
            [
                'name' => $name,
                'exception' => $debug,
            ]
        );
        if (Core::isDebug()) {
            echo $debug;
        }
        $message = $e->getMessage();
        $code = 2;
        if (!($e instanceof BaseException)) {
            $code = 255;
        }
        \WP_CLI::error($message, $code);
    }

    private function prepareExecution()
    {
        // To make sure even big webshops work..
        IniHelper::setIni(
            'memory_limit',
            0,
            [$this->logger, 'warning']
        );

        // To see the logs, instead of waiting until the end.
        for ($i = ob_get_level(); $i > 0; --$i) {
            ob_end_clean();
        }
    }

    protected function printError(\Throwable $e)
    {
        echo BaseException::getAsString($e);
    }
}
