<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class TerminalCommand extends Command
{
    public function run(): int
    {
        $env = $this->kernel->loadDeployEnv();
        $remotePath = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');

        Output::info("Remote terminal — {$remotePath}");
        Output::info('Commands run in PROD_REMOTE_PATH only. Type exit or quit to leave.');

        $ssh = $this->kernel->connectSsh($env);
        $ssh->setTimeout(0);

        while (true) {
            echo 'remote> ';
            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }

            $line = trim($line);
            if ($line === '' || in_array(strtolower($line), ['exit', 'quit'], true)) {
                break;
            }

            $this->kernel->runRemoteTerminalCommand($ssh, $remotePath, $line);
        }

        Output::info('Disconnected.');

        return 0;
    }
}
