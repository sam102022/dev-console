<?php
declare(strict_types=1);

namespace App\tests\client;

use App\client\GitLabClient;
use App\config\AppConfig;
use App\factory\LoggerFactory;
use App\model\ParamConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GitLabClientTest extends AbstractClientCase
{
    private MockHandler $mockHandler;
    //private GitLabClient $client;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
    }

    private function createClientWithMockedResponses(array $responses): GitLabClient
    {
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $client = new GitLabClient($guzzleClient, self::$appConfig, self::$loggerFactory);

        foreach ($responses as $response) {
            $this->mockHandler->append($response);
        }

        return $client;
    }

    public static function getAllProjectsProvider(): array
    {
        return [
            'no group path, single page' => [
                null,
                [new Response(200, [], json_encode([['id' => 1, 'name' => 'Project 1']]))],
                [['id' => 1, 'name' => 'Project 1']]
            ],
            'with group path, single page' => [
                'group/path',
                [new Response(200, [], json_encode([['id' => 2, 'name' => 'Project 2']]))],
                [['id' => 2, 'name' => 'Project 2']]
            ],
            'multiple pages' => [
                null,
                [
                    new Response(200, ['X-Next-Page' => '2'], json_encode([['id' => 1, 'name' => 'Project 1']])),
                    new Response(200, [], json_encode([['id' => 2, 'name' => 'Project 2']]))
                ],
                [['id' => 1, 'name' => 'Project 1'], ['id' => 2, 'name' => 'Project 2']]
            ],
            'empty response' => [
                null,
                [new Response(200, [], '[]')],
                []
            ],
        ];
    }

    #[DataProvider('getAllProjectsProvider')]
    final public function testGetAllProjects(?string $groupPath, array $responses, array $expected): void
    {
        $client = $this->createClientWithMockedResponses($responses);
        $projects = $client->getAllProjects($groupPath);
        $this->assertEquals($expected, $projects);
    }

    final public function testListRepositoryTree(): void
    {
        $expected = [['id' => 'file1', 'name' => 'file1.txt']];
        $client = $this->createClientWithMockedResponses([
            new Response(200, [], json_encode($expected))
        ]);

        $files = $client->listRepositoryTree(1, 'src', 'main');
        $this->assertEquals($expected, $files);
    }

    public static function getFileProvider(): array
    {
        return [
            'raw file content' => [
                1, 'src/file.txt', true, 'main',
                new Response(200, [], 'raw content'),
                'raw content'
            ],
            'json file content' => [
                1, 'src/file.json', false, 'main',
                new Response(200, [], json_encode(['key' => 'value'])),
                ['key' => 'value']
            ],
            'file not found' => [
                1, 'nonexistent.txt', true, 'main',
                new Response(404),
                null
            ]
        ];
    }

    #[DataProvider('getFileProvider')]
    final public function testGetFile(int $projectId, string $filePath, bool $raw, string $branch, Response $response, string|array|null $expected): void
    {
        $client = $this->createClientWithMockedResponses([$response]);
        $content = $client->getFile($projectId, $filePath, $raw, $branch);
        $this->assertEquals($expected, $content);
    }
}
