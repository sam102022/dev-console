<?php
declare(strict_types=1);

namespace App\tests\controller;

use App\controller\TagAdminController;
use App\factory\LoggerFactory;
use App\model\Project;
use App\service\GitlabService;
use App\service\RepositoryService;
use App\tests\AbstractTestCase;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Twig\Environment;

class TagAdminControllerTest extends AbstractTestCase
{
    private RepositoryService|MockObject $repositoryService;
    private GitlabService|MockObject $gitlabService;
    private Environment|MockObject $mockTwig;
    private LoggerFactory|MockObject $mockLoggerFactory;
    private TagAdminController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryService = $this->createMock(RepositoryService::class);
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->mockTwig = $this->createMock(Environment::class);
        
        $logger = new Logger('test', [new TestHandler()]);
        $this->mockLoggerFactory = $this->createMock(LoggerFactory::class);
        $this->mockLoggerFactory->method('get')->willReturn($logger);

        $this->controller = new TagAdminController(
            $this->repositoryService,
            $this->gitlabService,
            $this->mockTwig,
            $this->mockLoggerFactory
        );

        $_SESSION = ['user_id' => 1, 'user_role' => 'ROLE_ADMIN', 'user_email' => 'admin@mdm.com'];
        $_REQUEST = [];
        $_POST = [];
    }

    public function testIndexRendersTagsTemplateWithAllTags(): void
    {
        $this->repositoryService->expects($this->once())
            ->method('getAllTags')
            ->willReturn(['paiement', 'checkout', 'batch']);

        $this->mockTwig->expects($this->once())
            ->method('render')
            ->with(
                'tags.html.twig',
                $this->callback(function (array $context) {
                    return $context['current_route'] === TagAdminController::ROUTE_TAGS
                        && $context['allTags'] === ['paiement', 'checkout', 'batch'];
                })
            )
            ->willReturn('<html>Tags Admin</html>');

        $messages = [];
        ob_start();
        $this->controller->index($messages);
        $output = ob_get_clean();

        $this->assertEquals('<html>Tags Admin</html>', $output);
    }

    public function testIndexRendersTagsViewWithDomainsAndSfs(): void
    {
        $project1 = new Project();
        $project1->setName('api-orders');
        $project1->setDomain('pdv');
        $project1->setSf('buyers');

        $project2 = new Project();
        $project2->setName('flow-invoices');
        $project2->setDomain('finance');
        $project2->setSf('accounting');

        $this->gitlabService->method('scan')->willReturn([$project1, $project2]);
        $this->repositoryService->method('getAllTags')->willReturn(['paiement']);

        $renderedContext = [];
        $this->mockTwig->expects($this->once())
            ->method('render')
            ->with('tags.html.twig', $this->callback(function (array $context) use (&$renderedContext) {
                $renderedContext = $context;
                return true;
            }))
            ->willReturn('<html>Tags Page</html>');

        $messages = [];
        ob_start();
        $this->controller->index($messages);
        ob_end_clean();

        $this->assertArrayHasKey('domains', $renderedContext);
        $this->assertArrayHasKey('sfs', $renderedContext);
        $this->assertArrayHasKey('pdv', $renderedContext['domains']);
        $this->assertArrayHasKey('finance', $renderedContext['domains']);
        $this->assertArrayHasKey('buyers', $renderedContext['sfs']);
        $this->assertArrayHasKey('accounting', $renderedContext['sfs']);
    }

    public function testGetDatagridRowsReturnsDomainSfAndAllowedSfs(): void
    {
        $project1 = new Project();
        $project1->setName('api-orders');
        $project1->setDomain('pdv');
        $project1->setSf('buyers');

        $project2 = new Project();
        $project2->setName('flow-invoices');
        $project2->setDomain('finance');
        $project2->setSf('accounting');

        $this->gitlabService->method('scan')->willReturn([$project1, $project2]);
        $this->repositoryService->method('getTagsByProject')->willReturn([]);

        $this->mockTwig->method('render')->willReturn('<tr>rendered rows</tr>');

        $_REQUEST['filter_domain'] = 'pdv';

        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['totalRows']);
        $this->assertArrayHasKey('allowedSfs', $data);
        $this->assertEquals(['buyers'], $data['allowedSfs']);

        unset($_REQUEST['filter_domain']);
    }

    public function testGetDatagridRowsReturnsJsonWithHtmlAndTotal(): void
    {
        $project1 = new Project();
        $project1->setName('api-orders');
        $project1->setDomain('pdv');

        $project2 = new Project();
        $project2->setName('flow-billing');
        $project2->setDomain('finance');

        $this->gitlabService->expects($this->once())
            ->method('scan')
            ->willReturn([$project1, $project2]);

        $this->repositoryService->expects($this->once())
            ->method('getTagsByProject')
            ->willReturn([
                'api-orders' => ['paiement', 'checkout'],
                'flow-billing' => ['facturation']
            ]);

        $this->mockTwig->expects($this->once())
            ->method('render')
            ->with(
                'common/_tags_rows.html.twig',
                $this->callback(function (array $context) {
                    return count($context['results']) === 2
                        && $context['offset'] === 0
                        && $context['results'][0]['tags'] === ['paiement', 'checkout'];
                })
            )
            ->willReturn('<tr><td>Row</td></tr>');

        $_REQUEST['p'] = 1;
        $_REQUEST['rows_per_page'] = 10;

        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals('<tr><td>Row</td></tr>', $data['html']);
        $this->assertEquals(2, $data['totalRows']);
    }

    public function testAddProjectTagForbiddenForNonAdmin(): void
    {
        $_SESSION['user_role'] = 'ROLE_USER';

        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(403, http_response_code());
        $this->assertFalse($data['success']);
        $this->assertEquals('Accès réservé aux administrateurs.', $data['error']);
    }

    public function testAddProjectTagValidationFailure(): void
    {
        $_POST = ['projectName' => 'api-orders', 'tag' => ''];

        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(400, http_response_code());
        $this->assertFalse($data['success']);
        $this->assertEquals('Paramètres invalides.', $data['error']);
    }

    public function testAddProjectTagTooShortOrTooLong(): void
    {
        // < 3 chars
        $_POST = ['projectName' => 'api-orders', 'tag' => 'ab'];
        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $this->assertEquals(400, http_response_code());
        $this->assertFalse(json_decode($response, true)['success']);

        // > 50 chars
        $_POST = ['projectName' => 'api-orders', 'tag' => str_repeat('a', 51)];
        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $this->assertEquals(400, http_response_code());
        $this->assertFalse(json_decode($response, true)['success']);
    }

    public function testAddProjectTagSpecialCharactersForbidden(): void
    {
        $_POST = ['projectName' => 'api-orders', 'tag' => '<script>alert(1)</script>'];
        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $this->assertEquals(400, http_response_code());
        $this->assertFalse(json_decode($response, true)['success']);
    }

    public function testAddProjectTagSuccess(): void
    {
        $_POST = ['projectName' => 'api-orders', 'tag' => 'nouveau-tag'];

        $this->repositoryService->expects($this->once())
            ->method('addProjectTag')
            ->with('api-orders', 'nouveau-tag')
            ->willReturn(true);

        $this->repositoryService->expects($this->once())
            ->method('getTagsForProject')
            ->with('api-orders')
            ->willReturn(['paiement', 'nouveau-tag']);

        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(200, http_response_code());
        $this->assertTrue($data['success']);
        $this->assertEquals(['paiement', 'nouveau-tag'], $data['tags']);
    }

    public function testRemoveProjectTagForbiddenForNonAdmin(): void
    {
        $_SESSION['user_role'] = 'ROLE_USER';

        $response = $this->controller->handleRequest(TagAdminController::ACTION_REMOVE_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(403, http_response_code());
        $this->assertFalse($data['success']);
        $this->assertEquals('Accès réservé aux administrateurs.', $data['error']);
    }

    public function testRemoveProjectTagValidationFailure(): void
    {
        $_POST = ['projectName' => '', 'tag' => 'test'];

        $response = $this->controller->handleRequest(TagAdminController::ACTION_REMOVE_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(400, http_response_code());
        $this->assertFalse($data['success']);
        $this->assertEquals('Paramètres invalides.', $data['error']);
    }

    public function testRemoveProjectTagSuccess(): void
    {
        $_POST = ['projectName' => 'api-orders', 'tag' => 'paiement'];

        $this->repositoryService->expects($this->once())
            ->method('removeProjectTag')
            ->with('api-orders', 'paiement')
            ->willReturn(true);

        $this->repositoryService->expects($this->once())
            ->method('getTagsForProject')
            ->with('api-orders')
            ->willReturn([]);

        $response = $this->controller->handleRequest(TagAdminController::ACTION_REMOVE_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertEquals(200, http_response_code());
        $this->assertTrue($data['success']);
        $this->assertEquals([], $data['tags']);
    }

    public function testHandleRequestUnknownAction(): void
    {
        $response = $this->controller->handleRequest('unknownAction');
        $data = json_decode($response, true);

        $this->assertEquals(400, http_response_code());
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Action inconnue', $data['error']);
    }
}
