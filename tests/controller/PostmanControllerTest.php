<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\PostmanController;
use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\PostmanService;
use App\viewModel\IndexViewModelFactory;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PostmanControllerTest extends AbstractControllerCase
{
    private IndexViewModelFactory $viewModelFactory;
    private PostmanService $postmanService;
    private IndexContext $context;
    private PostmanController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(IndexViewModelFactory::class);
        $this->postmanService = $this->createMock(PostmanService::class);
        $this->context = $this->createMock(IndexContext::class);

        $this->controller = new PostmanController(
            $this->viewModelFactory,
            $this->postmanService,
            $this->context,
            $this->twigMocked,
            self::$loggerFactory
        );

        $_REQUEST = [];
        $_GET = [];
    }

    public function testIndex(): void
    {
        $messages = ['some_message'];
        $viewModel = ['viewModelKey' => 'viewModelValue'];

        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $messages)
            ->willReturn($viewModel);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('postman.html.twig', $this->callback(function ($subject) use ($viewModel) {
                $this->assertEquals('viewModelValue', $subject['viewModelKey']);
                $this->assertEquals('postman', $subject['current_route']);
                return true;
            }))
            ->willReturn('rendered_html');

        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_html', $output);
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
    public function testHandleRequest(string $action, array $input, ?string $serviceMethod, array $serviceArgs, $serviceReturn, string $expectedResponse, int $expectedHttpCode): void
    {
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

    public function testHandleRequestException(): void
    {
        $action = ACTION_POSTMAN_WORKSPACES;
        $errorMessage = 'Service exception';

        $this->postmanService->expects($this->once())
            ->method('getWorkspaces')
            ->willThrowException(new \Exception($errorMessage));

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
    public function testRenderCatchesExceptions(Throwable $exception): void
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