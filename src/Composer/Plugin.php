<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\PluginInterface;

final class Plugin implements EventSubscriberInterface, PluginInterface
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPackageChange',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPackageChange',
        ];
    }

    public function onPackageChange(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        $package = method_exists($operation, 'getTargetPackage')
            ? $operation->getTargetPackage()
            : $operation->getPackage();

        if ($package->getName() !== ScriptRegistrar::PACKAGE_NAME) {
            return;
        }

        $root = $this->composer->getPackage();

        if ($root->getName() === ScriptRegistrar::PACKAGE_NAME) {
            return;
        }

        if ($root->getType() !== 'project') {
            return;
        }

        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $jsonPath = dirname((string) $vendorDir).'/composer.json';

        if (! is_file($jsonPath)) {
            return;
        }

        $jsonFile = new JsonFile($jsonPath);
        /** @var array<string, mixed> $data */
        $data = $jsonFile->read();
        $result = ScriptRegistrar::merge($data);

        if (! $result['merged']) {
            $this->io->write('<comment>laravel-prod-deploy:</comment> deploy:* composer scripts already present — skipped.');

            return;
        }

        $jsonFile->write($result['data']);

        $this->io->write('<info>laravel-prod-deploy:</info> Registered composer scripts: '.implode(', ', $result['added']));
        $this->io->write('  Examples: composer deploy:build, composer deploy:push, composer deploy:migrate');
    }
}
