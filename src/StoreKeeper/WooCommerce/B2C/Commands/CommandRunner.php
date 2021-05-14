<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Monolog\Logger;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use Symfony\Component\Process\Process;

class CommandRunner
{
    use LoggerAwareTrait;

    const BASE_CLASS = AbstractCommand::class;
    /**
     * @var string[]
     */
    private $commands = [];

    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    private static function getWpRootDir(): string
    {
        return STOREKEEPER_WOOCOMMERCE_B2C_ABSPATH.'/../../../';
    }

    public function setConsoleLogger(): Logger
    {
        $logger = LoggerFactory::createConsole('ConsoleRunner');
        $this->setLogger($logger);

        return $logger;
    }

    public static function getSubProcessInputString(string $name, array $arguments = [], array $assoc_arguments = []): string
    {
        $input = json_encode(
            [
                'name' => $name,
                'arguments' => $arguments,
                'assoc_arguments' => $assoc_arguments,
            ]
        );

        return $input;
    }

    /**
     * @throws BaseException
     */
    public function addCommandClass(string $class)
    {
        if (!is_a($class, self::BASE_CLASS, true)) {
            throw new BaseException("$class is not instance of ".self::BASE_CLASS);
        }
        $this->commands[call_user_func("$class::getCommandName")] = $class;
    }

    /**
     * @return string[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * @param $name
     *
     * @throws \Exception
     */
    public function getCommandClass($name): string
    {
        if (!array_key_exists($name, $this->commands)) {
            throw new \Exception("$name command not found");
        }

        return $this->commands[$name];
    }

    /**
     * @param $name
     *
     * @throws \Exception
     */
    public function execute($name, array $arguments = [], array $assoc_arguments = []): int
    {
        $class = $this->getCommandClass($name);
        /* @var $command AbstractCommand */
        $command = new $class();
        $command->setRunner($this);
        $command->setLogger($this->logger);

        $this->logVmStats(
            [
                'step' => 'before_execute',
                'command_name' => $name,
            ]
        );
        $time_start = microtime(true);
        try {
            $result = $command->execute($arguments, $assoc_arguments);
        } finally {
            $time = round((microtime(true) - $time_start) * 1000);
            $this->logVmStats(
                [
                    'step' => 'after_execute',
                    'command_name' => $name,
                    'time_ms' => $time,
                ]
            );
        }

        return (int) $result;
    }

    protected function logVmStats(array $context = [])
    {
        $this->logger->debug(
            'Vm Stats',
            [
                'memory_usage_mb' => sprintf('%.2f MB', (memory_get_usage() / 1024 / 1024)),
                'memory_peak_usage_mb' => sprintf('%.2f MB', (memory_get_peak_usage() / 1024 / 1024)),
                'pid' => getmypid(),
            ] + $context
        );
    }

    /**
     * @param $name
     *
     * @throws SubProcessException
     */
    public function executeAsSubProcess(
        string $name,
        array $arguments = [],
        array $assoc_arguments = [],
        int $timeout = 0
    ): int {
        $php_script = STOREKEEPER_WOOCOMMERCE_B2C_ABSPATH.'/scripts/run-command-from-input.php';
        $input = self::getSubProcessInputString($name, $arguments, $assoc_arguments);
        $process = $this->executePhp(
            [
                $php_script,
            ],
            $input,
            $timeout
        );

        return $process->getExitCode();
    }

    /**
     * @throws SubProcessException
     */
    protected function executePhp(array $params, string $input = null, int $timeout = 0): Process
    {
        $phpBinary = PHP_BINARY === '' ? 'php' : PHP_BINARY;
        $command = [$phpBinary];
        list($xdebug_on, $command) = $this->setXdebugCmsArgs($command);
        $command = array_merge($command, $params);
        $cwd = getcwd() ?? null;
        $env = $this->getSubProcessEnv($xdebug_on);
        $process = new Process($command, $cwd, $env, null, $timeout);

        $cmd_string = implode(' ', $command);
        $context = [
            'command' => $cmd_string,
        ];
        $this->logger->debug('executePhp', $context);
        if (!empty($input)) {
            $process->setInput($input);
            $context['input'] = $input;
        }
        $process->start();
        $context['pid'] = $process->getPid();

        $this->logger->debug(
            'Running php script',
            [
                'env' => $env,
                'cwd' => $cwd,
            ] + $context
        );
        $process->wait(
            function ($type, $buffer) use ($context) {
                if (Process::ERR === $type) {
                    $this->logger->error($buffer, $context);
                } else {
                    $this->logger->debug($buffer, $context);
                }
            }
        );
        if (!$process->isSuccessful()) {
            throw new SubProcessException($process, $cmd_string);
        }

        return $process;
    }

