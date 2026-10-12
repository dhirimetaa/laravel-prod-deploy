<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Tests;

final class WindowsCommandTest extends TestCaseBase
{
    public function test_commands_with_a_path_are_left_alone(): void
    {
        $this->assertSame('C:/tools/npm.cmd', $this->kernel->resolveExecutable('C:/tools/npm.cmd'));
        $this->assertSame('C:\tools\bun.exe', $this->kernel->resolveExecutable('C:\tools\bun.exe'));
    }

    public function test_bare_commands_get_their_full_path_on_windows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('npm', $this->kernel->resolveExecutable('npm'));

            return;
        }

        $resolved = $this->kernel->resolveExecutable('cmd');

        $this->assertMatchesRegularExpression('/[\\\\\/]cmd\.exe$/i', $resolved);
        $this->assertFileExists($resolved);
        $this->assertSame('no-such-tool-xyz', $this->kernel->resolveExecutable('no-such-tool-xyz'));
    }

    public function test_composer_runs_the_phar_composer_is_running_from(): void
    {
        $phar = $this->projectRoot.'/composer.phar';
        file_put_contents($phar, '');
        $previous = getenv('COMPOSER_BINARY');
        putenv('COMPOSER_BINARY='.$phar);

        try {
            $this->assertSame([PHP_BINARY, $phar, 'install'], $this->kernel->composerCommand(['install']));
        } finally {
            putenv($previous === false ? 'COMPOSER_BINARY' : 'COMPOSER_BINARY='.$previous);
        }
    }
}
