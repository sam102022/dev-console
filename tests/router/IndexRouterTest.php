<?php
declare(strict_types=1);

namespace App\tests\router;

use App\controller\AuthController;
use App\controller\TagAdminController;
use App\controller\UserAdminController;
use App\controller\SettingsController;
use App\context\IndexContext;
use App\controller\GitlabController;
use App\controller\IndexController;
use App\controller\MonitoringController;
use App\controller\PostmanController;
use App\controller\RundeckController;
use App\exception\TechnicalException;
use App\router\IndexRouter;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;


class IndexRouterTest extends AbstractTestCase
{
    private IndexController $indexController;
    private GitlabController|MockObject $gitlabController;
    private MonitoringController|MockObject $monitoringController;
    private PostmanController|MockObject $postmanController;
    private RundeckController|MockObject $rundeckController;
    private AuthController|MockObject $authController;
    private UserAdminController|MockObject $userAdminController;
    private TagAdminController|MockObject $tagAdminController;
    private IndexContext|MockObject $indexContext;
    private SettingsController|MockObject $settingsController;
    private RepositoryService|MockObject $repositoryService;
    private IndexRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexController = $this->createMock(IndexController::class);
        $this->gitlabController = $this->createMock(GitlabController::class);
        $this->monitoringController = $this->createMock(MonitoringController::class);
        $this->postmanController = $this->createMock(PostmanController::class);
        $this->rundeckController = $this->createMock(RundeckController::class);
        $this->authController = $this->createMock(AuthController::class);
        $this->userAdminController = $this->createMock(UserAdminController::class);
        $this->tagAdminController = $this->createMock(TagAdminController::class);
        $this->settingsController = $this->createMock(SettingsController::class);
        $this->indexContext = $this->createMock(IndexContext::class);
        $this->indexContext->method('initMessages')->willReturn([]);
        $this->repositoryService = $this->createMock(RepositoryService::class);

        $this->router = $this->getMockBuilder(IndexRouter::class)
            ->setConstructorArgs([
                $this->indexController,
                $this->gitlabController,
                $this->monitoringController,
                $this->postmanController,
                $this->rundeckController,
                $this->authController,
                $this->userAdminController,
                $this->tagAdminController,
                $this->settingsController,
                $this->twigMocked,
                $this->indexContext,
                $this->repositoryService,
                self::$loggerFactory
            ])
            ->onlyMethods(['redirect', 'terminate'])
            ->getMock();

