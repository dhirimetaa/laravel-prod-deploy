<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Commands;

use Rashydhc\ProdDeploy\Support\Output;

final class VendorZipCommand extends Command
{
    public function run(): int
    {
        $prodfiles = $this->app->prodfilesDir();
        $root = $this->app->projectRoot();

        if (! is_dir($prodfiles.'/vendor')) {
            Output::fail('prodfiles/vendor/ not found. Run prod-deploy build first.');
        }

        Output::info('Creating vendor.zip from prodfiles/vendor/...');
        $zipPath = $this->kernel->createVendorZip($prodfiles);
        $size = filesize($zipPath) ?: 0;

        Output::info('Created: '.$this->kernel->relativePath($zipPath, $root).' ('.$this->kernel->formatBytes($size).')');
        Output::info('Upload: prod-deploy vendor:zip-push');
        Output::info('Or upload deploy/vendor.zip via FileZilla, then SSH extract on server.');

        return 0;
    }
}
