<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Console;

use Dhirimetaa\ProdDeploy\Application;
use Dhirimetaa\ProdDeploy\Commands\ArtisanCommand;
use Dhirimetaa\ProdDeploy\Commands\BuildCommand;
use Dhirimetaa\ProdDeploy\Commands\InitCommand;
use Dhirimetaa\ProdDeploy\Commands\MigrateCommand;
use Dhirimetaa\ProdDeploy\Commands\OptimizeCommand;
use Dhirimetaa\ProdDeploy\Commands\PushCommand;
use Dhirimetaa\ProdDeploy\Commands\PushTargetCommand;
use Dhirimetaa\ProdDeploy\Commands\RemoteCommand;
use Dhirimetaa\ProdDeploy\Commands\VendorPushCommand;
use Dhirimetaa\ProdDeploy\Commands\VendorZipCommand;
use Dhirimetaa\ProdDeploy\Commands\VendorZipPushCommand;

final class Cli
{
    /** @var array<string, class-string> */
    private const COMMANDS = [
        'build' => BuildCommand::class,
        'push' => PushCommand::class,
        'push:target' => PushTargetCommand::class,
        'vendor:push' => VendorPushCommand::class,
        'vendor:zip' => VendorZipCommand::class,
        'vendor:zip-push' => VendorZipPushCommand::class,
        'artisan' => ArtisanCommand::class,
        'migrate' => MigrateCommand::class,
        'optimize' => OptimizeCommand::class,
        'remote' => RemoteCommand::class,
        'init' => InitCommand::class,
    ];

    public static function run(array $argv): int
    {
        $args = array_slice($argv, 1);

        if ($args === [] || in_array($args[0], ['-h', '--help', 'help'], true)) {
            self::printHelp();

            return 0;
        }

        $commandName = $args[0];
        $commandArgs = array_slice($args, 1);

        if ($commandName === '--version' || $commandName === '-V') {
            echo 'laravel-prod-deploy '.self::version().PHP_EOL;

            return 0;
        }

        if (! isset(self::COMMANDS[$commandName])) {
            fwrite(STDERR, "Unknown command: {$commandName}\n\n");
            self::printHelp();

            return 1;
        }

        $app = Application::boot();
        $class = self::COMMANDS[$commandName];
        $command = new $class($app, $commandArgs);

        return $command->run();
    }

    private static function version(): string
    {
        $composer = dirname(__DIR__, 2).'/composer.json';
        if (is_file($composer)) {
            $data = json_decode((string) file_get_contents($composer), true);
            if (is_array($data) && isset($data['version'])) {
                return (string) $data['version'];
            }
        }

        return 'dev';
    }

    private static function printHelp(): void
    {
        echo <<<'HELP'
laravel-prod-deploy — build prodfiles and deploy Laravel apps via SFTP

Usage:
  prod-deploy <command> [options] [-- args...]

Commands:
  build              Build frontend + prodfiles/ staging directory
  push               Incremental SFTP push (app files, not vendor)
  push:target        Push specific paths (hotfix)
  vendor:push        Incremental vendor file push
  vendor:zip         Create deploy/vendor.zip locally
  vendor:zip-push    Upload vendor as single zip
  artisan            Run artisan on production via SSH
  migrate            Remote migrate --force (shortcut)
  optimize           Remote config:cache, route:cache, view:cache
  remote             Alias for artisan (deprecated)
  init               Scaffold deploy/ in the consuming project

Options (per command):
  build              [--force-composer]
  push               [--dry-run] [--full]
  push:target        [--dry-run] [--allow-vendor] -- <paths...>
  vendor:push        [--dry-run] [--full]
  vendor:zip-push    [--dry-run] [--full]
  init               [--force]

Examples:
  prod-deploy artisan migrate --force
  prod-deploy migrate
  prod-deploy optimize
  prod-deploy artisan queue:restart

Run from your Laravel project root (where composer.json type=project lives).

HELP;
    }
}
