<?php
declare(strict_types=1);

namespace App\tests\client;

use App\client\NewRelicClient;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\model\EnumEnvironment;
use App\model\ParamNewRelic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Stream;
use JsonException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NewRelicClientTest extends TestCase
{
    private Client $guzzleClientMock;
    private ParamNewRelic $paramNewRelicMock;
    private NewRelicClient $newRelicClient;

    protected function setUp(): void
    {
        $this->guzzleClientMock = $this->createMock(Client::class);
        $this->paramNewRelicMock = $this->createMock(ParamNewRelic::class);
        $loggerFactoryMock = $this->createMock(LoggerFactory::class);
        $loggerFactoryMock->method('get')->willReturn($this->createMock(Logger::class));

        $this->newRelicClient = new NewRelicClient(
            $this->guzzleClientMock,
            $this->paramNewRelicMock,
            $loggerFactoryMock
        );
    }

    /**
     * @throws JsonException
     * @throws TechnicalException
     */
    #[DataProvider('entityGuidDataProvider')]
    final public function testGetEntityGuid(EnumEnvironment $env, string $apiKey, int $accountId, ?string $expectedGuid, string $responseBody): void
    {
        // Arrange
        if ($env === EnumEnvironment::PROD) {
            $this->paramNewRelicMock->method('getApiKeyProd')->willReturn($apiKey);
            $this->paramNewRelicMock->method('getAccountIdProd')->willReturn($accountId);
        } else {
            $this->paramNewRelicMock->method('getApiKeyRec')->willReturn($apiKey);
            $this->paramNewRelicMock->method('getAccountIdRec')->willReturn($accountId);
        }

        $response = new Response(200, [], $responseBody);
        $this->guzzleClientMock->method('post')->willReturn($response);

        // Act
        $guid = $this->newRelicClient->getEntityGuid('test-app', $env);

        // Assert
        $this->assertEquals($expectedGuid, $guid);
    }

    public static function entityGuidDataProvider(): array
    {
        $successBody = json_encode([
            'data' => [
                'actor' => [
                    'entitySearch' => [
                        'results' => [
                            'entities' => [
                                ['guid' => 'test-guid-123']
                            ]
                        ]
                    ]
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $notFoundBody = json_encode(['data' => ['actor' => ['entitySearch' => ['results' => ['entities' => []]]]]], JSON_THROW_ON_ERROR);

        return [
            'REC environment - Success' => [EnumEnvironment::REC, 'rec-api-key', 12345, 'test-guid-123', $successBody],
            'PROD environment - Success' => [EnumEnvironment::PROD, 'prod-api-key', 67890, 'test-guid-123', $successBody],
            'Entity not found' => [EnumEnvironment::PROD, 'prod-api-key', 67890, null, $notFoundBody],
        ];
    }

    /**
     * @throws JsonException
     */
    final public function testGetEntityGuidThrowsTechnicalExceptionOnGuzzleError(): void
    {
        // Arrange
        $this->paramNewRelicMock->method('getApiKeyProd')->willReturn('any-key');
        $this->paramNewRelicMock->method('getAccountIdProd')->willReturn(111);

        $this->guzzleClientMock->method('post')->willThrowException($this->createMock(GuzzleException::class));

        // Assert
        $this->expectException(TechnicalException::class);

        // Act
        $this->newRelicClient->getEntityGuid('test-app', EnumEnvironment::PROD);
    }

    final public function testGenerateEntityUrl(): void
    {
        // Arrange
        $guid = 'test-guid-123';
        $expectedUrl = 'https://one.newrelic.com/redirect/entity/' . urlencode($guid);

        // Act
        $url = $this->newRelicClient->generateEntityUrl($guid);

        // Assert
        $this->assertEquals($expectedUrl, $url);
    }

    /**
     * @throws JsonException
     * @throws TechnicalException
     */
    final public function testGetAllProjects(): void
    {
        // Arrange
        $env = EnumEnvironment::PROD;
        $apiKey = 'prod-api-key';
        $accountId = 67890;
        $expectedProjects = [
            ['guid' => 'guid1', 'name' => 'Project A'],
            ['guid' => 'guid2', 'name' => 'Project B'],
        ];
        $responseBody = json_encode(['data' => ['actor' => ['entitySearch' => ['results' => ['entities' => $expectedProjects]]]]], JSON_THROW_ON_ERROR);

        $this->paramNewRelicMock->method('getApiKeyProd')->willReturn($apiKey);
        $this->paramNewRelicMock->method('getAccountIdProd')->willReturn($accountId);

        $response = new Response(200, [], $responseBody);
        $this->guzzleClientMock->method('post')->willReturn($response);

        // Act
        $projects = $this->newRelicClient->getAllProjects($env);

        // Assert
        $this->assertEquals($expectedProjects, $projects['data']['actor']['entitySearch']['results']['entities']);
    }

    /**
     * @throws JsonException
     */
    final public function testGetAllProjectsThrowsTechnicalExceptionOnGuzzleError(): void
    {
        // Arrange
        $this->paramNewRelicMock->method('getApiKeyProd')->willReturn('any-key');
        $this->paramNewRelicMock->method('getAccountIdProd')->willReturn(111);

        $this->guzzleClientMock->method('post')->willThrowException($this->createMock(GuzzleException::class));

        // Assert
        $this->expectException(TechnicalException::class);

        // Act
        $this->newRelicClient->getAllProjects(EnumEnvironment::PROD);
    }
}