        $_SESSION = ['user_id' => 1, 'user_role' => 'ROLE_ADMIN', 'user_email' => 'admin@mdm.com'];
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
            'get datagrid rows' => [ACTION_GET_DATAGRID_ROWS, 'indexController', 'handleRequest'],
            'login submit' => [AuthController::ACTION_LOGIN_SUBMIT, 'authController', 'loginSubmit'],
            'forgot password submit' => [AuthController::ACTION_FORGOT_PASSWORD_SUBMIT, 'authController', 'forgotPasswordSubmit'],
            'reset password submit' => [AuthController::ACTION_RESET_PASSWORD_SUBMIT, 'authController', 'resetPasswordSubmit'],
            'save settings' => [SettingsController::ACTION_SAVE_SETTINGS, 'settingsController', 'save'],
            'create user' => [UserAdminController::ACTION_CREATE_USER, 'userAdminController', 'handleRequest'],
            'update user' => [UserAdminController::ACTION_UPDATE_USER, 'userAdminController', 'handleRequest'],
            'delete user' => [UserAdminController::ACTION_DELETE_USER, 'userAdminController', 'handleRequest'],
            'add project tag' => [TagAdminController::ACTION_ADD_PROJECT_TAG, 'tagAdminController', 'handleRequest'],
            'remove project tag' => [TagAdminController::ACTION_REMOVE_PROJECT_TAG, 'tagAdminController', 'handleRequest']
        ];
    }

    /**
     * @throws TechnicalException
     */
    #[DataProvider('actionProvider')]
    public function testDispatchAction(string $action, string $controllerName, string $methodName): void
    {
        $_REQUEST['action'] = $action;

        $this->{$controllerName}->expects($this->once())->method($methodName);

        $this->router->dispatch();
    }

    public static function pageProvider(): array
    {
        return [
            'monitoring page' => ['monitoring', 'monitoringController'],
            'postman page' => ['postman', 'postmanController'],
            'rundeck page' => ['rundeck', 'rundeckController'],
            'tags page' => ['tags', 'tagAdminController'],
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
    public function testDispatchSaveColumnsPrefsMonitoring(): void
    {
        $_REQUEST['action'] = ACTION_SAVE_COLUMNS_PREFS;
        $_REQUEST['page'] = 'monitoring';

        $this->monitoringController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_SAVE_COLUMNS_PREFS)
            ->willReturn('{"success":true}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true}', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchSaveColumnsPrefsRundeck(): void
    {
        $_REQUEST['action'] = ACTION_SAVE_COLUMNS_PREFS;
        $_REQUEST['page'] = 'rundeck';

        $this->rundeckController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_SAVE_COLUMNS_PREFS)
            ->willReturn('{"success":true}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true}', $output);
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

    /**
     * @throws TechnicalException
     */
    public function testDispatchGetDatagridRowsMonitoring(): void
    {
        $_REQUEST['action'] = ACTION_GET_DATAGRID_ROWS;
        $_REQUEST['page'] = 'monitoring';

        $this->monitoringController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_GET_DATAGRID_ROWS)
            ->willReturn('{"success":true}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true}', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchGetDatagridRowsRundeck(): void
    {
        $_REQUEST['action'] = ACTION_GET_DATAGRID_ROWS;
        $_REQUEST['page'] = 'rundeck';

        $this->rundeckController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_GET_DATAGRID_ROWS)
            ->willReturn('{"success":true}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true}', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testAutoLoginWithValidRememberMeCookie(): void
    {
        // Simulate NOT logged in
        $_SESSION = [];
        $_COOKIE['remember_me'] = 'valid_token_123';

        // Mock database returning valid user
        $user = [
            'id' => 42,
            'role' => 'ROLE_USER',
            'email' => 'user@mdm.com'
        ];
        $this->repositoryService->expects($this->once())
            ->method('findUserByRememberToken')
            ->with('valid_token_123')
            ->willReturn($user);

        // Expect the request to be routed to default index Controller since they are auto-logged in
        $this->indexController->expects($this->once())
            ->method('index')
            ->with($this->anything());

        $this->router->dispatch();

        // Check if session has been populated
        $this->assertEquals(42, $_SESSION['user_id']);
        $this->assertEquals('ROLE_USER', $_SESSION['user_role']);
        $this->assertEquals('user@mdm.com', $_SESSION['user_email']);
    }

    /**
     * @throws TechnicalException
     */
    public function testAutoLoginWithInvalidRememberMeCookieOnPublicPage(): void
    {
        // Simulate NOT logged in
        $_SESSION = [];
        $_COOKIE['remember_me'] = 'invalid_token_456';
        $_REQUEST['page'] = 'login';

        // Mock database returning null (user not found)
        $this->repositoryService->expects($this->once())
            ->method('findUserByRememberToken')
            ->with('invalid_token_456')
            ->willReturn(null);

        // Expect the request to be routed to the mocked login page
        $this->authController->expects($this->once())
            ->method('login');

        $this->router->dispatch();

        // Check if session remains empty
        $this->assertEmpty($_SESSION['user_id'] ?? null);
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchGetDatagridRowsUsers(): void
    {
        $_REQUEST['action'] = ACTION_GET_DATAGRID_ROWS;
        $_REQUEST['page'] = 'users';

        $this->userAdminController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_GET_DATAGRID_ROWS)
            ->willReturn('{"success":true,"html":"users_rows"}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true,"html":"users_rows"}', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchNonAdminAccessUsersBlocked(): void
    {
        // Logged in as ROLE_USER (non-admin)
        $_SESSION = ['user_id' => 42, 'user_role' => 'ROLE_USER', 'user_email' => 'user@mdm.com'];
        $_REQUEST['page'] = 'users';

        // Expect terminate method to be called to prevent execution and return 403
        $this->router->expects($this->once())
            ->method('terminate')
            ->with(403, '403 Forbidden');

        // These should never be called
        $this->userAdminController->expects($this->never())->method('index');

        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchNonLoggedInAccessBlocked(): void
    {
        // Unauthenticated visitor trying to access index
        $_SESSION = [];
        unset($_COOKIE['remember_me']);
        $_REQUEST['page'] = 'index';

        // Expect redirect to login page
        $this->router->expects($this->once())
            ->method('redirect')
            ->with('?page=login');

        // indexController should never be reached
        $this->indexController->expects($this->never())->method('index');

        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageLogin(): void
    {
        $_REQUEST['page'] = 'login';
        // Non logged-in users are allowed to hit public pages
        $_SESSION = [];

        $this->authController->expects($this->once())->method('login');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageLogout(): void
    {
        $_REQUEST['page'] = 'logout';

        $this->authController->expects($this->once())->method('logout');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageForgotPassword(): void
    {
        $_REQUEST['page'] = 'forgotPassword';
        $_SESSION = [];

        $this->authController->expects($this->once())->method('forgotPassword');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageResetPassword(): void
    {
        $_REQUEST['page'] = 'resetPassword';
        $_SESSION = [];

        $this->authController->expects($this->once())->method('resetPassword');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageSettings(): void
    {
        $_REQUEST['page'] = 'settings';

        $this->settingsController->expects($this->once())->method('index');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageUsers(): void
    {
        $_REQUEST['page'] = 'users';

        $this->userAdminController->expects($this->once())->method('index');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchPageTags(): void
    {
        $_REQUEST['page'] = 'tags';

        $this->tagAdminController->expects($this->once())->method('index');
        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchGetDatagridRowsTags(): void
    {
        $_REQUEST['action'] = ACTION_GET_DATAGRID_ROWS;
        $_REQUEST['page'] = 'tags';

        $this->tagAdminController->expects($this->once())
            ->method('handleRequest')
            ->with(ACTION_GET_DATAGRID_ROWS)
            ->willReturn('{"success":true,"html":"tags_rows"}');

        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        $this->assertEquals('{"success":true,"html":"tags_rows"}', $output);
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchNonAdminAccessTagsBlocked(): void
    {
        // Logged in as ROLE_USER (non-admin)
        $_SESSION = ['user_id' => 42, 'user_role' => 'ROLE_USER', 'user_email' => 'user@mdm.com'];
        $_REQUEST['page'] = 'tags';

        $this->router->expects($this->once())
            ->method('terminate')
            ->with(403, '403 Forbidden');

        $this->tagAdminController->expects($this->never())->method('index');

        $this->router->dispatch();
    }

    /**
     * @throws TechnicalException
     */
    public function testDispatchNonAdminAccessTagActionsBlocked(): void
    {
        // Logged in as ROLE_USER (non-admin)
        $_SESSION = ['user_id' => 42, 'user_role' => 'ROLE_USER', 'user_email' => 'user@mdm.com'];
        $_REQUEST['action'] = TagAdminController::ACTION_ADD_PROJECT_TAG;

        $this->router->expects($this->once())
            ->method('terminate')
            ->with(403, '403 Forbidden');

        $this->tagAdminController->expects($this->never())->method('handleRequest');

        $this->router->dispatch();
    }
}
