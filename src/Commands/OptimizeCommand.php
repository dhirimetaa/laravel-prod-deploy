<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class OptimizeCommand extends Command
{
    /** @var list<string> */
    private const COMMANDS = [
        'config:cache',
        'route:cache',
        'view:cache',
    ];

    public function run(): int
    {
        Output::info('Running production optimization on remote server...');

        foreach (self::COMMANDS as $command) {
            $this->kernel->runRemoteArtisan(explode(' ', $command));
        }

        Output::info('Production optimization complete.');

        return 0;
    }
}
