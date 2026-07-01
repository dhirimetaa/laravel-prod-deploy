<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class TerminalCommand extends Command
{
    public function run(): int
    {
        if (! stream_isatty(STDIN)) {
            Output::fail(
                "Interactive terminal needs a real TTY — composer deploy:terminal often cannot read input on Windows.\n".
                "Run directly instead:\n".
                '  vendor/bin/prod-deploy terminal'
            );
        }

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
                Output::info('Input closed.');

                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (in_array(strtolower($line), ['exit', 'quit'], true)) {
                break;
            }

            $this->kernel->runRemoteTerminalCommand($ssh, $remotePath, $line);
        }

        Output::info('Disconnected.');

        return 0;
    }
}
