<?php
declare(strict_types=1);

namespace App\tests\config;

use App\config\PathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PathResolverTest extends TestCase
{
    private PathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PathResolver();
    }

    /**
     * Provider for existing paths in the project to resolve (parameterized)
     */
    public static function existingPathsProvider(): array
    {
        return [
            'tests directory' => ['tests'],
            'src/config directory' => ['src/config'],
            'public directory' => ['public']
        ];
    }

    #[DataProvider('existingPathsProvider')]
    public function testResolveExistingPaths(string $relativePath): void
    {
        $resolved = $this->resolver->resolve($relativePath);

        $this->assertNotFalse($resolved);
        $this->assertTrue(is_dir($resolved));
        $this->assertStringContainsString($relativePath, str_replace('\\', '/', $resolved));
    }

    public function testResolveCreatesNewDirectory(): void
    {
        $testRelativePath = 'var/cache/test_path_resolver_tmp_' . uniqid();
        $projectRootDir = dirname(__DIR__, 2) . '/';
        $fullTestPath = $projectRootDir . $testRelativePath;

        // Ensure it doesn't exist before resolving
        if (is_dir($fullTestPath)) {
            rmdir($fullTestPath);
        }

        $this->assertFalse(is_dir($fullTestPath));

        $resolved = $this->resolver->resolve($testRelativePath);

        $this->assertNotFalse($resolved);
        $this->assertTrue(is_dir($resolved));
        $this->assertEquals(realpath($fullTestPath), $resolved);

        // Clean up
        if (is_dir($resolved)) {
            rmdir($resolved);
        }
    }

    public function testResolveInvalidPathThrowsException(): void
    {
        $this->expectException(\Throwable::class);
        // Overly invalid characters or paths on Windows or Linux that can't be created
        @$this->resolver->resolve('invalid://path/??*invalid');
    }
}
