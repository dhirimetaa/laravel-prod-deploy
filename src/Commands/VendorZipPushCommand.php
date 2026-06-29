<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Commands;

use phpseclib3\Net\SFTP;
use Rashydhc\ProdDeploy\Support\Output;

final class VendorZipPushCommand extends Command
{
    public function run(): int
    {
        $dryRun = $this->hasFlag('--dry-run');
        $full = $this->hasFlag('--full');
        $prodfiles = $this->app->prodfilesDir();
        $excludePatterns = $this->kernel->loadExcludePatterns('exclude-push.txt');
        $remoteBase = $this->kernel->resolveRemoteBase($dryRun);

        if (! is_dir($prodfiles.'/vendor')) {
            Output::fail('prodfiles/vendor/ not found. Run prod-deploy build first.');
        }

        $manifest = $this->kernel->loadPushManifest($full);
        $previousManifest = $manifest['files'];
        $vendorPending = $this->kernel->diffChangedFiles($prodfiles, $excludePatterns, $previousManifest, $full, 'vendor');
        $vendorFileCount = $this->kernel->countVendorFiles($prodfiles, $excludePatterns);

        if ($dryRun) {
            if ($vendorPending === [] && ! $full) {
                Output::info('Vendor zip would still upload (replaces entire vendor/ tree on server).');
            } else {
                Output::info(count($vendorPending).' vendor file(s) changed since last push.');
            }
            Output::info('Would upload: vendor.zip ('.$vendorFileCount.' files inside)');
            echo '  server: '.$this->kernel->vendorExtractInstructions($remoteBase).PHP_EOL;

            return 0;
        }

        Output::info('Building deploy/vendor.zip ('.$vendorFileCount.' files)...');
        $zipPath = $this->kernel->createVendorZip($prodfiles);
        Output::info('vendor.zip ready ('.$this->kernel->formatBytes(filesize($zipPath) ?: 0).').');

        $env = $this->kernel->loadDeployEnv();
        $remoteBase = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $sftp = $this->kernel->connectSftp($env);

        Output::info('Uploading vendor.zip (1/1)...');
        if (! $sftp->put($remoteBase.'/vendor.zip', $zipPath, SFTP::SOURCE_LOCAL_FILE)) {
            Output::fail('Upload failed: vendor.zip');
        }

        Output::info('Uploaded 1/1 item(s) (0 remaining)...');

        $vendorHashes = $this->kernel->diffChangedFiles($prodfiles, $excludePatterns, [], true, 'vendor');
        $localFiles = $this->kernel->collectFiles($prodfiles, $excludePatterns);

        $this->kernel->savePushManifest($previousManifest, $vendorHashes, $localFiles, $remoteBase, 'vendor zip push');
        Output::info('Vendor zip push complete. Extract on the server before using the app:');
        Output::info('  '.$this->kernel->vendorExtractInstructions($remoteBase));

        return 0;
    }
}
