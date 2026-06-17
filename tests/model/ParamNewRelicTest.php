<?php
declare(strict_types=1);

namespace App\tests\model;

use App\model\ParamNewRelic;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ParamNewRelicTest extends TestCase
{
    final public function testGettersAndSetters(): void
    {
        $paramNewRelic = new ParamNewRelic();

        $paramNewRelic->setApiUser('user');
        $this->assertEquals('user', $paramNewRelic->getApiUser());

        $paramNewRelic->setApiKeyRec('key_rec');
        $this->assertEquals('key_rec', $paramNewRelic->getApiKeyRec());

        $paramNewRelic->setApiKeyProd('key_prod');
        $this->assertEquals('key_prod', $paramNewRelic->getApiKeyProd());

        $paramNewRelic->setAccountIdDev(1);
        $this->assertEquals(1, $paramNewRelic->getAccountIdDev());

        $paramNewRelic->setAccountIdRec(2);
        $this->assertEquals(2, $paramNewRelic->getAccountIdRec());

        $paramNewRelic->setAccountIdPreprod(3);
        $this->assertEquals(3, $paramNewRelic->getAccountIdPreprod());

        $paramNewRelic->setAccountIdProd(4);
        $this->assertEquals(4, $paramNewRelic->getAccountIdProd());
    }

    #[DataProvider('parseDataProvider')]
    final public function testParse(array $params, array $expected): void
    {
        $paramNewRelic = ParamNewRelic::parse($params);

        $this->assertEquals($expected['api_user'], $paramNewRelic->getApiUser());
        $this->assertEquals($expected['api_key_rec'], $paramNewRelic->getApiKeyRec());
        $this->assertEquals($expected['api_key_prod'], $paramNewRelic->getApiKeyProd());
        $this->assertEquals($expected['account_id_dev'], $paramNewRelic->getAccountIdDev());
        $this->assertEquals($expected['account_id_rec'], $paramNewRelic->getAccountIdRec());
        $this->assertEquals($expected['account_id_preprod'], $paramNewRelic->getAccountIdPreprod());
        $this->assertEquals($expected['account_id_prod'], $paramNewRelic->getAccountIdProd());
    }

    public static function parseDataProvider(): array
    {
        return [
            'all_params' => [
                'params' => [
                    'newrelic-api-user' => 'nr_user',
                    'newrelic-api-key-rec' => 'nr_key_rec',
                    'newrelic-api-key-prod' => 'nr_key_prod',
                    'newrelic-account-id-dev' => '1',
                    'newrelic-account-id-rec' => '2',
                    'newrelic-account-id-pp' => '3',
                    'newrelic-account-id-prod' => '4',
                ],
                'expected' => [
                    'api_user' => 'nr_user',
                    'api_key_rec' => 'nr_key_rec',
                    'api_key_prod' => 'nr_key_prod',
                    'account_id_dev' => 1,
                    'account_id_rec' => 2,
                    'account_id_preprod' => 3,
                    'account_id_prod' => 4,
                ],
            ],
        ];
    }

    #[DataProvider('parseThrowsExceptionDataProvider')]
    final public function testParseThrowsException(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certains paramètres newRelic requis sont manquants.');
        ParamNewRelic::parse($params);
    }

    public static function parseThrowsExceptionDataProvider(): array
    {
        return [
            'partial_params' => [
                'params' => [
                    'newrelic-api-user' => 'nr_user',
                    'newrelic-account-id-prod' => '4',
                ],
            ],
            'empty_params' => [
                'params' => [],
            ],
        ];
    }
}