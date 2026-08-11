<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\MonitoringController;
use App\exception\TechnicalException;
use App\model\EnumEnvironment;
use App\service\GitlabService;
use App\service\MonitoringService;
use App\service\UserPreferencesService;
use App\tests\AbstractTestCase;
use App\viewModel\IndexViewModelFactory;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MonitoringControllerTest extends AbstractTestCase
{
    private IndexViewModelFactory $viewModelFactory;
    private GitlabService $gitlabService;
    private MonitoringService $monitoringService;
    private IndexContext $context;
    private MonitoringController $controller;

    final protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(IndexViewModelFactory::class);
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->monitoringService = $this->createMock(MonitoringService::class);
        $this->context = $this->createMock(IndexContext::class);
        $this->userPreferencesService = $this->createMock(UserPreferencesService::class);

        $this->controller = new MonitoringController(
            $this->viewModelFactory,
            $this->gitlabService,
            $this->monitoringService,
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
        $scanResults = ['projects' => ['project1']];
        $viewModel = ['viewModelKey' => 'viewModelValue'];

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

        $this->twigMocked->expects($this->once())
            ->method('render')
            ->with('monitoring.html.twig', $this->callback(function ($subject) {
                $this->assertEquals('viewModelValue', $subject['viewModelKey']);
                $this->assertEquals('monitoring', $subject['current_route']);
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
        $return1 = [
            'health' => ['status' => 'UP', 'httpCode' => 200, 'error' => null]
        ];
        return [
            'check one success' => [
                'action' => ACTION_MONITORING_GET_DATA,
                'request' => ['project' => 'my-project', 'env' => 'dev'],
                'serviceReturn' => $return1,
                'expectedResponse' => json_encode(['success' => true,
                    'health' => ['status' => 'UP', 'httpCode' => 200, 'error' => null]]),
            ],
            'check one failure' => [
                'action' => ACTION_MONITORING_GET_DATA,
                'request' => ['project' => 'my-project', 'env' => 'prod'],
                'serviceReturn' => [
                    'health' => ['status' => 'DOWN', 'httpCode' => 500, 'error' => 'Server Error'],
                ],
                'expectedResponse' => json_encode(['success' => true,
                    'health' => ['status' => 'DOWN', 'httpCode' => 500, 'error' => 'Server Error']]),
            ],
            'unknown action' => [
                'action' => 'unknown_action',
                'request' => [],
                'serviceReturn' => null,
                'expectedResponse' => json_encode(['error' => 'Action inconnue']),
            ],
        ];
    }

    #[DataProvider('handleRequestProvider')]
    final public function testHandleRequest(string $action, array $request, ?array $serviceReturn, string $expectedResponse): void
    {
        $_REQUEST = $request;

        if ($serviceReturn) {
            $this->monitoringService->expects($this->once())
                ->method('getMonitoringData')
                ->with($request['project'], EnumEnvironment::from($request['env']))
                ->willReturn($serviceReturn);
        }

        $response = $this->controller->handleRequest($action);

        $this->assertEquals($expectedResponse, $response);
    }

    final public function testHandleRequestException(): void
    {
        $action = ACTION_MONITORING_GET_DATA;
        $_REQUEST = ['project' => 'p', 'env' => 'dev'];
        $errorMessage = 'Service exception';

        $this->monitoringService->expects($this->once())
            ->method('getMonitoringData')
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

    /**
     * @throws TechnicalException
     */
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
