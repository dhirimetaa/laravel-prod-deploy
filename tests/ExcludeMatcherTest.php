<?php

declare(strict_types=1);

namespace Rashydhc\ProdDeploy\Tests;

final class ExcludeMatcherTest extends TestCaseBase
{
    public function test_public_hot_is_excluded_with_prefix(): void
    {
        $patterns = ['/public/hot'];

        $this->assertTrue($this->kernel->pathMatchesExclude('public/hot', $patterns));
        $this->assertFalse($this->kernel->pathMatchesExclude('public/index.php', $patterns));
    }

    public function test_glob_patterns_match_bootstrap_cache(): void
    {
        $patterns = ['/bootstrap/cache/*.php'];

        $this->assertTrue($this->kernel->pathMatchesExclude('bootstrap/cache/config.php', $patterns));
        $this->assertFalse($this->kernel->pathMatchesExclude('bootstrap/app.php', $patterns));
    }

    public function test_directory_trailing_slash(): void
    {
        $patterns = ['/tests/'];

        $this->assertTrue($this->kernel->pathMatchesExclude('tests/Unit/FooTest.php', $patterns));
        $this->assertFalse($this->kernel->pathMatchesExclude('app/Models/User.php', $patterns));
    }

    public function test_simple_filename_pattern(): void
    {
        $patterns = ['*.md'];

        $this->assertTrue($this->kernel->pathMatchesExclude('README.md', $patterns));
        $this->assertFalse($this->kernel->pathMatchesExclude('app/Models/User.php', $patterns));
    }
}
