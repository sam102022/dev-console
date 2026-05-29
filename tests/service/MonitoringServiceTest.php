<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\FunctionalException;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\MonitoringService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

class MonitoringServiceTest extends AbstractServiceCase
{
    private GitlabService $gitlabService;
    private ClientInterface $client;
    private MonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->client = $this->createMock(ClientInterface::class);
        
        $this->service = new MonitoringService(
            $this->gitlabService,
            $this->client,
            self::$loggerFactory
        );
    }

    public function testCheckOneThrowsExceptionWhenEnvIsNull(): void
    {
        $this->expectException(FunctionalException::class);
        $this->service->getMonitoringData('any-project', null);
    }

    /**
     * @throws FunctionalException
     */
    public function testCheckOneReturnsEmptyWhenProjectNotFound(): void
    {
        $this->gitlabService->method('getProjectByCode')->with('not-found')->willReturn(null);
        $result = $this->service->getMonitoringData('not-found', EnumEnvironment::DEV);
        $this->assertEmpty($result);
    }

    public static function callAndCheckProvider(): array
    {
        return [
            'status UP' => [
                new Response(200, [], json_encode(['status' => 'UP'])),
                ['status' => 'UP', 'httpCode' => 200, 'error' => null]
            ],
            'status DOWN' => [
                new Response(200, [], json_encode(['status' => 'DOWN'])),
                ['status' => 'DOWN', 'httpCode' => 200, 'error' => null]
            ],
            'invalid JSON' => [
                new Response(200, [], 'invalid-json'),
                ['status' => 'DOWN', 'httpCode' => 200, 'error' => 'JSON invalide']
            ],
            'Guzzle exception' => [
                new RequestException('Error Communicating with Server', new Request('GET', 'test')),
                ['status' => 'DOWN', 'httpCode' => 0, 'error' => 'Error Communicating with Server']
            ],
            'empty URL' => [
                null,
                ['status' => 'N/A', 'httpCode' => null, 'error' => 'URL non définie'],
                ''
            ]
        ];
    }

    /**
     * @throws ReflectionException
     */
    #[DataProvider('callAndCheckProvider')]
    public function testCheckHealth(?object $response, array $expected, string $url = 'http://test.url'): void
    {
        if ($response instanceof \Exception) {
            $this->client->method('request')->with('GET', $url)->willThrowException($response);
        } elseif ($response instanceof Response) {
            $this->client->method('request')->with('GET', $url)->willReturn($response);
        }

        $result = $this->service->checkHealth($url);

        $this->assertEquals($expected, $result);
    }
}
