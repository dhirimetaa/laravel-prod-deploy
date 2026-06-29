<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Tests;

final class PathsTest extends TestCaseBase
{
    public function test_normalize_path_converts_backslashes(): void
    {
        $this->assertSame('app/Models/User.php', $this->kernel->normalizePath('app\\Models\\User.php'));
    }

    public function test_relative_path_from_project_root(): void
    {
        $file = $this->projectRoot.'/app/Models/User.php';
        mkdir(dirname($file), 0777, true);
        file_put_contents($file, '<?php');

        $this->assertSame(
            'app/Models/User.php',
            $this->kernel->relativePath($file, $this->projectRoot)
        );
    }

    public function test_is_vendor_path(): void
    {
        $this->assertTrue($this->kernel->isVendorPath('vendor/autoload.php'));
        $this->assertTrue($this->kernel->isVendorPath('vendor/laravel/framework/src/Application.php'));
        $this->assertFalse($this->kernel->isVendorPath('app/Models/User.php'));
    }

    public function test_format_bytes(): void
    {
        $this->assertSame('512 B', $this->kernel->formatBytes(512));
        $this->assertSame('1 KB', $this->kernel->formatBytes(1024));
        $this->assertSame('1 MB', $this->kernel->formatBytes(1024 * 1024));
    }
}
