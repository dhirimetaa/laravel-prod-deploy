<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Commands;

use Dhirimetaa\ProdDeploy\Support\Output;

final class InitCommand extends Command
{
    public function run(): int
    {
        $force = $this->hasFlag('--force');
        $configDir = $this->app->configDir();
        $root = $this->app->projectRoot();

        $this->kernel->ensureDirectory($configDir);

        $copies = [
            'deploy.env.example' => 'deploy.env',
            'config.php.example' => 'config.php',
        ];

        foreach ($copies as $stub => $dest) {
            $target = $configDir.'/'.$dest;
            $source = $this->app->stubPath($stub);
            if (! is_file($source)) {
                Output::fail("Package stub missing: {$stub}");
            }
            if (is_file($target) && ! $force) {
                Output::info("Skipped {$dest} (already exists). Use --force to overwrite.");
            } else {
                copy($source, $target);
                Output::info("Created deploy/{$dest}");
            }
        }

        foreach (['exclude-build.txt', 'exclude-push.txt'] as $file) {
            $target = $configDir.'/'.$file;
            $source = $this->app->packageRoot().'/config/'.$file;
            if (is_file($target) && ! $force) {
                Output::info("Skipped {$file} (already exists).");
            } else {
                copy($source, $target);
                Output::info("Created deploy/{$file}");
            }
        }

        $gitignorePath = $root.'/.gitignore';
        $lines = [
            '',
            '# laravel-prod-deploy',
            '/prodfiles/',
            '/deploy/deploy.env',
            '/deploy/.push-manifest.json',
            '/deploy/.build-manifest.json',
            '/deploy/vendor.zip',
        ];

        if (is_file($gitignorePath)) {
            $content = (string) file_get_contents($gitignorePath);
            if (! str_contains($content, '/prodfiles/')) {
                file_put_contents($gitignorePath, $content.PHP_EOL.implode(PHP_EOL, $lines).PHP_EOL);
                Output::info('Updated .gitignore with prodfiles/ and deploy secrets.');
            }
        }

        Output::info('Init complete. Edit deploy/deploy.env with your SSH details.');
        Output::info('Optional: add composer scripts from stubs/composer-scripts.json');

        return 0;
    }
}
