<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy;

final class Configuration
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function load(string $projectRoot, string $packageRoot): self
    {
        $defaults = [
            'prodfiles_dir' => 'prodfiles',
            'config_dir' => 'deploy',
            'frontend_build' => ['bun', 'run', 'build'],
            'copy_roots' => [
                'app', 'bootstrap', 'config', 'database',
                'public', 'resources', 'routes', 'storage',
            ],
            'copy_files' => ['artisan', 'composer.json', 'composer.lock'],
            'verify_after_build' => [
                'vendor/autoload.php',
                'public/build/manifest.json',
            ],
        ];

        $projectConfig = $projectRoot.'/deploy/config.php';
        if (is_file($projectConfig)) {
            $loaded = require $projectConfig;
            if (is_array($loaded)) {
                $defaults = array_merge($defaults, $loaded);
            }
        }

        return new self($defaults);
    }

    public function prodfilesDir(): string
    {
        return (string) $this->config['prodfiles_dir'];
    }

    public function configDir(): string
    {
        return (string) $this->config['config_dir'];
    }

    /** @return list<string> */
    public function frontendBuild(): array
    {
        /** @var list<string> $cmd */
        $cmd = $this->config['frontend_build'];

        return $cmd;
    }

    /** @return list<string> */
    public function copyRoots(): array
    {
        /** @var list<string> $roots */
        $roots = $this->config['copy_roots'];

        return $roots;
    }

    /** @return list<string> */
    public function copyFiles(): array
    {
        /** @var list<string> $files */
        $files = $this->config['copy_files'];

        return $files;
    }

    /** @return list<string> */
    public function verifyAfterBuild(): array
    {
        /** @var list<string> $paths */
        $paths = $this->config['verify_after_build'];

        return $paths;
    }
}
