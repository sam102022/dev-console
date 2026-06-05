<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamPostman;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParamPostmanTest extends TestCase
{
    final public function testGettersAndSetters(): void
    {
        $paramPostman = new ParamPostman();

        $paramPostman->setPostmanApiKey('test_api_key');
        $this->assertEquals('test_api_key', $paramPostman->getPostmanApiKey());

        $paramPostman->setPostmanApiUrl('https://api.postman.com');
        $this->assertEquals('https://api.postman.com', $paramPostman->getPostmanApiUrl());
    }

    #[DataProvider('parseDataProvider')]
    final public function testParse(array $params, array $expected): void
    {
        $paramPostman = ParamPostman::parse($params);

        $this->assertEquals($expected['postman_api_key'], $paramPostman->getPostmanApiKey());
        $this->assertEquals($expected['postman_api_url'], $paramPostman->getPostmanApiUrl());
    }

    public static function parseDataProvider(): array
    {
        return [
            'all_params' => [
                'params' => [
                    'postman_api_key' => 'pm_key',
                    'postman_api_url' => 'https://pm.test',
                ],
                'expected' => [
                    'postman_api_key' => 'pm_key',
                    'postman_api_url' => 'https://pm.test',
                ],
            ],
        ];
    }

    #[DataProvider('parseThrowsExceptionDataProvider')]
    final public function testParseThrowsException(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certains paramètres postman requis sont manquants.');
        ParamPostman::parse($params);
    }

    public static function parseThrowsExceptionDataProvider(): array
    {
        return [
            'partial_params' => [
                'params' => [
                    'postman_api_key' => 'pm_key_partial',
                ],
            ],
            'empty_params' => [
                'params' => [],
            ],
        ];
    }
}