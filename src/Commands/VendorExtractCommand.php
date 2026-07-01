<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class VendorExtractCommand extends Command
{
    public function run(): int
    {
        $dryRun = $this->hasFlag('--dry-run');
        $cleanupOld = $this->hasFlag('--cleanup-old');

        $this->kernel->extractVendorZipOnServer($cleanupOld, $dryRun);

        return 0;
    }
}
