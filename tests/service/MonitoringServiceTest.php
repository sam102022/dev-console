<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\FunctionalException;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\MonitoringService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

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
            self::$appConfig,
            self::$loggerFactory
        );
    }

    /**
     * @throws GuzzleException
     */
    public function testCheckOneThrowsExceptionWhenEnvIsNull(): void
    {
        $this->expectException(FunctionalException::class);
        $this->service->checkOne('any-project', null);
    }

    /**
     * @throws FunctionalException
     * @throws GuzzleException
     */
    public function testCheckOneReturnsEmptyWhenProjectNotFound(): void
    {
        $this->gitlabService->method('getProjectByCode')->with('not-found')->willReturn(null);
        $result = $this->service->checkOne('not-found', EnumEnvironment::DEV);
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
                ['status' => 'DOWN', 'httpCode' => 200, 'error' => 'ERROR JSON']
            ],
            'Guzzle exception' => [
                new RequestException('Error Communicating with Server', new Request('GET', 'test')),
                ['status' => 'DOWN', 'httpCode' => 500, 'error' => 'Error Communicating with Server']
            ],
        ];
    }

    #[DataProvider('callAndCheckProvider')]
    public function testCallAndCheck(Response|RequestException $response, array $expected): void
    {
        $url = 'http://test.url';
        if ($response instanceof \Exception) {
            $this->client->method('request')->with('GET', $url)->willThrowException($response);
        } else {
            $this->client->method('request')->with('GET', $url)->willReturn($response);
        }

        $method = new \ReflectionMethod(MonitoringService::class, 'callAndCheck');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $url);

        $this->assertEquals($expected, $result);
    }
}
