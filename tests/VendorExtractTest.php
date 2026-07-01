<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Tests;

final class VendorExtractTest extends TestCaseBase
{
    public function test_build_vendor_extract_shell_rotates_vendor(): void
    {
        $shell = $this->kernel->buildVendorExtractShellCommand();

        $this->assertStringContainsString('rm -rf vendor-old', $shell);
        $this->assertStringContainsString('mv vendor vendor-old', $shell);
        $this->assertStringContainsString('unzip -o vendor.zip', $shell);
        $this->assertStringContainsString('rm -f vendor.zip', $shell);
    }

    public function test_build_vendor_extract_shell_cleanup_old(): void
    {
        $shell = $this->kernel->buildVendorExtractShellCommand(true);

        $this->assertStringEndsWith('rm -rf vendor-old', $shell);
    }

    public function test_terminal_blocks_shell_operators(): void
    {
        $this->assertNotNull($this->kernel->validateTerminalCommand('ls; rm -rf /'));
        $this->assertNull($this->kernel->validateTerminalCommand('ls -la vendor'));
    }
}
