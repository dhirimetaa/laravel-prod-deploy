<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Tests;

final class ScriptRegistrarTest extends TestCaseBase
{
    public function test_merge_adds_missing_deploy_scripts(): void
    {
        $composerJson = [
            'name' => 'acme/app',
            'type' => 'project',
            'scripts' => [
                'test' => '@php artisan test',
            ],
        ];

        $result = \Dhirimetaa\ProdDeploy\Composer\ScriptRegistrar::merge($composerJson);

        $this->assertTrue($result['merged']);
        $this->assertContains('deploy:build', $result['added']);
        $this->assertSame('prod-deploy build', $result['data']['scripts']['deploy:build'][1]);
        $this->assertSame('@php artisan test', $result['data']['scripts']['test']);
    }

    public function test_merge_preserves_existing_scripts(): void
    {
        $composerJson = [
            'name' => 'acme/app',
            'type' => 'project',
            'scripts' => [
                'deploy:build' => 'custom-build',
            ],
        ];

        $result = \Dhirimetaa\ProdDeploy\Composer\ScriptRegistrar::merge($composerJson);

        $this->assertTrue($result['merged']);
        $this->assertSame('custom-build', $result['data']['scripts']['deploy:build']);
        $this->assertArrayHasKey('deploy:push', $result['data']['scripts']);
    }

    public function test_merge_noop_when_all_scripts_present(): void
    {
        $composerJson = [
            'name' => 'acme/app',
            'type' => 'project',
            'scripts' => \Dhirimetaa\ProdDeploy\Composer\ScriptRegistrar::scripts(),
        ];

        $result = \Dhirimetaa\ProdDeploy\Composer\ScriptRegistrar::merge($composerJson);

        $this->assertFalse($result['merged']);
        $this->assertSame([], $result['added']);
    }
}
