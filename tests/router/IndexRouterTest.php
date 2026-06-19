<?php
declare(strict_types=1);

namespace App\tests\router;

use App\context\IndexContext;
use App\controller\GitlabController;
use App\controller\IndexController;
use App\controller\MonitoringController;
use App\controller\PostmanController;
use App\controller\RundeckController;
use App\exception\TechnicalException;
use App\router\IndexRouter;
use PHPUnit\Framework\Attributes\DataProvider;


class IndexRouterTest extends AbstractRouterCase
{
    private IndexController $indexController;
    private GitlabController $gitlabController;
    private MonitoringController $monitoringController;
    private PostmanController $postmanController;
    private RundeckController $rundeckController;
    private IndexContext $indexContext;
    private IndexRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexController = $this->createMock(IndexController::class);
        $this->gitlabController = $this->createMock(GitlabController::class);
        $this->monitoringController = $this->createMock(MonitoringController::class);
        $this->postmanController = $this->createMock(PostmanController::class);
        $this->rundeckController = $this->createMock(RundeckController::class);
        $this->indexContext = $this->createMock(IndexContext::class);
        $this->indexContext->method('initMessages')->willReturn([]);

        $this->router = new IndexRouter(
            $this->indexController,
            $this->gitlabController,
            $this->monitoringController,
            $this->postmanController,
            $this->rundeckController,
            $this->twigMocked,
            $this->indexContext,
            self::$loggerFactory
        );

        $_SESSION = [];
        $_REQUEST = [];
        $_GET = [];
    }

    public static function actionProvider(): array
    {
        return [
            'purge cache' => [ACTION_PURGE_CACHE, 'indexController', 'purgeCache'],
            'gitlab file' => [ACTION_GITLAB_FILE, 'gitlabController', 'handleRequest'],
            'gitlab scan' => [ACTION_GITLAB_SCAN, 'gitlabController', 'handleRequest'],
            'gitlab tree' => [ACTION_GITLAB_TREE, 'gitlabController', 'handleRequest'],
            'postman workspaces' => [ACTION_POSTMAN_WORKSPACES, 'postmanController', 'handleRequest'],
            'postman create workspace' => [ACTION_POSTMAN_CREATE_WORKSPACE, 'postmanController', 'handleRequest'],
            'postman create environment' => [ACTION_POSTMAN_CREATE_ENVIRONMENT, 'postmanController', 'handleRequest'],
            'postman import openapi' => [ACTION_POSTMAN_IMPORT_OPENAPI, 'postmanController', 'handleRequest'],
            'postman get workspace details' => [ACTION_POSTMAN_GET_WORKSPACE_DETAILS, 'postmanController', 'handleRequest'],
            'monitoring check one' => [ACTION_MONITORING_GET_DATA, 'monitoringController', 'handleRequest'],
        ];
    }

    /**
     * @throws TechnicalException
     */
    #[DataProvider('actionProvider')]
    public function testDispatchAction(string $action, string $controllerName, string $methodName): void
    {
        $_REQUEST['action'] = $action;

        $this->{$controllerName}->expects($this->once())->method($methodName)->with($this->anything());

        $this->router->dispatch();
    }

    public static function pageProvider(): array
    {
        return [
            'monitoring page' => ['monitoring', 'monitoringController'],
            'postman page' => ['postman', 'postmanController'],
            'default page' => ['any_other_page', 'indexController'],
        ];
    }

    /**
     * @throws TechnicalException
     */
    #[DataProvider('pageProvider')]
    public function testDispatchPage(string $page, string $controllerName): void
    {
        $_REQUEST['page'] = $page;

        $this->{$controllerName}->expects($this->once())->method('index')->with($this->anything());

        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchDefaultRoute(): void
    {
        $this->indexController->expects($this->once())->method('index')->with($this->anything());
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchNotFound(): void
    {
        $_REQUEST['action'] = 'unknown_action';

        $this->twigMocked->expects($this->once())->method('render')->willReturn('Error HTML');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals(404, http_response_code());
        $this->assertEquals('Error HTML', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testThemeHandling(): void
    {
        // Test default theme
        $this->router->dispatch();
        $this->assertEquals(THEME_DEFAULT, $_SESSION['theme']);

        // Test theme from GET parameter
        $_GET['theme'] = 'light';
        $this->router->dispatch();
        $this->assertEquals('light', $_SESSION['theme']);
    }
}
