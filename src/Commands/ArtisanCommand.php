<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

final class ArtisanCommand extends Command
{
    public function run(): int
    {
        return $this->kernel->runRemoteArtisan($this->positionalArgs());
    }
}
