<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Commands;

use Rashydhc\ProdDeploy\Support\Output;

final class BuildCommand extends Command
{
    public function run(): int
    {
        $config = $this->app->configuration();
        $root = $this->app->projectRoot();
        $prodfiles = $this->app->prodfilesDir();
        $excludePatterns = $this->kernel->loadExcludePatterns('exclude-build.txt');
        $forceComposer = $this->hasFlag('--force-composer');

        Output::info('Building frontend assets ('.implode(' ', $config->frontendBuild()).')...');
        $this->kernel->runProcess($config->frontendBuild(), $root);

        $reuseVendor = ! $forceComposer && $this->kernel->vendorIsFresh($prodfiles);

        Output::info('Preparing prodfiles/...');
        if ($reuseVendor) {
            Output::info('Composer lock unchanged — keeping existing prodfiles/vendor/.');
            $this->kernel->resetDirectoryExcept($prodfiles, ['vendor']);
        } else {
            if (is_dir($prodfiles)) {
                $this->kernel->removeDirectory($prodfiles);
                if (is_dir($prodfiles)) {
                    $stale = $prodfiles.'_old_'.time();
                    if (@rename($prodfiles, $stale)) {
                        Output::info('Could not delete prodfiles/ — moved to '.basename($stale));
                        $this->kernel->removeDirectory($stale);
                    }
                }
            }
            $this->kernel->ensureDirectory($prodfiles);
        }

        Output::info('Copying production files...');
        $copied = 0;

        foreach ($config->copyRoots() as $dir) {
            $sourceDir = $root.'/'.$dir;
            if (! is_dir($sourceDir)) {
                continue;
            }
            foreach ($this->kernel->collectFiles($sourceDir, $excludePatterns, $dir) as $relative) {
                $this->kernel->copyFile($sourceDir.'/'.$relative, $prodfiles.'/'.$dir.'/'.$relative);
                $copied++;
            }
        }

        foreach ($config->copyFiles() as $file) {
            $source = $root.'/'.$file;
            if (! is_file($source)) {
                Output::fail("Required file missing: {$file}");
            }
            if ($this->kernel->pathMatchesExclude($file, $excludePatterns)) {
                continue;
            }
            $this->kernel->copyFile($source, $prodfiles.'/'.$file);
            $copied++;
        }

        Output::info("Copied {$copied} files into prodfiles/.");

        $hotFile = $prodfiles.'/public/hot';
        if (is_file($hotFile)) {
            unlink($hotFile);
            Output::info('Removed public/hot (Vite dev marker — not for production).');
        }

        Output::info('Ensuring storage & cache directories...');
        $this->kernel->ensureStorageSkeleton($prodfiles);

        if ($reuseVendor && is_file($prodfiles.'/vendor/autoload.php')) {
            Output::info('Skipping composer install (vendor up to date).');
        } else {
            Output::info('Installing production Composer dependencies...');
            $this->kernel->runProcess(
                $this->kernel->composerCommand([
                    'install', '--no-dev', '--optimize-autoloader',
                    '--no-interaction', '--no-scripts',
                    '--working-dir='.$config->prodfilesDir(),
                ]),
                $root
            );
            $this->kernel->saveBuildManifest();
        }

        foreach ($config->verifyAfterBuild() as $relative) {
            $path = $prodfiles.'/'.$relative;
            if (! is_file($path)) {
                Output::fail('Build verification failed — missing: '.$relative);
            }
        }

        Output::info('Build complete. Output: '.$config->prodfilesDir().'/');
        Output::info('Next: prod-deploy vendor:zip-push  then  prod-deploy push');

        return 0;
    }
}
