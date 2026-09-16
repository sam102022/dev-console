<?php
declare(strict_types=1);

namespace App\tests\config;

use App\config\AppConfig;
use App\model\ParamConfig;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AppConfigTest extends AbstractTestCase
{
    private AppConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new AppConfig('test', 'templates', 'fr');
    }

    public function testConstructorAndBasics(): void
    {
        $this->assertEquals('test', $this->config->getEnv());
        $this->assertEquals('templates', $this->config->getPathTemplates());
        $this->assertEquals('fr', $this->config->getDefaultLocale());
        $this->assertInstanceOf(ParamConfig::class, $this->config->getParamConfig());
    }

    /**
     * Provider for various config path resolving checks (parameterized)
     */
    public static function configPathsProvider(): array
    {
        return [
            'path cache file' => ['getPathCacheFile', 'var/cache/file'],
            'path cache user' => ['getPathCacheUser', 'var/cache/user'],
            'path images' => ['getPathImages', 'public/images'],
            'path data' => ['getPathData', 'data']
        ];
    }

    #[DataProvider('configPathsProvider')]
    public function testPathsResolution(string $method, string $expectedSuffix): void
    {
        $resolvedPath = $this->config->{$method}();

        $this->assertNotEmpty($resolvedPath);
        $this->assertTrue(is_dir($resolvedPath));
        $this->assertStringContainsString($expectedSuffix, str_replace('\\', '/', $resolvedPath));
    }

    /**
     * Provider for basic network and host param getters (parameterized)
     */
    public static function paramGettersProvider(): array
    {
        return [
            'base url' => ['getBaseUrl', 'string'],
            'host' => ['getHost', 'string'],
            'port' => ['getPort', 'int']
        ];
    }

    #[DataProvider('paramGettersProvider')]
    public function testParamGetters(string $method, string $expectedType): void
    {
        $value = $this->config->{$method}();

        if ($expectedType === 'string') {
            $this->assertIsString($value);
            if ($method !== 'getBaseUrl') {
                $this->assertNotEmpty($value);
            }
        } elseif ($expectedType === 'int') {
            $this->assertIsInt($value);
            $this->assertGreaterThan(0, $value);
        }
    }
}
