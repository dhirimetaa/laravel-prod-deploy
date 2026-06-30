<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

final class MigrateCommand extends Command
{
    public function run(): int
    {
        $args = ['migrate', '--force', ...$this->positionalArgs()];

        return $this->kernel->runRemoteArtisan($args);
    }
}
