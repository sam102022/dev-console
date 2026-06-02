<?php
declare(strict_types=1);

namespace App\tests\service;

use App\exception\FunctionalException;
use App\model\EnumEnvironment;
use App\model\Project;
use App\service\GitlabService;
use App\service\MonitoringService;
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
    final public  function testCheckOneReturnsEmptyWhenProjectNotFound(): void
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
                ['health' => ['status' => 'UP', 'httpCode' => 200, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '']]
            ],
            'status DOWN' => [
                new Response(200, [], json_encode(['status' => 'DOWN'])),
                ['health' => ['status' => 'DOWN', 'httpCode' => 200, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '']]
            ],
            'invalid JSON' => [
                new Response(200, [], 'invalid-json'),
                ['health' => ['status' => 'DOWN', 'httpCode' => 200, 'error' => 'JSON invalide'],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '']]
            ],
            'Guzzle exception' => [
                new RequestException('Error Communicating with Server', new Request('GET', 'test')),
                ['health' => ['status' => 'DOWN', 'httpCode' => 0, 'error' => 'Error Communicating with Server'],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '']]
            ],
            'empty URL' => [
                null,
                ['health' => ['status' => 'DOWN', 'httpCode' => 0, 'error' => null],
                    'urls' => ['healthCheckUrl' => 'http://url/dev', 'logsUrl' => '']],
            ]
        ];
    }

    /**
     * @throws FunctionalException
     */
    #[DataProvider('callAndCheckProvider')]
    final public function testCheckHealth(?object $response, array $expected): void
    {
        if ($response instanceof Exception) {
            $this->client->method('request')->willThrowException($response);
        } elseif ($response instanceof Response) {
            $this->client->method('request')->willReturn($response);
        }

        $project = Project::build('New Project', 'service-name', 'sf', 'sfName', 'subsf', false,
                '2.7.18', '21',  'java', null, 'http://url/a', false,
            ['dev' => 'http://url/dev', 'rec' => 'http://url/rec', 'pp' => 'http://url/pp', 'prod' => 'http://url/prod'], [], [], []);

        $this->gitlabService->method('getProjectByCode')->with('any-project')->willReturn($project);

        $result = $this->service->getMonitoringData('any-project', EnumEnvironment::DEV);

        $this->assertEquals($expected, $result);
    }
}