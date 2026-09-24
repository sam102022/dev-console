<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\IndexController;
use App\exception\TechnicalException;
use App\service\GitlabService;
use App\service\NewRelicService;
use App\tests\AbstractTestCase;
use App\viewModel\IndexViewModelFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class IndexControllerTest extends AbstractTestCase
{
    private IndexViewModelFactory $viewModelFactory;
    private IndexContext $context;
    private GitlabService $gitlabService;
    private NewRelicService $newRelicService;
    private IndexController $controller;

    final protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(IndexViewModelFactory::class);
        $this->context = $this->createMock(IndexContext::class);
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->newRelicService = $this->createMock(NewRelicService::class);

        $this->controller = new IndexController(
            $this->viewModelFactory,
            $this->context,
            $this->gitlabService,
            $this->twigMocked,
            $this->newRelicService,
            self::$loggerFactory
        );
    }

    final public function testIndex(): void
    {
        $messages = ['some_message'];
        $scanResults = ['projects' => ['project1']];
        $viewModel = ['viewModelKey' => 'viewModelValue'];

        // Mock service and factory calls
        $this->gitlabService->expects($this->once())
            ->method('scan')
            ->willReturn($scanResults);

        $this->viewModelFactory->expects($this->once())
            ->method('setResults')
            ->with($scanResults);

        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $messages)
            ->willReturn($viewModel);

        // Expect twig to render
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('index.html.twig', $this->callback(function ($subject) {
                $this->assertArrayHasKey('viewModelKey', $subject);
                $this->assertEquals('viewModelValue', $subject['viewModelKey']);
                $this->assertArrayHasKey('current_route', $subject);
                $this->assertEquals('index', $subject['current_route']);
                return true;
            }))
            ->willReturn('rendered_html');

        // Capture output
        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_html', $output);
    }

    final public function testPurgeCache(): void
    {
        $initialMessages = [];
        $scanResults = ['projects' => ['refreshed_project']];
        $viewModel = ['viewModelKey' => 'refreshedValue'];

        // Expected messages after purging cache
        $expectedMessages = [
            MESSAGES_SCAN_RESULTS => [
                LEVEL_LOG_INFO => [
                    'Cache supprimé avec succès.'
                ]
            ]
        ];

        // Mock service calls
        $this->gitlabService->expects($this->once())
            ->method('purgeCache');
        $this->newRelicService->expects($this->once())
            ->method('purgeAll');

        $this->gitlabService->expects($this->once())
            ->method('scan')
            ->willReturn($scanResults);

        $this->viewModelFactory->expects($this->once())
            ->method('setResults')
            ->with($scanResults);

        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $expectedMessages)
            ->willReturn($viewModel);

        // Expect twig to render
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('index.html.twig', $this->callback(function ($subject) {
                $this->assertEquals('refreshedValue', $subject['viewModelKey']);
                return true;
            }))
            ->willReturn('rendered_html_after_purge');

        // Capture output
        ob_start();
        $this->controller->purgeCache($initialMessages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_html_after_purge', $output);
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
        $messages = [];

        // Configure the viewModelFactory to throw an exception
        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->willThrowException($exception);

        // Expect twig to never be called
        $this->twigMocked->expects($this->never())->method('render');

        // Run the method (output is not relevant here)
        ob_start();
        $this->controller->index($messages);
        ob_end_clean();
    }

    /**
     * Test index scan exception handling (parameterized)
     */
    public static function indexScanExceptionProvider(): array
    {
        // Define GuzzleException anonymous exception
        $guzzleException = new class('Guzzle connection timeout') extends \Exception implements \GuzzleHttp\Exception\GuzzleException {};

        return [
            'standard Exception' => [
                'exception' => new \Exception('Standard database error')
            ],
            'GuzzleException' => [
                'exception' => $guzzleException
            ]
        ];
    }

    #[DataProvider('indexScanExceptionProvider')]
    final public function testIndexScanException(\Throwable $exception): void
    {
        $messages = [];
        $expectedErrorMessages = [
            MESSAGES_SCAN_RESULTS => [
                LEVEL_LOG_ERROR => [
                    'Une erreur est survenue lors du scan des projets GitLab.'
                ]
            ]
        ];
        $viewModel = ['viewModelKey' => 'error_view_model'];

        // Mock scan() to throw the exception
        $this->gitlabService->expects($this->once())
            ->method('scan')
            ->willThrowException($exception);

        // Expect the view model to be built with error messages
        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $expectedErrorMessages)
            ->willReturn($viewModel);

        // Expect twig to render
        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('index.html.twig', $this->callback(function ($subject) {
                $this->assertEquals('error_view_model', $subject['viewModelKey']);
                return true;
            }))
            ->willReturn('rendered_error_html');

        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_error_html', $output);
    }

    /**
     * Test handleRequest with various actions and scenarios (parameterized)
     */
    public static function handleRequestProvider(): array
    {
        return [
            'getDatagridRows nominal success' => [
                'action' => 'getDatagridRows',
                'scanResults' => [
                    'projects' => [
                        ['id' => 1, 'name' => 'proj1', 'domain' => 'domain1', 'sf' => 'sf1', 'created_at' => '2026-09-15 10:00:00']
                    ]
                ],
                'viewModel' => [
                    'results' => [
                        ['id' => 1, 'name' => 'proj1', 'domain' => 'domain1', 'sf' => 'sf1', 'created_at' => '2026-09-15 10:00:00']
                    ]
                ],
                'requestParams' => [
                    'filter_domain' => 'domain1',
                    'sort_column' => 'created_at',
                    'sort_dir' => 'desc',
                    'p' => '1',
                    'rows_per_page' => '15'
                ],
                'shouldThrow' => null,
                'expectedResponse' => [
                    'success' => true,
                    'html' => 'rows_rendered_html',
                    'totalRows' => 1,
                    'allowedSfs' => ['sf1']
                ],
                'expectedTwigRender' => true
            ],
            'unknown action 400 error' => [
                'action' => 'non_existent_action',
                'scanResults' => [],
                'viewModel' => [],
                'requestParams' => [],
                'shouldThrow' => null,
                'expectedResponse' => [
                    'error' => 'Action inconnue'
                ],
                'expectedTwigRender' => false
            ],
            'throwable caught 500 error' => [
                'action' => 'getDatagridRows',
                'scanResults' => [],
                'viewModel' => [],
                'requestParams' => [],
                'shouldThrow' => new \Exception('Scan failure'),
                'expectedResponse' => [
                    'error' => 'Scan failure'
                ],
                'expectedTwigRender' => false
            ]
        ];
    }

    #[DataProvider('handleRequestProvider')]
    final public function testHandleRequest(
        string $action,
        array $scanResults,
        array $viewModel,
        array $requestParams,
        ?\Throwable $shouldThrow,
        array $expectedResponse,
        bool $expectedTwigRender
    ): void {
        $_REQUEST = $requestParams;

        if ($shouldThrow !== null) {
            $this->gitlabService->expects($this->once())
                ->method('scan')
                ->willThrowException($shouldThrow);
        } elseif ($action === 'getDatagridRows') {
            $this->gitlabService->expects($this->once())
                ->method('scan')
                ->willReturn($scanResults);

            $this->viewModelFactory->expects($this->once())
                ->method('setResults')
                ->with($scanResults);

            $this->viewModelFactory->expects($this->once())
                ->method('build')
                ->with($this->context, [])
                ->willReturn($viewModel);
        } else {
            $this->gitlabService->expects($this->never())->method('scan');
        }

        if ($expectedTwigRender) {
            $this->twigMocked->expects($this->once())
                ->method('render')
                ->with('common/_index_rows.html.twig', [
                    'results' => $viewModel['results'],
                    'offset' => 0
                ])
                ->willReturn('rows_rendered_html');
        } else {
            $this->twigMocked->expects($this->never())->method('render');
        }

        $responseJson = $this->controller->handleRequest($action);
        $response = json_decode($responseJson, true);

        $this->assertEquals($expectedResponse, $response);
    }

    final public function testHandleRequestAddProjectTagForbiddenForNonAdmin(): void
    {
        $_SESSION['user_role'] = 'ROLE_USER';
        $_POST = ['projectName' => 'api-orders', 'tag' => 'paiement'];

        $responseJson = $this->controller->handleRequest(ACTION_ADD_PROJECT_TAG);
        $response = json_decode($responseJson, true);

        $this->assertEquals(403, http_response_code());
        $this->assertFalse($response['success']);
        $this->assertEquals('Accès réservé aux administrateurs.', $response['error']);
    }

    final public function testHandleRequestAddProjectTagMissingParams(): void
    {
        $_SESSION['user_role'] = 'ROLE_ADMIN';
        $_POST = ['projectName' => '', 'tag' => ''];

        $responseJson = $this->controller->handleRequest(ACTION_ADD_PROJECT_TAG);
        $response = json_decode($responseJson, true);

        $this->assertEquals(400, http_response_code());
        $this->assertFalse($response['success']);
        $this->assertEquals('Paramètres manquants.', $response['error']);
    }

    final public function testHandleRequestAddProjectTagSuccessForAdmin(): void
    {
        $_SESSION['user_role'] = 'ROLE_ADMIN';
        $_POST = ['projectName' => 'api-orders', 'tag' => 'paiement'];

        $repoMock = $this->createMock(\App\service\RepositoryService::class);
        $repoMock->expects($this->once())
            ->method('addProjectTag')
            ->with('api-orders', 'paiement')
            ->willReturn(true);
        $repoMock->expects($this->once())
            ->method('getTagsForProject')
            ->with('api-orders')
            ->willReturn(['paiement']);

        $controller = new IndexController(
            $this->viewModelFactory,
            $this->context,
            $this->gitlabService,
            $this->twigMocked,
            $this->newRelicService,
            self::$loggerFactory,
            $repoMock
        );

        $responseJson = $controller->handleRequest(ACTION_ADD_PROJECT_TAG);
        $response = json_decode($responseJson, true);

        $this->assertTrue($response['success']);
        $this->assertEquals(['paiement'], $response['tags']);
    }

    final public function testHandleRequestRemoveProjectTagSuccessForAdmin(): void
    {
        $_SESSION['user_role'] = 'ROLE_ADMIN';
        $_POST = ['projectName' => 'api-orders', 'tag' => 'paiement'];

        $repoMock = $this->createMock(\App\service\RepositoryService::class);
        $repoMock->expects($this->once())
            ->method('removeProjectTag')
            ->with('api-orders', 'paiement')
            ->willReturn(true);
        $repoMock->expects($this->once())
            ->method('getTagsForProject')
            ->with('api-orders')
            ->willReturn([]);

        $controller = new IndexController(
            $this->viewModelFactory,
            $this->context,
            $this->gitlabService,
            $this->twigMocked,
            $this->newRelicService,
            self::$loggerFactory,
            $repoMock
        );

        $responseJson = $controller->handleRequest(ACTION_REMOVE_PROJECT_TAG);
        $response = json_decode($responseJson, true);

        $this->assertTrue($response['success']);
        $this->assertEquals([], $response['tags']);
    }
}

