<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class PushTargetCommand extends Command
{
    public function run(): int
    {
        $dryRun = $this->hasFlag('--dry-run');
        $allowVendor = $this->hasFlag('--allow-vendor');
        $targets = $this->positionalArgs();

        if ($targets === []) {
            Output::fail(
                "Usage: prod-deploy push:target [--dry-run] [--allow-vendor] -- <path> [path2 ...]\n".
                "  Example: prod-deploy push:target -- app/Livewire/Foo.php"
            );
        }

        $root = $this->app->projectRoot();
        $prodfiles = $this->app->prodfilesDir();
        $buildExcludes = $this->kernel->loadExcludePatterns('exclude-build.txt');
        $pushExcludes = $this->kernel->loadExcludePatterns('exclude-push.txt');

        $this->kernel->ensureDirectory($prodfiles);

        /** @var array<string, string> $toUpload */
        $toUpload = [];

        foreach ($targets as $target) {
            $relative = $this->kernel->normalizePath(ltrim($target, './\\'));

            if ($relative === '' || str_contains($relative, '..')) {
                Output::fail("Invalid path: {$target}");
            }

            if ($this->kernel->isVendorPath($relative) && ! $allowVendor) {
                Output::fail("Vendor paths require --allow-vendor. Use: prod-deploy vendor:push -- {$relative}");
            }

            $source = $root.'/'.$relative;
            $staging = $prodfiles.'/'.$relative;

            if (! is_file($source) && ! is_dir($source) && ! is_file($staging) && ! is_dir($staging)) {
                Output::fail("Not found in project or prodfiles/: {$relative}");
            }

            if ($this->kernel->pathMatchesExclude($relative, $buildExcludes) && ! is_dir($staging) && ! is_file($staging)) {
                Output::fail("Path is excluded from deploy: {$relative}");
            }

            $fileMap = $this->kernel->resolveTargetFileMap($relative, $buildExcludes, true);

            if ($fileMap === []) {
                Output::info("No deployable files under: {$relative}");
                continue;
            }

            foreach ($fileMap as $fullRelative => $absoluteSource) {
                if ($this->kernel->isVendorPath($fullRelative) && ! $allowVendor) {
                    continue;
                }

                $dest = $prodfiles.'/'.$fullRelative;
                if (realpath($absoluteSource) !== realpath($dest)) {
                    $this->kernel->copyFile($absoluteSource, $dest);
                }
                $hash = md5_file($dest);
                if ($hash === false) {
                    Output::fail("Could not hash: {$fullRelative}");
                }
                $toUpload[$fullRelative] = $hash;
            }
        }

        if ($toUpload === []) {
            Output::fail('No files to upload for the given path(s).');
        }

        ksort($toUpload);
        Output::info(count($toUpload).' file(s) to upload from '.count($targets).' target(s).');

        if ($dryRun) {
            $this->kernel->dryRunList($toUpload);

            return 0;
        }

        $env = $this->kernel->loadDeployEnv();
        $remoteBase = rtrim(str_replace('\\', '/', $env['PROD_REMOTE_PATH']), '/');
        $sftp = $this->kernel->connectSftp($env);
        $uploaded = $this->kernel->uploadFiles($sftp, $prodfiles, $remoteBase, $toUpload);

        $manifest = $this->kernel->loadPushManifest(false);
        $localFiles = $this->kernel->collectFiles($prodfiles, $pushExcludes);

        $this->kernel->savePushManifest(
            $manifest['files'],
            $toUpload,
            $localFiles,
            $remoteBase,
            'target push: '.implode(', ', $targets)
        );

        Output::info("Target push complete. {$uploaded} item(s) uploaded.");

        return 0;
    }
}
