<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy;

final class Application
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $projectRoot,
        private readonly string $packageRoot,
        private readonly Configuration $configuration,
    ) {}

    public static function boot(?string $projectRoot = null): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $packageRoot = dirname(__DIR__);
        $resolvedRoot = $projectRoot ?? self::detectProjectRoot();

        self::$instance = new self(
            $resolvedRoot,
            $packageRoot,
            Configuration::load($resolvedRoot, $packageRoot),
        );

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function packageRoot(): string
    {
        return $this->packageRoot;
    }

    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    public function configDir(): string
    {
        return $this->projectRoot.'/'.$this->configuration->configDir();
    }

    public function prodfilesDir(): string
    {
        return $this->projectRoot.'/'.$this->configuration->prodfilesDir();
    }

    public function pushManifestPath(): string
    {
        return $this->configDir().'/.push-manifest.json';
    }

    public function buildManifestPath(): string
    {
        return $this->configDir().'/.build-manifest.json';
    }

    public function vendorZipPath(): string
    {
        return $this->configDir().'/vendor.zip';
    }

    public function excludeFilePath(string $name): string
    {
        $projectFile = $this->configDir().'/'.$name;
        if (is_file($projectFile)) {
            return $projectFile;
        }

        return $this->packageRoot.'/config/'.$name;
    }

    public function stubPath(string $name): string
    {
        return $this->packageRoot.'/stubs/'.$name;
    }

    public static function detectProjectRoot(): string
    {
        $dir = getcwd() ?: '.';
        $dir = realpath($dir) ?: $dir;

        while ($dir !== false && $dir !== dirname($dir)) {
            $composer = $dir.'/composer.json';
            if (is_file($composer)) {
                $data = json_decode((string) file_get_contents($composer), true);
                if (is_array($data) && ($data['type'] ?? '') === 'project') {
                    return $dir;
                }
            }
            $dir = dirname($dir);
        }

        return getcwd() ?: '.';
    }
}
