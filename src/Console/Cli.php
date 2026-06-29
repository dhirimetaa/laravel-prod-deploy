<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Console;

use Rashydhc\ProdDeploy\Application;
use Rashydhc\ProdDeploy\Commands\BuildCommand;
use Rashydhc\ProdDeploy\Commands\InitCommand;
use Rashydhc\ProdDeploy\Commands\PushCommand;
use Rashydhc\ProdDeploy\Commands\PushTargetCommand;
use Rashydhc\ProdDeploy\Commands\RemoteCommand;
use Rashydhc\ProdDeploy\Commands\VendorPushCommand;
use Rashydhc\ProdDeploy\Commands\VendorZipCommand;
use Rashydhc\ProdDeploy\Commands\VendorZipPushCommand;

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
            echo "laravel-prod-deploy 1.0.0\n";

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
  remote             Run artisan command on production via SSH
  init               Scaffold deploy/ in the consuming project

Options (per command):
  build              [--force-composer]
  push               [--dry-run] [--full]
  push:target        [--dry-run] [--allow-vendor] -- <paths...>
  vendor:push        [--dry-run] [--full]
  vendor:zip-push    [--dry-run] [--full]
  init               [--force]

Run from your Laravel project root (where composer.json type=project lives).

HELP;
    }
}
