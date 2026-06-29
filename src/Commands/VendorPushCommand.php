<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class VendorPushCommand extends Command
{
    public function run(): int
    {
        $dryRun = $this->hasFlag('--dry-run');
        $full = $this->hasFlag('--full');
        $prodfiles = $this->app->prodfilesDir();
        $excludePatterns = $this->kernel->loadExcludePatterns('exclude-push.txt');

        if (! is_dir($prodfiles.'/vendor')) {
            Output::fail('prodfiles/vendor/ not found. Run prod-deploy build first.');
        }

        $manifest = $this->kernel->loadPushManifest($full);
        $previousManifest = $manifest['files'];
        $toUpload = $this->kernel->diffChangedFiles($prodfiles, $excludePatterns, $previousManifest, $full, 'vendor');

        if ($toUpload === []) {
            Output::info('Nothing to upload — vendor/ matches last successful push.');

            return 0;
        }

        Output::info(count($toUpload).' vendor file(s) to upload.');

        if ($dryRun) {
            $this->kernel->dryRunList($toUpload);

            return 0;
        }

        $env = $this->kernel->loadDeployEnv();
        $remoteBase = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $sftp = $this->kernel->connectSftp($env);
        $uploaded = $this->kernel->uploadFiles($sftp, $prodfiles, $remoteBase, $toUpload);
        $localFiles = $this->kernel->collectFiles($prodfiles, $excludePatterns);

        $this->kernel->savePushManifest($previousManifest, $toUpload, $localFiles, $remoteBase, 'vendor push');
        Output::info("Vendor push complete. {$uploaded} item(s) uploaded.");

        return 0;
    }
}
