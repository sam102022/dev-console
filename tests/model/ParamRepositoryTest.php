<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParamRepositoryTest extends TestCase
{
    final public function testGettersAndSetters(): void
    {
        $paramRepository = new ParamRepository();

        $paramRepository->setDatabaseHost('localhost');
        $this->assertEquals('localhost', $paramRepository->getDatabaseHost());

        $paramRepository->setDatabasePort(3306);
        $this->assertEquals(3306, $paramRepository->getDatabasePort());

        $paramRepository->setDatabaseName('iptv');
        $this->assertEquals('iptv', $paramRepository->getDatabaseName());

        $paramRepository->setDatabaseUser('root');
        $this->assertEquals('root', $paramRepository->getDatabaseUser());

        $paramRepository->setDatabasePassword('');
        $this->assertEquals('', $paramRepository->getDatabasePassword());
    }

    #[DataProvider('parseDataProvider')]
    final public function testParse(array $params, array $expected): void
    {
        $paramRepository = ParamRepository::parse($params);

        $this->assertEquals($expected['database_host'], $paramRepository->getDatabaseHost());
        $this->assertEquals($expected['database_port'], $paramRepository->getDatabasePort());
        $this->assertEquals($expected['database_name'], $paramRepository->getDatabaseName());
        $this->assertEquals($expected['database_user'], $paramRepository->getDatabaseUser());
        $this->assertEquals($expected['database_password'], $paramRepository->getDatabasePassword());
    }

    public static function parseDataProvider(): array
    {
        return [
            'all_params' => [
                'params' => [
                    'database_host' => 'db_host',
                    'database_port' => 3307,
                    'database_name' => 'db_name',
                    'database_user' => 'db_user',
                    'database_password' => 'db_pass',
                ],
                'expected' => [
                    'database_host' => 'db_host',
                    'database_port' => 3307,
                    'database_name' => 'db_name',
                    'database_user' => 'db_user',
                    'database_password' => 'db_pass',
                ],
            ],
        ];
    }

    #[DataProvider('parseThrowsExceptionDataProvider')]
    final public function testParseThrowsException(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certains paramètres database requis sont manquants.');
        ParamRepository::parse($params);
    }

    public static function parseThrowsExceptionDataProvider(): array
    {
        return [
            'partial_params' => [
                'params' => [
                    'database_host' => 'db_host',
                    'database_name' => 'db_name',
                ],
            ],
            'empty_params' => [
                'params' => [],
            ],
        ];
    }
}