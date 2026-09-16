<?php
declare(strict_types=1);

namespace App\tests\config;

use App\config\EnvLoader;
use App\exception\TechnicalException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvLoaderTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('root');
    }

    /**
     * Provider for loading success scenarios (parameterized)
     */
    public static function loadSuccessProvider(): array
    {
        return [
            'test env with comments and spaces' => [
                'env' => 'test',
                'filename' => '.env-test',
                'content' => "# Main Database\nDB_HOST=localhost\n\n# Redis\nREDIS_HOST=127.0.0.1\nPORT=6379",
                'expected' => [
                    'DB_HOST' => 'localhost',
                    'REDIS_HOST' => '127.0.0.1',
                    'PORT' => '6379'
                ]
            ],
            'dev env simple' => [
                'env' => 'dev',
                'filename' => '.env-dev',
                'content' => "APP_DEBUG=true\nURL=http://localhost",
                'expected' => [
                    'APP_DEBUG' => 'true',
                    'URL' => 'http://localhost'
                ]
            ],
            'prod env falls back to .env' => [
                'env' => 'prod',
                'filename' => '.env',
                'content' => "APP_ENV=prod\nSECURE=1",
                'expected' => [
                    'APP_ENV' => 'prod',
                    'SECURE' => '1'
                ]
            ]
        ];
    }

    #[DataProvider('loadSuccessProvider')]
    public function testLoadSuccess(string $env, string $filename, string $content, array $expected): void
    {
        // Write virtual file
        vfsStream::newFile($filename)
            ->at($this->root)
            ->setContent($content);

        $loaded = EnvLoader::load($env, vfsStream::url('root'));

        $this->assertEquals($expected, $loaded);
    }

    public function testLoadFileNotExistsThrowsException(): void
    {
        $this->expectException(TechnicalException::class);
        $this->expectExceptionMessage('Fichier vfs://root' . DIRECTORY_SEPARATOR . '.env-notfound non trouvé (notfound)');

        EnvLoader::load('notfound', vfsStream::url('root'));
    }
}
