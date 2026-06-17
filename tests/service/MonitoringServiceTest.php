<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\FunctionalException;
use App\exception\TechnicalException;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\MonitoringService;
use App\tests\fixtures\ProjectFixtures;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

class MonitoringServiceTest extends AbstractServiceCase
{
    private GitlabService $gitlabService;
    private ClientInterface $client;
    private MonitoringService $service;

    final protected function setUp(): void
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

    final public function testCheckOneThrowsExceptionWhenEnvIsNull(): void
    {
        $this->expectException(FunctionalException::class);
        $this->service->getMonitoringData('any-project', null);
    }

    /**
     * @throws FunctionalException
     */
    final public function testCheckOneReturnsEmptyWhenProjectNotFound(): void
    {
        $this->gitlabService->method('getProjectByCode')->with('not-found')->willReturn(null);
        $this->expectException(FunctionalException::class);
        $this->service->getMonitoringData('not-found', EnumEnvironment::DEV);
    }

    public static function callAndCheckProvider(): array
    {
        return [
            'status UP' => [
                new Response(200, [], json_encode(['status' => 'UP'])),
                new Response(200, [], json_encode(['build' => ['version' => '1.0.0']])),
                ['actuatorInfo' => ['version' => '1.0.0', 'httpCode' => 200, 'error' => null],
                    'health' => ['status' => 'UP', 'httpCode' => 200, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '', 'actuatorInfoUrl' => 'http://url/dev']]
            ],
            'status DOWN' => [
                new Response(200, [], json_encode(['status' => 'DOWN'])),
                new Response(200, [], json_encode(['build' => ['version' => '']])),
                ['actuatorInfo' => ['version' => '', 'httpCode' => 200, 'error' => null],
                    'health' => ['status' => 'DOWN', 'httpCode' => 200, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '', 'actuatorInfoUrl' => 'http://url/dev']]
            ],
            'invalid JSON' => [
                new Response(200, [], 'invalid-json'),
                new Response(200, [], 'invalid-json'),
                ['actuatorInfo' => ['version' => 'N/A', 'httpCode' => 200, 'error' => 'JSON invalide'],
                    'health' => ['status' => 'DOWN', 'httpCode' => 200, 'error' => 'JSON invalide'],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '', 'actuatorInfoUrl' => 'http://url/dev']]
            ],
            'Guzzle exception' => [
                new RequestException('Error Communicating with Server', new Request('GET', 'test')),
                new RequestException('Error Communicating with Server', new Request('GET', 'test')),
                ['actuatorInfo' => ['version' => 'N/A', 'httpCode' => 0, 'error' => 'Error Communicating with Server'],
                    'health' => ['status' => 'DOWN', 'httpCode' => 0, 'error' => 'Error Communicating with Server'],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '', 'actuatorInfoUrl' => 'http://url/dev']]
            ],
            'empty URL' => [
                null,
                null,
                ['actuatorInfo' => ['version' => 'N/A', 'httpCode' => 0, 'error' => null],
                    'health' => ['status' => 'DOWN', 'httpCode' => 0, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '', 'actuatorInfoUrl' => 'http://url/dev']],
            ]
        ];
    }

    /**
     * @throws FunctionalException|TechnicalException
     */
    #[DataProvider('callAndCheckProvider')]
    final public function testCheckHealth(?object $response1, ?object $response2, array $expected): void
    {
        if ($response1 instanceof Exception) {
            $this->client->method('request')->willThrowException($response1);
        } elseif ($response1 instanceof Response) {
            $this->client->method('request')->willReturnOnConsecutiveCalls($response1, $response2);
        }

        $project = ProjectFixtures::getProjectWithUrls();

        $this->gitlabService->method('getProjectByCode')->with('any-project')->willReturn($project);

        $result = $this->service->getMonitoringData('any-project', EnumEnvironment::DEV);

        $this->assertEquals($expected, $result);
    }
}