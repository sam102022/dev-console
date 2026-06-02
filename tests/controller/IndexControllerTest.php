<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\context\IndexContext;
use App\controller\IndexController;
use App\exception\TechnicalException;
use App\service\GitlabService;
use App\viewModel\IndexViewModelFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class IndexControllerTest extends AbstractControllerCase
{
    private IndexViewModelFactory $viewModelFactory;
    private IndexContext $context;
    private GitlabService $gitlabService;
    private IndexController $controller;

    final protected function setUp(): void
    {
        parent::setUp();
        $this->viewModelFactory = $this->createMock(IndexViewModelFactory::class);
        $this->context = $this->createMock(IndexContext::class);
        $this->gitlabService = $this->createMock(GitlabService::class);

        $this->controller = new IndexController(
            $this->viewModelFactory,
            $this->context,
            $this->gitlabService,
            $this->twigMocked,
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
            ->with('index.html.twig', $this->callback(function ($subject) use ($viewModel) {
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
}
