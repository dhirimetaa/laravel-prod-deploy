<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Tests;

final class ManifestStoreTest extends TestCaseBase
{
    public function test_load_push_manifest_returns_empty_when_missing(): void
    {
        $manifest = $this->kernel->loadPushManifest(false);

        $this->assertSame([], $manifest['files']);
    }

    public function test_save_and_load_push_manifest(): void
    {
        $previous = ['app/Models/User.php' => 'abc123'];
        $uploaded = ['app/Models/User.php' => 'def456', 'routes/web.php' => 'ghi789'];
        $localFiles = ['app/Models/User.php', 'routes/web.php'];

        $this->kernel->savePushManifest(
            $previous,
            $uploaded,
            $localFiles,
            '/home/user/app',
            'test push'
        );

        $loaded = $this->kernel->loadPushManifest(false);

        $this->assertSame('def456', $loaded['files']['app/Models/User.php']);
        $this->assertSame('ghi789', $loaded['files']['routes/web.php']);
        $this->assertSame('/home/user/app', $loaded['remote_path'] ?? null);
    }

    public function test_full_flag_resets_manifest_for_diff(): void
    {
        $manifestPath = $this->app->pushManifestPath();
        file_put_contents($manifestPath, json_encode([
            'files' => ['app/Foo.php' => 'hash1'],
        ], JSON_THROW_ON_ERROR));

        $full = $this->kernel->loadPushManifest(true);
        $this->assertSame([], $full['files']);
    }

    public function test_build_manifest_vendor_freshness(): void
    {
        $prodfiles = $this->projectRoot.'/prodfiles';
        mkdir($prodfiles.'/vendor', 0777, true);
        file_put_contents($prodfiles.'/vendor/autoload.php', '<?php');

        $this->kernel->saveBuildManifest();
        $this->assertTrue($this->kernel->vendorIsFresh($prodfiles));
    }
}
