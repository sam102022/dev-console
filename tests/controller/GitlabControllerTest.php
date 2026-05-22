<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\GitlabController;
use App\model\ParamConfig;
use App\service\GitlabService;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

class GitlabControllerTest extends AbstractControllerCase
{
    private GitlabService $gitlabService;
    private GitlabController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->paramConfig = $this->createMock(ParamConfig::class);
        
        $this->controller = new GitlabController(
            $this->gitlabService,
            self::$appConfig,
            self::$loggerFactory
        );

        // Reset $_REQUEST before each test
        $_REQUEST = [];
    }

    public static function handleRequestProvider(): array
    {
        return [
            'ACTION_GITLAB_SCAN success' => [
                'action' => ACTION_GITLAB_SCAN,
                'requestParams' => [],
                'serviceMethod' => 'scan',
                'serviceArgs' => [],
                'serviceReturn' => ['status' => 'success', 'data' => ['scanned' => true]],
                'expectedResponse' => json_encode(['status' => 'success', 'data' => ['scanned' => true]]),
            ],
            'ACTION_GITLAB_TREE success' => [
                'action' => ACTION_GITLAB_TREE,
                'requestParams' => ['path' => 'src/'],
                'serviceMethod' => 'getTree',
                'serviceArgs' => [656, 'src/'],
                'serviceReturn' => [['name' => 'file1.txt']],
                'expectedResponse' => json_encode([['name' => 'file1.txt']]),
            ],
            'ACTION_GITLAB_TREE default path' => [
                'action' => ACTION_GITLAB_TREE,
                'requestParams' => [],
                'serviceMethod' => 'getTree',
                'serviceArgs' => [656, ''],
                'serviceReturn' => ['content' => [['name' => 'root_dir']]],
                'expectedResponse' => json_encode(['content' => [['name' => 'root_dir']]],),
            ],
            'ACTION_GITLAB_FILE success' => [
                'action' => ACTION_GITLAB_FILE,
                'requestParams' => ['file' => 'config.json'],
                'serviceMethod' => 'getFile',
                'serviceArgs' => [656, 'config.json', 'master'],
                'serviceReturn' => ['content' => 'file content'],
                'expectedResponse' => json_encode(['content' => 'file content']),
            ],
            'ACTION_GITLAB_FILE default file' => [
                'action' => ACTION_GITLAB_FILE,
                'requestParams' => [],
                'serviceMethod' => 'getFile',
                'serviceArgs' => [656, '', 'master'],
                'serviceReturn' => ['content' => 'empty file content'],
                'expectedResponse' => json_encode(['content' => 'empty file content']),
            ],
            'Unknown action' => [
                'action' => 'unknown_action',
                'requestParams' => [],
                'serviceMethod' => null,
                'serviceArgs' => [],
                'serviceReturn' => ['content' => null],
                'expectedResponse' => json_encode(['error' => 'Action inconnue']),
            ],
        ];
    }

    #[DataProvider('handleRequestProvider')]
    public function testHandleRequest(
        string $action,
        array $requestParams,
        ?string $serviceMethod,
        array $serviceArgs,
        mixed $serviceReturn,
        string $expectedResponse
    ): void {
        // Prepare $_REQUEST
        $_REQUEST = $requestParams;

        // Configure GitlabService mock
        if ($serviceMethod) {
            $this->gitlabService->expects($this->once())
                ->method($serviceMethod)
                ->with(...$serviceArgs)
                ->willReturn($serviceReturn);
        } else {
            $this->gitlabService->expects($this->never())->method($this->anything());
        }

        // Capture output or check return value
        $response = $this->controller->handleRequest($action);

        $this->assertEquals($expectedResponse, $response);
    }

    public function testHandleRequestException(): void
    {
        $action = ACTION_GITLAB_SCAN;
        $errorMessage = 'Test exception message';

        $this->gitlabService->expects($this->once())
            ->method('scan')
            ->willThrowException(new Exception($errorMessage));

        $response = $this->controller->handleRequest($action);

        $expectedResponse = json_encode(['error' => $errorMessage]);
        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals(500, http_response_code());
    }
}
