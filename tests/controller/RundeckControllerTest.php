<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\RundeckController;
use App\exception\TechnicalException;
use App\service\RundeckService;
use App\service\UserPreferencesService;
use App\tests\AbstractTestCase;
use App\viewModel\RundeckViewModelFactory;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class RundeckControllerTest extends AbstractTestCase
{
    private RundeckViewModelFactory $viewModelFactory;
    private RundeckService $rundeckService;
    private UserPreferencesService $userPreferencesService;
    private IndexContext $context;
    private RundeckController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(RundeckViewModelFactory::class);
        $this->rundeckService = $this->createMock(RundeckService::class);
        $this->userPreferencesService = $this->createMock(UserPreferencesService::class);
        $this->context = $this->createMock(IndexContext::class);

        $this->controller = new RundeckController(
            $this->viewModelFactory,
            $this->rundeckService,
            $this->userPreferencesService,
            $this->context,
            $this->twigMocked,
            self::$loggerFactory
        );

        $_REQUEST = [];
    }

    final public function testIndex(): void
    {
        $messages = ['some_message'];
        $results = ['projects' => ['p1']];
        $viewModel = ['viewModelKey' => 'viewModelValue'];

        $this->rundeckService->expects($this->once())
            ->method('findAll')
            ->willReturn($results);

        $this->viewModelFactory->expects($this->once())
            ->method('setResults')
            ->with($results);

        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, $messages)
            ->willReturn($viewModel);

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('rundeck.html.twig', $this->callback(function ($subject) {
                $this->assertEquals('viewModelValue', $subject['viewModelKey']);
                $this->assertEquals('rundeck', $subject['current_route']);
                return true;
            }))
            ->willReturn('rendered_html');

        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('rendered_html', $output);
    }

    public static function handleSimpleRequestProvider(): array
    {
        return [
            'ACTION_SAVE_COLUMNS_PREFS success' => [
                'action' => ACTION_SAVE_COLUMNS_PREFS,
                'request' => [],
                'expectedResponse' => json_encode(['success' => true])
            ],
            'unknown action' => [
                'action' => 'unknown_action',
                'request' => [],
                'expectedResponse' => json_encode(['error' => 'Action inconnue'])
            ],
        ];
    }

    #[DataProvider('handleSimpleRequestProvider')]
    final public function testHandleSimpleRequest(string $action, array $request, string $expectedResponse): void
    {
        $_REQUEST = $request;

        if ($action === ACTION_SAVE_COLUMNS_PREFS) {
            $this->userPreferencesService->expects($this->once())
                ->method('set')
                ->with('rundeck_columns', []);
        }

        $response = $this->controller->handleRequest($action);

        $this->assertEquals($expectedResponse, $response);
    }

    final public function testHandleRequestException(): void
    {
        $action = ACTION_GET_DATAGRID_ROWS;
        $errorMessage = 'Rundeck Exception';

        $this->rundeckService->expects($this->once())
            ->method('findAll')
            ->willThrowException(new Exception($errorMessage));

        $response = $this->controller->handleRequest($action);

        $this->assertEquals(json_encode(['error' => $errorMessage]), $response);
        $this->assertEquals(500, http_response_code());
    }

    final public function testHandleRequestGetDatagridRows(): void
    {
        $results = ['projects' => ['rundeck_data']];
        $this->rundeckService->expects($this->once())
            ->method('findAll')
            ->willReturn($results);

        $this->viewModelFactory->expects($this->once())
            ->method('setResults')
            ->with($results);

        $viewModel = [
            'results' => [
                ['name' => 'Rundeck Job 1', 'domain' => 'domain-x', 'sf' => 'sf-x'],
                ['name' => 'Rundeck Job 2', 'domain' => 'domain-y', 'sf' => 'sf-y']
            ]
        ];
        $this->viewModelFactory->expects($this->once())
            ->method('build')
            ->with($this->context, [])
            ->willReturn($viewModel);

        $_REQUEST = [
            'filter_domain' => 'domain-x',
            'sort_column' => 'name',
            'sort_dir' => 'asc',
            'p' => '1',
            'rows_per_page' => '15'
        ];

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('common/_rundeck_rows.html.twig', $this->callback(function ($subject) {
                $this->assertCount(1, $subject['results']);
                $this->assertEquals('Rundeck Job 1', $subject['results'][0]['name']);
                $this->assertEquals(0, $subject['offset']);
                return true;
            }))
            ->willReturn('<tr><td>Rundeck Job 1</td></tr>');

        $response = $this->controller->handleRequest(ACTION_GET_DATAGRID_ROWS);

        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertEquals('<tr><td>Rundeck Job 1</td></tr>', $data['html']);
        $this->assertEquals(1, $data['totalRows']);
        $this->assertEquals(['sf-x'], $data['allowedSfs']);
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
