<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Commands;

use Rashydhc\ProdDeploy\Support\Output;

final class RemoteCommand extends Command
{
    public function run(): int
    {
        $args = $this->positionalArgs();

        if ($args === []) {
            Output::fail('Usage: prod-deploy remote <artisan-command-and-args>');
        }

        $env = $this->kernel->loadDeployEnv();
        $remotePath = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $artisanCommand = 'php artisan '.implode(' ', array_map('escapeshellarg', $args));

        Output::info('Running remote: php artisan '.implode(' ', $args));

        $ssh = $this->kernel->connectSsh($env);
        $exitCode = $this->kernel->runRemoteShell($ssh, $remotePath, $artisanCommand);

        if ($exitCode !== 0) {
            Output::fail("Remote command failed with exit code {$exitCode}.");
        }

        return 0;
    }
}
