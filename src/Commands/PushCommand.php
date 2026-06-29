<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Commands;

use Rashydhc\ProdDeploy\Support\Output;

final class PushCommand extends Command
{
    public function run(): int
    {
        $dryRun = $this->hasFlag('--dry-run');
        $full = $this->hasFlag('--full');
        $prodfiles = $this->app->prodfilesDir();
        $excludePatterns = $this->kernel->loadExcludePatterns('exclude-push.txt');

        if (! is_dir($prodfiles)) {
            Output::fail('prodfiles/ not found. Run prod-deploy build first.');
        }

        $manifest = $this->kernel->loadPushManifest($full);
        $previousManifest = $manifest['files'];
        $toUpload = $this->kernel->diffChangedFiles($prodfiles, $excludePatterns, $previousManifest, $full, 'app');
        $vendorPending = $this->kernel->diffChangedFiles($prodfiles, $excludePatterns, $previousManifest, $full, 'vendor');

        if ($toUpload === []) {
            Output::info('Nothing to upload — app files match last successful push.');
        } else {
            Output::info(count($toUpload).' app file(s) to upload.');
        }

        if ($vendorPending !== []) {
            Output::info('Vendor folder has '.count($vendorPending).' changed file(s) — not included in push.');
            Output::info('Run: prod-deploy vendor:push   (changed files only)');
            Output::info(' Or: prod-deploy vendor:zip-push   (single zip — first deploy / bulk update)');
        }

        if ($toUpload === []) {
            return 0;
        }

        if ($dryRun) {
            $this->kernel->dryRunList($toUpload);

            return 0;
        }

        $env = $this->kernel->loadDeployEnv();
        $remoteBase = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $sftp = $this->kernel->connectSftp($env);
        $uploaded = $this->kernel->uploadFiles($sftp, $prodfiles, $remoteBase, $toUpload);
        $localFiles = $this->kernel->collectFiles($prodfiles, $excludePatterns);

        $this->kernel->savePushManifest($previousManifest, $toUpload, $localFiles, $remoteBase, 'app push');
        Output::info("Push complete. {$uploaded} app item(s) uploaded.");

        return 0;
    }
}
