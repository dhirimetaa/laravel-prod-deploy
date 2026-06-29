<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Tests;

use PHPUnit\Framework\TestCase;
use Rashydhc\ProdDeploy\Application;
use Rashydhc\ProdDeploy\DeployKernel;

abstract class TestCaseBase extends TestCase
{
    protected string $projectRoot;

    protected Application $app;

    protected DeployKernel $kernel;

    protected function setUp(): void
    {
        Application::reset();
        $this->projectRoot = sys_get_temp_dir().'/prod-deploy-test-'.uniqid('', true);
        mkdir($this->projectRoot, 0777, true);
        mkdir($this->projectRoot.'/deploy', 0777, true);
        file_put_contents(
            $this->projectRoot.'/composer.json',
            json_encode(['type' => 'project', 'name' => 'test/app'], JSON_THROW_ON_ERROR)
        );
        file_put_contents($this->projectRoot.'/composer.lock', json_encode([
            'packages' => [],
            'packages-dev' => [],
        ], JSON_THROW_ON_ERROR));
        $this->app = Application::boot($this->projectRoot);
        $this->kernel = new DeployKernel($this->app);
    }

    protected function tearDown(): void
    {
        Application::reset();
        $this->removeTree($this->projectRoot);
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path.'/'.$entry;
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
