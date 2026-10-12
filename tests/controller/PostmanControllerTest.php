<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\PostmanController;
use App\exception\TechnicalException;
use App\service\PostmanService;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use App\viewModel\IndexViewModelFactory;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PostmanControllerTest extends AbstractTestCase
{
    private IndexViewModelFactory $viewModelFactory;
    private PostmanService $postmanService;
    private IndexContext $context;
    private RepositoryService $repositoryService;
    private PostmanController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(IndexViewModelFactory::class);
        $this->postmanService = $this->createMock(PostmanService::class);
        $this->context = $this->createMock(IndexContext::class);
        $this->repositoryService = $this->createMock(RepositoryService::class);

        $this->controller = new PostmanController(
            $this->viewModelFactory,
            $this->postmanService,
            $this->context,
            $this->twigMocked,
            $this->repositoryService,
            self::$loggerFactory
        );

        $_REQUEST = [];
        $_GET = [];
        $_SESSION = [];
    }

    final public function testIndex(): void
    {
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'email' => 'user@mdm.com',
            'postman_api_key' => 'PMAK-123',
        ]);

        $messages = ['some_message'];
        $viewModel = ['viewModelKey' => 'viewModelValue'];

        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $messages)
            ->willReturn($viewModel);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('postman.html.twig', $this->callback(function ($subject) {
                $this->assertEquals('viewModelValue', $subject['viewModelKey']);
                $this->assertEquals('postman', $subject['current_route']);
                $this->assertTrue($subject['has_postman_api_key']);
                return true;
            }))
            ->willReturn('rendered_html');

        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_html', $output);
    }

    final public function testIndexWithoutApiKey(): void
    {
        $_SESSION['user_id'] = 2;
        $this->repositoryService->method('findUserById')->with(2)->willReturn([
            'id' => 2,
            'email' => 'nokey@mdm.com',
            'postman_api_key' => null,
        ]);

        $this->viewModelFactory->method('build')->willReturn([]);
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('postman.html.twig', $this->callback(function ($subject) {
                $this->assertFalse($subject['has_postman_api_key']);
                return true;
            }))
            ->willReturn('');

        $this->controller->index([]);
    }

    final public function testHandleRequestBlockedWithoutApiKey(): void
    {
        $_SESSION['user_id'] = 2;
        $this->repositoryService->method('findUserById')->with(2)->willReturn([
            'id' => 2,
            'email' => 'nokey@mdm.com',
            'postman_api_key' => '',
        ]);

        $response = $this->controller->handleRequest(ACTION_POSTMAN_WORKSPACES);

        $this->assertEquals(403, http_response_code());
        $this->assertEquals(
            json_encode(['error' => 'Clé API Postman manquante. Veuillez la configurer dans Mon compte > Paramètres.']),
            $response
        );
    }

    final public function testHandleRequestFailsCreateEnvironmentWithoutWorkspace(): void
    {
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'postman_api_key' => 'PMAK-123',
        ]);

        $input = json_encode(['name' => 'Env Dev', 'workspaceId' => '']);
        $response = $this->controller->handleRequest(ACTION_POSTMAN_CREATE_ENVIRONMENT, $input);

        $this->assertEquals(400, http_response_code());
        $this->assertEquals(json_encode(['error' => 'Le workspace est obligatoire.']), $response);
    }

    final public function testHandleRequestFailsImportOpenApiWithoutWorkspace(): void
    {
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'postman_api_key' => 'PMAK-123',
        ]);

        $input = json_encode(['fileContent' => ['openapi' => '3.0.0'], 'workspaceId' => '']);
        $response = $this->controller->handleRequest(ACTION_POSTMAN_IMPORT_OPENAPI, $input);

        $this->assertEquals(400, http_response_code());
        $this->assertEquals(json_encode(['error' => 'Le workspace est obligatoire.']), $response);
    }

    public static function handleRequestProvider(): array
    {
        return [
            'get workspaces' => [
                'action' => ACTION_POSTMAN_WORKSPACES,
                'input' => [],
                'serviceMethod' => 'getWorkspaces',
                'serviceArgs' => [],
                'serviceReturn' => [['id' => 'ws1', 'name' => 'Workspace 1']],
                'expectedResponse' => json_encode([['id' => 'ws1', 'name' => 'Workspace 1']]),
                'expectedHttpCode' => 200,
            ],
            'create workspace' => [
                'action' => ACTION_POSTMAN_CREATE_WORKSPACE,
                'input' => ['name' => 'New WS', 'description' => 'Desc'],
                'serviceMethod' => 'createWorkspace',
                'serviceArgs' => ['New WS', 'Desc'],
                'serviceReturn' => ['id' => 'ws2', 'name' => 'New WS'],
                'expectedResponse' => json_encode(['id' => 'ws2', 'name' => 'New WS']),
                'expectedHttpCode' => 200,
            ],
            'create environment' => [
                'action' => ACTION_POSTMAN_CREATE_ENVIRONMENT,
                'input' => ['workspaceId' => 'ws1', 'name' => 'Dev Env', 'variables' => [['key' => 'url', 'value' => 'localhost']]],
                'serviceMethod' => 'createEnvironment',
                'serviceArgs' => ['ws1', 'Dev Env', [['key' => 'url', 'value' => 'localhost']]],
                'serviceReturn' => ['id' => 'env1', 'name' => 'Dev Env'],
                'expectedResponse' => json_encode(['id' => 'env1', 'name' => 'Dev Env']),
                'expectedHttpCode' => 200,
            ],
            'import openapi' => [
                'action' => ACTION_POSTMAN_IMPORT_OPENAPI,
                'input' => ['workspaceId' => 'ws1', 'fileContent' => ['{"openapi":"3.0.0"}']],
                'serviceMethod' => 'importOpenApi',
                'serviceArgs' => ['ws1', ['{"openapi":"3.0.0"}']],
                'serviceReturn' => ['collection' => ['id' => 'col1']],
                'expectedResponse' => json_encode(['collection' => ['id' => 'col1']]),
                'expectedHttpCode' => 200,
            ],
            'get workspace details' => [
                'action' => ACTION_POSTMAN_GET_WORKSPACE_DETAILS,
                'input' => ['id' => 'ws1'], // Simulating $_GET
                'serviceMethod' => 'getWorkspaceDetails',
                'serviceArgs' => ['ws1'],
                'serviceReturn' => ['id' => 'ws1', 'collections' => []],
                'expectedResponse' => json_encode(['id' => 'ws1', 'collections' => []]),
                'expectedHttpCode' => 200,
            ],
            'unknown action' => [
                'action' => 'unknown_action',
                'input' => [],
                'serviceMethod' => null,
                'serviceArgs' => [],
                'serviceReturn' => null,
                'expectedResponse' => json_encode(['error' => 'Action inconnue']),
                'expectedHttpCode' => 400,
            ],
        ];
    }

    #[DataProvider('handleRequestProvider')]
    final public function testHandleRequest(string $action, array $input, ?string $serviceMethod,
                                      array $serviceArgs, ?array $serviceReturn, string $expectedResponse, int $expectedHttpCode): void
    {
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'postman_api_key' => 'PMAK-123',
        ]);

        $rawInput = null;
        if ($action === ACTION_POSTMAN_GET_WORKSPACE_DETAILS) {
            $_GET['id'] = $input['id'] ?? '';
        } else {
            $rawInput = json_encode($input);
        }

        if ($serviceMethod) {
            $this->postmanService->expects($this->once())
                ->method($serviceMethod)
                ->with(...$serviceArgs)
                ->willReturn($serviceReturn);
        }

        $response = $this->controller->handleRequest($action, $rawInput);

        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals($expectedHttpCode, http_response_code());
    }

    final public function testHandleRequestException(): void
    {
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'postman_api_key' => 'PMAK-123',
        ]);

        $action = ACTION_POSTMAN_WORKSPACES;
        $errorMessage = 'Service exception';

        $this->postmanService->expects($this->once())
            ->method('getWorkspaces')
            ->willThrowException(new Exception($errorMessage));

        $response = $this->controller->handleRequest($action);

        $this->assertEquals(json_encode(['error' => $errorMessage]), $response);
        $this->assertEquals(500, http_response_code());
    }

    public static function renderExceptionProvider(): array
    {
        return [
            'Twig LoaderError' => [new LoaderError('Twig loader error')],
            'Twig RuntimeError' => [new RuntimeError('Twig runtime error')],
            'Twig SyntaxError' => [new SyntaxError('Twig syntax error')],
            'TechnicalException' => [new TechnicalException('Technical error')],
        ];
    }

    #[DataProvider('renderExceptionProvider')]
    final public function testRenderCatchesExceptions(Throwable $exception): void
    {
        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->willThrowException($exception);

        $this->twigMocked->expects($this->never())->method('render');

        ob_start();
        $this->controller->index([]);
        ob_end_clean();
    }
}
