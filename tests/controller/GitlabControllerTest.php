<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\GitlabController;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\NewRelicService;
use App\tests\fixtures\NewRelicFixtures;
use App\tests\fixtures\ProjectFixtures;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;

class GitlabControllerTest extends AbstractControllerCase
{
    private GitlabService $gitlabService;
    private NewRelicService $newRelicService;
    private GitlabController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->newRelicService = $this->createMock(NewRelicService::class);

        $this->controller = new GitlabController(
            $this->gitlabService,
            $this->newRelicService,
            self::$appConfig,
            self::$loggerFactory
        );

        $_REQUEST = [];
    }

    public static function handleSimpleRequestProvider(): array
    {
        return [
            'ACTION_GITLAB_SCAN success' => [
                'action' => ACTION_GITLAB_SCAN,
                'requestParams' => [],
                'serviceMethod' => 'scan',
                'serviceArgs' => [],
                'serviceReturn' => ['status' => 'success'],
                'expectedResponse' => json_encode(['status' => 'success']),
            ],
            'ACTION_GITLAB_TREE success' => [
                'action' => ACTION_GITLAB_TREE,
                'requestParams' => ['path' => 'src/'],
                'serviceMethod' => 'getTree',
                'serviceArgs' => [656, 'src/'],
                'serviceReturn' => [['name' => 'file1.txt']],
                'expectedResponse' => json_encode([['name' => 'file1.txt']]),
            ],
            'ACTION_GITLAB_FILE success' => [
                'action' => ACTION_GITLAB_FILE,
                'requestParams' => ['file' => 'config.json'],
                'serviceMethod' => 'getFile',
                'serviceArgs' => [656, 'config.json'],
                'serviceReturn' => ['content' => 'file content'],
                'expectedResponse' => json_encode(['content' => 'file content']),
            ],
            'Unknown action' => [
                'action' => 'unknown_action',
                'requestParams' => [],
                'serviceMethod' => null,
                'serviceArgs' => [],
                'serviceReturn' => null,
                'expectedResponse' => json_encode(['error' => 'Action inconnue']),
            ],
        ];
    }

    #[DataProvider('handleSimpleRequestProvider')]
    final public function testHandleSimpleRequest(
        string $action,
        array $requestParams,
        ?string $serviceMethod,
        array $serviceArgs,
        mixed $serviceReturn,
        string $expectedResponse
    ): void {
        $_REQUEST = $requestParams;

        if ($serviceMethod) {
            $this->gitlabService->expects($this->once())
                ->method($serviceMethod)
                ->with(...$serviceArgs)
                ->willReturn($serviceReturn);
        } else {
            $this->gitlabService->expects($this->never())->method($this->anything());
        }

        $response = $this->controller->handleRequest($action);

        $this->assertEquals($expectedResponse, $response);
    }

    final public function testHandleRequestNewRelicUrlFromCache(): void
    {
        $project = ProjectFixtures::getProjectWithUrls();
        $_REQUEST = ['project' => $project->getName(), 'env' => 'rec'];

        $newRelicModel = NewRelicFixtures::getNewRelic();

        $this->newRelicService->expects($this->once())
            ->method('find')
            ->with($project->getName(), EnumEnvironment::REC)
            ->willReturn($newRelicModel);

        $this->gitlabService->expects($this->never())->method('getProjectByCode');
        $this->gitlabService->expects($this->never())->method('buildNewRelicUrl');

        $response = $this->controller->handleRequest(ACTION_NEW_RELIC_URL);

        $this->assertEquals(json_encode(['url' => $newRelicModel->getUrl()]), $response);
    }

    final public function testHandleRequestNewRelicUrlBuildNewUrl(): void
    {
        $project = ProjectFixtures::getProjectWithUrls();
        $_REQUEST = ['project' => $project->getName(), 'env' => 'prod'];
        $newUrl = 'http://new.url';

        $this->newRelicService->expects($this->once())
            ->method('find')
            ->with($project->getName(), EnumEnvironment::PROD)
            ->willReturn(null);

        $this->gitlabService->expects($this->once())
            ->method('getProjectByCode')
            ->with($project->getName())
            ->willReturn($project);

        $this->gitlabService->expects($this->once())
            ->method('buildNewRelicUrl')
            ->with($project, EnumEnvironment::PROD)
            ->willReturn($newUrl);

        $this->newRelicService->expects($this->once())->method('save');

        $response = $this->controller->handleRequest(ACTION_NEW_RELIC_URL);

        $this->assertEquals(json_encode(['url' => $newUrl]), $response);
    }

    final public function testHandleRequestNewRelicUrlNotFound(): void
    {
        $project = ProjectFixtures::getProjectWithUrls();
        $_REQUEST = ['project' => $project->getName(), 'env' => 'prod'];

        $this->newRelicService->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->gitlabService->expects($this->once())
            ->method('getProjectByCode')
            ->willReturn($project);

        $this->gitlabService->expects($this->once())
            ->method('buildNewRelicUrl')
            ->willReturn(null);

        $response = $this->controller->handleRequest(ACTION_NEW_RELIC_URL);

        $this->assertEquals(json_encode(['error' => 'URL non trouvée.']), $response);
        $this->assertEquals(404, http_response_code());
    }

    final public function testHandleRequestException(): void
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

    final public function testNewRelicUrlMissingParams(): void
    {
        $response = $this->controller->handleRequest(ACTION_NEW_RELIC_URL);
        $expectedResponse = json_encode(['error' => 'Les paramètres project et env sont requis.']);
        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals(400, http_response_code());
    }
}