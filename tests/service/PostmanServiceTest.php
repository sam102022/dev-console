<?php
declare(strict_types=1);

namespace App\tests\service;

use App\client\PostmanClient;
use App\service\PostmanService;
use App\tests\AbstractTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class PostmanServiceTest extends AbstractTestCase
{
    private PostmanClient $client;
    private PostmanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(PostmanClient::class);

        $this->service = new PostmanService($this->client, self::$loggerFactory);
    }

    public function testGetWorkspaces(): void
    {
        $expectedResult = ['workspaces' => [['id' => '1', 'name' => 'WS1']]];
        
        $this->client->expects($this->once())
            ->method('get')
            ->with('/workspaces')
            ->willReturn($expectedResult);

        $result = $this->service->getWorkspaces();
        $this->assertEquals($expectedResult, $result);
    }

    public static function createWorkspaceProvider(): array
    {
        return [
            'with description' => [
                'name' => 'My WS',
                'description' => 'Custom desc',
                'expectedPayload' => [
                    'workspace' => [
                        'name' => 'My WS',
                        'type' => 'team',
                        'description' => 'Custom desc'
                    ]
                ],
            ],
            'without description' => [
                'name' => 'My WS',
                'description' => '',
                'expectedPayload' => [
                    'workspace' => [
                        'name' => 'My WS',
                        'type' => 'team',
                        'description' => 'Créé via interface PHP'
                    ]
                ],
            ],
        ];
    }

    #[DataProvider('createWorkspaceProvider')]
    public function testCreateWorkspace(string $name, string $description, array $expectedPayload): void
    {
        $expectedResult = ['workspace' => ['id' => 'new-id']];

        $this->client->expects($this->once())
            ->method('post')
            ->with('/workspaces', $expectedPayload)
            ->willReturn($expectedResult);

        $result = $this->service->createWorkspace($name, $description);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetWorkspaceDetails(): void
    {
        $workspaceId = 'ws-123';
        $clientResponse = ['workspace' => ['id' => $workspaceId, 'name' => 'WS Name']];
        $expectedResult = ['workspace' => ['id' => $workspaceId, 'name' => 'WS Name']];

        $this->client->expects($this->once())
            ->method('get')
            ->with("/workspaces/$workspaceId")
            ->willReturn($clientResponse);

        $result = $this->service->getWorkspaceDetails($workspaceId);
        $this->assertEquals($expectedResult, $result);
    }

    public function testCreateEnvironment(): void
    {
        $workspaceId = 'ws-123';
        $name = 'Dev Env';
        $variables = ['apiUrl' => 'http://localhost', 'token' => '12345'];
        
        $expectedPayload = [
            'environment' => [
                'name' => $name,
                'values' => [
                    ['key' => 'apiUrl', 'value' => 'http://localhost', 'enabled' => true],
                    ['key' => 'token', 'value' => '12345', 'enabled' => true]
                ]
            ]
        ];
        
        $expectedResult = ['environment' => ['id' => 'env-id']];

        $this->client->expects($this->once())
            ->method('post')
            ->with("/environments?workspace=$workspaceId", $expectedPayload)
            ->willReturn($expectedResult);

        $result = $this->service->createEnvironment($workspaceId, $name, $variables);
        $this->assertEquals($expectedResult, $result);
    }

    public function testImportOpenApiSuccess(): void
    {
        $workspaceId = 'ws-123';
        $fileContent = ['openapi' => '3.0.0', 'info' => ['title' => 'API']];
        
        $expectedPayload = [
            'type' => 'json',
            'input' => $fileContent
        ];
        
        $expectedResult = ['collections' => [['id' => 'col-id']]];

        $this->client->expects($this->once())
            ->method('post')
            ->with("/import/openapi?workspace=$workspaceId", $expectedPayload)
            ->willReturn($expectedResult);

        $result = $this->service->importOpenApi($workspaceId, $fileContent);
        $this->assertEquals($expectedResult, $result);
    }

    public function testImportOpenApiThrowsExceptionWhenContentIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contenu OpenAPI manquant');

        $this->service->importOpenApi('ws-123', []);
    }
}