    private function setXdebugCmsArgs(array $cmd): array
    {
        $xdebug_on = false;
        if (extension_loaded('xdebug')) {
            $xdebug = ini_get_all('xdebug');
            $xdebug_on = !empty($xdebug) && !empty($xdebug['xdebug.remote_enable']);
            if ($xdebug_on) {
                foreach ($xdebug as $k => $v) {
                    if (0 === strpos($k, 'xdebug.remote') && '' !== $v['local_value']) {
                        $cmd[] = '-d'.$k.'='.$v['local_value'];
                    }
                }
            }
        }

        return [$xdebug_on, $cmd];
    }

    /**
     * @param $xdebug_on
     */
    private function setXdebugEnv($xdebug_on, array $env): array
    {
        if ($xdebug_on) {
            $copy_keys = ['PHP_IDE_CONFIG', 'JETBRAINS_REMOTE_RUN', 'XDEBUG_CONFIG'];
            $env = $this->copyEnv($env, $copy_keys);
        }

        return $env;
    }

    /**
     * @param null $contents uses php://stdin for getting which command to execute if empty
     *
     * @throws \Exception
     */
    public function executeFromInputJson($contents = null): int
    {
        if (empty($contents)) {
            $contents = file_get_contents('php://stdin');
            if (empty($contents)) {
                throw new \Exception('No input');
            }
        }
        $json = json_decode($contents, true);
        if (empty($json)) {
            throw new \Exception("Failed to decode json from contents: $contents. Error: ".json_last_error_msg());
        }
        if (empty($json['name'])) {
            throw new \Exception("Name is not set in: $contents");
        }
        $name = $json['name'];

        if ($this->needsFullWordpress($name)) {
            self::loadFullWordpress();
        } else {
            self::loadWpConfig();
        }

        return $this->execute(
            $name,
            $json['arguments'] ?? [],
            $json['assoc_arguments'] ?? []
        );
    }

    public static function loadWpConfig(): void
    {
        define('ABSPATH', self::getWpRootDir());
        // Getting the content of the wp-config file
        $wp_config = file(ABSPATH.'wp-config.php');
        // Getting the important lines of the wp-config file
        $out = ['<?php', ''];
        foreach ($wp_config as $line) {
            if (
                1 === preg_match('/^\s*(define)/', $line) ||
                1 === preg_match('/^\s*(\$table_prefix)/', $line)
            ) {
                $out[] = trim($line);
            }
        }

        // Creating a temp wp-config file and include it
        $wp_config_file = tempnam(sys_get_temp_dir(), 'wp-config').'.php';
        try {
            file_put_contents($wp_config_file, implode(PHP_EOL, $out));
            // need to define globals here to make sure it get's loaded in global scope
            global $table_prefix;
            @require $wp_config_file;
        } finally {
            unlink($wp_config_file);
        }
    }

    public static function loadFullWordpress(): void
    {
        // Load wordpress
        $_SERVER['PHP_SELF'] = '/wp-admin/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($GLOBALS['current_screen']);
        define('WP_ADMIN', true);
        require_once self::getWpRootDir().'/wp-load.php';
    }

    public static function exitIFNotCli(): void
    {
        if ('cli' !== php_sapi_name()) {
            echo "Only can run in cli\n";
            http_response_code(403);
            exit;
        }
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     */
    public function needsFullWordpress(string $name): bool
    {
        $class = $this->getCommandClass($name);

        return call_user_func("$class::needsFullWpToExecute");
    }

    private function copyEnv(array $env, array $copy_keys): array
    {
        foreach ($copy_keys as $copy_key) {
            if (isset($_ENV[$copy_key])) {
                $env[$copy_key] = $_ENV[$copy_key];
            }
        }

        return $env;
    }

    /**
     * @param $xdebug_on
     */
    protected function getSubProcessEnv($xdebug_on): array
    {
        $env = [];
        $env = $this->setXdebugEnv($xdebug_on, $env);
        $env = $this->copyEnv($env, ['STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL']);

        return $env;
    }
}
