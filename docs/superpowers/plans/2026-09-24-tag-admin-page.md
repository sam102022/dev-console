# Page d'Administration Dédiée pour la Gestion des Tags - Plan d'Implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Créer une page d'administration dédiée (`?page=tags`) pour gérer les tags des projets via une modale interactive, tout en nettoyant l'édition inline sur Index, Monitoring et Rundeck et en regroupant l'administration dans un menu déroulant navbar.

**Architecture:** 
- Un nouveau contrôleur `TagAdminController` (`src/controller/TagAdminController.php`) sécurisé par `ROLE_ADMIN` expose le rendu de la page d'administration et les actions API `getDatagridRows`, `addProjectTag` et `removeProjectTag`.
- Les vues existantes (`_index_rows.html.twig`, `_monitoring_rows.html.twig`, `_rundeck_rows.html.twig`) sont épurées de tout contrôle d'édition (`+` et `x`), conservant l'affichage des badges et le clic de filtrage.
- Une nouvelle vue `tags.html.twig` associée à son template partiel `_tags_rows.html.twig` et pilotée par un composant Alpine `tagsDatagrid()` offre un tableau paginé et une modale d'édition instantanée des tags alimentée par une `<datalist>`.
- La barre de navigation (`templates/base.html.twig`) regroupe "Utilisateurs" et "Gestion des tags" dans un menu déroulant "Administration".

**Tech Stack:** PHP 8.4, Twig 3, Alpine.js, Bootstrap 4, SQLite / RepositoryService, PHPUnit 11, PHPStan.

---

### Task 1: Nettoyage des templates de lignes et mise à jour des tests de templates

**Files:**
- Modify: `tests/templates/TagsRowTemplateTest.php`
- Modify: `templates/common/_index_rows.html.twig:18-38`
- Modify: `templates/common/_monitoring_rows.html.twig:28-48`
- Modify: `templates/common/_rundeck_rows.html.twig:23-43`

- [ ] **Step 1: Mettre à jour les tests unitaires de templates**

Dans `tests/templates/TagsRowTemplateTest.php`, modifier les assertions pour vérifier que les boutons d'édition inline (`add-tag-btn`, `delete-tag-btn`, `tag-input-container`) ne sont JAMAIS générés, même pour un utilisateur ayant le rôle `ROLE_ADMIN`, tout en confirmant que les badges de tags (`tag-badge`) et leurs noms sont bien présents :

```php
<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class TagsRowTemplateTest extends AbstractTestCase
{
    public function testIndexRowsRendersTagsWithoutInlineAdminControls(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'cloudGCP' => true,
                'springBoot' => '3.2.0',
                'java' => '21',
                'mdmWorkloadVersion' => '1.0.0',
                'webUrl' => 'https://gitlab.com/api-orders',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        // 1. As ROLE_ADMIN
        $htmlAdmin = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('paiement', $htmlAdmin);
        $this->assertStringContainsString('checkout', $htmlAdmin);
        $this->assertStringContainsString('tag-badge', $htmlAdmin);
        $this->assertStringNotContainsString('delete-tag-btn', $htmlAdmin);
        $this->assertStringNotContainsString('add-tag-btn', $htmlAdmin);
        $this->assertStringNotContainsString('tag-input-container', $htmlAdmin);

        // 2. As ROLE_USER (non admin)
        $htmlUser = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_USER']
        ]);

        $this->assertStringContainsString('paiement', $htmlUser);
        $this->assertStringContainsString('checkout', $htmlUser);
        $this->assertStringContainsString('tag-badge', $htmlUser);
        $this->assertStringNotContainsString('delete-tag-btn', $htmlUser);
        $this->assertStringNotContainsString('add-tag-btn', $htmlUser);
    }

    public function testMonitoringRowsRendersTagsWithoutInlineAdminControls(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'techno' => 'java',
                'archived' => false,
                'cloudGCP' => true,
                'webUrl' => 'https://gitlab.com/api-orders',
                'tags' => ['supervision', 'core'],
                'urlActuatorInfo' => [],
                'urlHealthCheck' => [],
                'urlLogs' => [],
                'urlFronts' => [],
                'urlPubsubs' => [],
                'urlsRundeck' => [],
                'urlsDeploymentGcp' => []
            ]
        ];

        $html = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('supervision', $html);
        $this->assertStringContainsString('core', $html);
        $this->assertStringContainsString('tag-badge', $html);
        $this->assertStringNotContainsString('delete-tag-btn', $html);
        $this->assertStringNotContainsString('add-tag-btn', $html);
    }

    public function testRundeckRowsRendersTagsWithoutInlineAdminControls(): void
    {
        $results = [
            [
                'name' => 'Batch Orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'techno' => 'java',
                'webUrl' => 'https://gitlab.com/batch-orders',
                'tags' => ['batch', 'nightly'],
                'urlsRundeck' => []
            ]
        ];

        $html = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $results,
            'offset' => 0,
            'session' => ['user_role' => 'ROLE_ADMIN']
        ]);

        $this->assertStringContainsString('batch', $html);
        $this->assertStringContainsString('nightly', $html);
        $this->assertStringContainsString('tag-badge', $html);
        $this->assertStringNotContainsString('delete-tag-btn', $html);
        $this->assertStringNotContainsString('add-tag-btn', $html);
    }
}
```

- [ ] **Step 2: Vérifier l'échec du test**

Exécuter : `./vendor/bin/phpunit tests/templates/TagsRowTemplateTest.php`
Résultat attendu : FAIL (Failed asserting that string does not contain "delete-tag-btn").

- [ ] **Step 3: Nettoyer les templates `_index_rows.html.twig`, `_monitoring_rows.html.twig`, `_rundeck_rows.html.twig`**

Dans `templates/common/_index_rows.html.twig` :
Remplacer le bloc `.project-tags` (lignes 19 à 37) par :
```twig
            <div class="project-tags mt-1 d-flex flex-wrap align-items-center" style="gap: 3px;" data-project="{{ r.name }}">
                {% for tag in r.tags|default([]) %}
                    <span class="badge badge-light border text-secondary tag-badge" style="cursor: pointer; font-size: 0.72rem; padding: 2px 5px;" onclick="filterByTag('{{ tag|escape('js') }}')" title="Filtrer par ce tag">
                        <i class="fa-solid fa-tag mr-1 text-muted" style="font-size: 0.65rem;"></i>{{ tag }}
                    </span>
                {% endfor %}
            </div>
```

Dans `templates/common/_monitoring_rows.html.twig` :
Remplacer le bloc `.project-tags` (lignes 29 à 47) par :
```twig
            <div class="project-tags mt-1 d-flex flex-wrap align-items-center" style="gap: 3px;" data-project="{{ r.name }}">
                {% for tag in r.tags|default([]) %}
                    <span class="badge badge-light border text-secondary tag-badge" style="cursor: pointer; font-size: 0.72rem; padding: 2px 5px;" onclick="filterByTag('{{ tag|escape('js') }}')" title="Filtrer par ce tag">
                        <i class="fa-solid fa-tag mr-1 text-muted" style="font-size: 0.65rem;"></i>{{ tag }}
                    </span>
                {% endfor %}
            </div>
```

Dans `templates/common/_rundeck_rows.html.twig` :
Remplacer le bloc `.project-tags` (lignes 24 à 42) par :
```twig
            <div class="project-tags mt-1 d-flex flex-wrap align-items-center" style="gap: 3px;" data-project="{{ r.name }}">
                {% for tag in r.tags|default([]) %}
                    <span class="badge badge-light border text-secondary tag-badge" style="cursor: pointer; font-size: 0.72rem; padding: 2px 5px;" onclick="filterByTag('{{ tag|escape('js') }}')" title="Filtrer par ce tag">
                        <i class="fa-solid fa-tag mr-1 text-muted" style="font-size: 0.65rem;"></i>{{ tag }}
                    </span>
                {% endfor %}
            </div>
```

- [ ] **Step 4: Vérifier que le test passe**

Exécuter : `./vendor/bin/phpunit tests/templates/TagsRowTemplateTest.php`
Résultat attendu : OK (3 tests, 12 assertions).

- [ ] **Step 5: Commit**

```bash
git add tests/templates/TagsRowTemplateTest.php templates/common/_index_rows.html.twig templates/common/_monitoring_rows.html.twig templates/common/_rundeck_rows.html.twig
git commit -m "refactor: retrait de l'edition inline des tags sur les templates index, monitoring et rundeck"
```

---

### Task 2: Filtrage explicite par tag dans `DatagridHelper` et tests unitaires

**Files:**
- Modify: `src/util/DatagridHelper.php:40-60`
- Test: `tests/util/DatagridHelperTest.php`

- [ ] **Step 1: Écrire le test unitaire pour le filtre `tag`**

Ajouter la méthode `testProcessExplicitTagFilter` dans `tests/util/DatagridHelperTest.php` :

```php
    public function testProcessExplicitTagFilter(): void
    {
        $items = [
            [
                'name' => 'api-orders',
                'tags' => ['paiement', 'checkout']
            ],
            [
                'name' => 'flow-billing',
                'tags' => ['facturation', 'paiement']
            ],
            [
                'name' => 'batch-customers',
                'tags' => ['client']
            ],
            [
                'name' => 'integ-partners',
                'tags' => []
            ]
        ];

        // 1. Filtrer par tag direct "checkout"
        $resultCheckout = DatagridHelper::process($items, ['tag' => 'checkout'], 'name', 'asc', 1, 10);
        $this->assertEquals(1, $resultCheckout['totalRows']);
        $this->assertEquals('api-orders', $resultCheckout['items'][0]['name']);

        // 2. Filtrer par tag "paiement" (doit retourner api-orders et flow-billing)
        $resultPaiement = DatagridHelper::process($items, ['tag' => 'paiement'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultPaiement['totalRows']);

        // 3. Filtrer par tag partiel / casse différente ("PAIE")
        $resultPaie = DatagridHelper::process($items, ['tag' => 'PAIE'], 'name', 'asc', 1, 10);
        $this->assertEquals(2, $resultPaie['totalRows']);

        // 4. Filtrer par tag inexistant
        $resultNone = DatagridHelper::process($items, ['tag' => 'inexistant'], 'name', 'asc', 1, 10);
        $this->assertEquals(0, $resultNone['totalRows']);
        $this->assertEmpty($resultNone['items']);
    }
```

- [ ] **Step 2: Vérifier l'échec du test**

Exécuter : `./vendor/bin/phpunit --filter testProcessExplicitTagFilter tests/util/DatagridHelperTest.php`
Résultat attendu : FAIL (Failed asserting that 0 matches expected 1).

- [ ] **Step 3: Implémenter le filtre `tag` dans `DatagridHelper`**

Dans `src/util/DatagridHelper.php`, ajouter la gestion de la clé `'tag'` dans la boucle de filtrage (juste après la gestion de `'name'`) :

```php
                if ($key === 'tag') {
                    $tags = self::getPropertyValue($item, 'tags');
                    if (!is_array($tags)) {
                        return false;
                    }
                    $matchTag = false;
                    foreach ($tags as $t) {
                        if (stripos((string)$t, (string)$val) !== false) {
                            $matchTag = true;
                            break;
                        }
                    }
                    if (!$matchTag) {
                        return false;
                    }
                    continue;
                }
```

- [ ] **Step 4: Vérifier que le test passe**

Exécuter : `./vendor/bin/phpunit --filter testProcessExplicitTagFilter tests/util/DatagridHelperTest.php`
Résultat attendu : OK (1 test, 6 assertions).
Exécuter tous les tests de `DatagridHelperTest` :
`./vendor/bin/phpunit tests/util/DatagridHelperTest.php`
Résultat attendu : OK.

- [ ] **Step 5: Commit**

```bash
git add src/util/DatagridHelper.php tests/util/DatagridHelperTest.php
git commit -m "feat: ajout du support du filtre par tag dedie dans DatagridHelper"
```

---

### Task 3: Création du contrôleur `TagAdminController` et tests unitaires

**Files:**
- Create: `src/controller/TagAdminController.php`
- Create: `tests/controller/TagAdminControllerTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour `TagAdminController`**

Créer `tests/controller/TagAdminControllerTest.php` :

```php
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
    private Environment|MockObject $twig;
    private LoggerFactory|MockObject $loggerFactory;
    private TagAdminController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repositoryService = $this->createMock(RepositoryService::class);
        $this->gitlabService = $this->createMock(GitlabService::class);
        $this->twig = $this->createMock(Environment::class);
        
        $logger = new Logger('test', [new TestHandler()]);
        $this->loggerFactory = $this->createMock(LoggerFactory::class);
        $this->loggerFactory->method('get')->willReturn($logger);

        $this->controller = new TagAdminController(
            $this->repositoryService,
            $this->gitlabService,
            $this->twig,
            $this->loggerFactory
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

        $this->twig->expects($this->once())
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

        $this->twig->expects($this->once())
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

        $this->assertFalse($data['success']);
        $this->assertEquals('Accès réservé aux administrateurs.', $data['error']);
    }

    public function testAddProjectTagValidationFailure(): void
    {
        $_POST = ['projectName' => 'api-orders', 'tag' => ''];

        $response = $this->controller->handleRequest(TagAdminController::ACTION_ADD_PROJECT_TAG);
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Paramètres invalides.', $data['error']);
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

        $this->assertTrue($data['success']);
        $this->assertEquals(['paiement', 'nouveau-tag'], $data['tags']);
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

        $this->assertTrue($data['success']);
        $this->assertEquals([], $data['tags']);
    }

    public function testHandleRequestUnknownAction(): void
    {
        $response = $this->controller->handleRequest('unknownAction');
        $data = json_decode($response, true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Action inconnue', $data['error']);
    }
}
```

- [ ] **Step 2: Vérifier l'échec du test**

Exécuter : `./vendor/bin/phpunit tests/controller/TagAdminControllerTest.php`
Résultat attendu : FAIL (Class "App\controller\TagAdminController" not found).

- [ ] **Step 3: Implémenter `TagAdminController`**

Créer `src/controller/TagAdminController.php` :

```php
<?php
declare(strict_types=1);

namespace App\controller;

use App\exception\TechnicalException;
use App\factory\LoggerFactory;
use App\service\GitlabService;
use App\service\RepositoryService;
use App\util\DatagridHelper;
use App\util\UtilsLog;
use Monolog\Logger;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TagAdminController
{
    public const string ROUTE_TAGS = 'tags';
    public const string ACTION_GET_DATAGRID_ROWS = 'getDatagridRows';
    public const string ACTION_ADD_PROJECT_TAG = 'addProjectTag';
    public const string ACTION_REMOVE_PROJECT_TAG = 'removeProjectTag';

    private readonly Logger $logger;

    public function __construct(
        private readonly RepositoryService $repositoryService,
        private readonly GitlabService $gitlabService,
        private readonly Environment $twig,
        LoggerFactory $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(self::class);
    }

    /**
     * @param array<string, mixed> $messages
     */
    public function index(array &$messages): void
    {
        try {
            $viewModel = [];
            $viewModel['current_route'] = self::ROUTE_TAGS;
            $viewModel['allTags'] = $this->repositoryService->getAllTags();
            echo $this->twig->render('tags.html.twig', $viewModel);
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }

    public function handleRequest(string $action): string
    {
        switch ($action) {
            case self::ACTION_GET_DATAGRID_ROWS:
                return $this->getDatagridRows();
            case self::ACTION_ADD_PROJECT_TAG:
                return $this->addProjectTag();
            case self::ACTION_REMOVE_PROJECT_TAG:
                return $this->removeProjectTag();
            default:
                http_response_code(400);
                return json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
        }
    }

    private function getDatagridRows(): string
    {
        try {
            $projects = $this->gitlabService->scan() ?? [];
        } catch (TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $projects = [];
        }

        $tagsByProject = $this->repositoryService->getTagsByProject();

        $items = [];
        foreach ($projects as $project) {
            $projectName = $project->getName();
            $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
            $items[] = [
                'name' => $projectName,
                'domain' => $project->getDomain(),
                'tags' => array_values($tags)
            ];
        }

        $filters = [];
        foreach ($_REQUEST as $key => $val) {
            if (str_starts_with($key, 'filter_')) {
                $filters[str_replace('filter_', '', $key)] = $val;
            }
        }

        $sortCol = $_REQUEST['sort_column'] ?? 'name';
        if (empty($sortCol)) {
            $sortCol = 'name';
        }
        $sortDir = $_REQUEST['sort_dir'] ?? 'asc';
        $page = (int)($_REQUEST['p'] ?? 1);
        $limit = (int)($_REQUEST['rows_per_page'] ?? 15);
        if ($limit === 1000) {
            $limit = 999999;
        }

        $paginated = DatagridHelper::process($items, $filters, $sortCol, $sortDir, $page, $limit);

        try {
            $html = $this->twig->render('common/_tags_rows.html.twig', [
                'results' => $paginated['items'],
                'offset' => ($page - 1) * $limit
            ]);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $html = '';
        }

        return json_encode([
            'success' => true,
            'html' => $html,
            'totalRows' => $paginated['totalRows']
        ]);
    }

    private function addProjectTag(): string
    {
        if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
            http_response_code(403);
            return json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs.']);
        }

        $validated = $this->validateTagInput();
        if ($validated === null) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
        }

        $this->repositoryService->addProjectTag($validated['projectName'], $validated['tag']);
        $tags = $this->repositoryService->getTagsForProject($validated['projectName']);

        return json_encode(['success' => true, 'tags' => $tags]);
    }

    private function removeProjectTag(): string
    {
        if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
            http_response_code(403);
            return json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs.']);
        }

        $validated = $this->validateTagInput();
        if ($validated === null) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
        }

        $this->repositoryService->removeProjectTag($validated['projectName'], $validated['tag']);
        $tags = $this->repositoryService->getTagsForProject($validated['projectName']);

        return json_encode(['success' => true, 'tags' => $tags]);
    }

    /**
     * @return array{projectName: string, tag: string}|null
     */
    private function validateTagInput(): ?array
    {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input) || empty($input)) {
            $input = !empty($_POST) ? $_POST : $_REQUEST;
        }

        $projectName = trim((string)($input['projectName'] ?? ''));
        $tag = trim((string)($input['tag'] ?? ''));

        if ($projectName === '' || $tag === '') {
            return null;
        }

        if (mb_strlen($tag) < 3 || mb_strlen($tag) > 50) {
            return null;
        }

        return ['projectName' => $projectName, 'tag' => $tag];
    }
}
```

- [ ] **Step 4: Vérifier que le test passe**

Exécuter : `./vendor/bin/phpunit tests/controller/TagAdminControllerTest.php`
Résultat attendu : OK (7 tests, 16 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/controller/TagAdminController.php tests/controller/TagAdminControllerTest.php
git commit -m "feat: controleur TagAdminController avec actions getDatagridRows, addProjectTag et removeProjectTag"
```

---

### Task 4: Intégration du routage dans `IndexRouter` et mise à jour de la navbar

**Files:**
- Modify: `src/router/IndexRouter.php`
- Modify: `tests/router/IndexRouterTest.php`
- Modify: `templates/base.html.twig:110-125`

- [ ] **Step 1: Mettre à jour `tests/router/IndexRouterTest.php`**

Ajouter `TagAdminController` mocké dans `tests/router/IndexRouterTest.php` et tester l'accès/redirection pour `ROLE_ADMIN` vs `ROLE_USER` sur `ROUTE_TAGS` :

Dans `setUp()` :
- Ajouter `$this->tagAdminController = $this->createMock(TagAdminController::class);`
- Injecter `$this->tagAdminController` dans le constructeur de `IndexRouter`.
- Ajouter des cas de tests :
  - `testDispatchTagsPageAllowedForAdmin()`
  - `testDispatchTagsPageForbiddenForNonAdmin()`
  - `testDispatchTagsActionsRoutedToTagAdminController()`

- [ ] **Step 2: Vérifier l'échec des tests**

Exécuter : `./vendor/bin/phpunit tests/router/IndexRouterTest.php`
Résultat attendu : FAIL (argument count ou mocks mismatch).

- [ ] **Step 3: Modifier `IndexRouter.php` et `base.html.twig`**

Dans `src/router/IndexRouter.php` :
1. Importer `App\controller\TagAdminController`.
2. Ajouter `private readonly TagAdminController $tagAdminController` dans le constructeur.
3. Dans la vérification de permissions :
```php
        if ($isLoggedIn && $role !== 'ROLE_ADMIN') {
            if ($page === UserAdminController::ROUTE_USERS
                || $page === TagAdminController::ROUTE_TAGS
                || in_array($action, [
                    UserAdminController::ACTION_CREATE_USER,
                    UserAdminController::ACTION_UPDATE_USER,
                    UserAdminController::ACTION_DELETE_USER,
                    TagAdminController::ACTION_ADD_PROJECT_TAG,
                    TagAdminController::ACTION_REMOVE_PROJECT_TAG,
                ])
            ) {
                $this->terminate(403, '403 Forbidden');
                return;
            }
        }
```
4. Dans le switch `$action` :
```php
                case TagAdminController::ACTION_ADD_PROJECT_TAG:
                case TagAdminController::ACTION_REMOVE_PROJECT_TAG:
                    echo $this->tagAdminController->handleRequest($action);
                    return;
```
Et dans `ACTION_GET_DATAGRID_ROWS` :
```php
                        case TagAdminController::ROUTE_TAGS:
                            echo $this->tagAdminController->handleRequest($action);
                            break;
```
5. Dans le switch `$page` :
```php
                case TagAdminController::ROUTE_TAGS:
                    $this->tagAdminController->index($messages);
                    break;
```

Dans `templates/base.html.twig` :
Remplacer le lien unitaire "Utilisateurs" (lignes 112 à 121) par le menu déroulant Administration :
```twig
                    {% if session.user_role == 'ROLE_ADMIN' %}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {% if current_route is defined and (current_route == constant('App\\controller\\UserAdminController::ROUTE_USERS') or current_route == constant('App\\controller\\TagAdminController::ROUTE_TAGS')) %}active{% endif %}"
                               href="#" id="adminDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-tools mr-1"></i>
                                Administration
                            </a>
                            <div class="dropdown-menu" aria-labelledby="adminDropdown">
                                <a class="dropdown-item {% if current_route is defined and current_route == constant('App\\controller\\UserAdminController::ROUTE_USERS') %}active{% endif %}"
                                   href="?page={{ constant('App\\controller\\UserAdminController::ROUTE_USERS') }}">
                                    <i class="fas fa-users-cog mr-2"></i>Utilisateurs
                                </a>
                                <a class="dropdown-item {% if current_route is defined and current_route == constant('App\\controller\\TagAdminController::ROUTE_TAGS') %}active{% endif %}"
                                   href="?page={{ constant('App\\controller\\TagAdminController::ROUTE_TAGS') }}">
                                    <i class="fas fa-tags mr-2"></i>Gestion des tags
                                </a>
                            </div>
                        </li>
                    {% endif %}
```

- [ ] **Step 4: Vérifier que tous les tests router passent**

Exécuter : `./vendor/bin/phpunit tests/router/IndexRouterTest.php`
Résultat attendu : OK.

- [ ] **Step 5: Commit**

```bash
git add src/router/IndexRouter.php tests/router/IndexRouterTest.php templates/base.html.twig
git commit -m "feat: integration de la route tags et du menu deroulant Administration dans la navbar"
```

---

### Task 5: Templates `tags.html.twig`, `_tags_rows.html.twig` et composant Alpine

**Files:**
- Create: `templates/common/_tags_rows.html.twig`
- Create: `templates/tags.html.twig`
- Modify: `public/js/datagrid.js`
- Create: `tests/templates/TagAdminTemplateTest.php`

- [ ] **Step 1: Créer le template de lignes `templates/common/_tags_rows.html.twig`**

```twig
{% for r in results %}
    <tr class="project-row" data-project="{{ r.name }}">
        <td class="text-muted" style="width: 50px;">{{ offset + loop.index }}</td>
        <td>
            <strong>{{ r.name }}</strong>
            {% if r.domain is defined and r.domain %}
                <span class="badge badge-secondary ml-2" style="font-size: 0.72rem;">{{ r.domain }}</span>
            {% endif %}
        </td>
        <td>
            <div class="project-tags d-flex flex-wrap align-items-center" style="gap: 4px;" data-project="{{ r.name }}">
                {% for tag in r.tags|default([]) %}
                    <span class="badge badge-light border text-secondary" style="font-size: 0.75rem; padding: 3px 6px;">
                        <i class="fa-solid fa-tag mr-1 text-muted"></i>{{ tag }}
                    </span>
                {% else %}
                    <span class="text-muted font-italic small no-tags-placeholder">Aucun tag</span>
                {% endfor %}
            </div>
        </td>
        <td class="text-center" style="width: 160px;">
            <button type="button" class="btn btn-outline-primary btn-sm btn-manage-tags"
                    @click="openManageModal('{{ r.name|escape('js') }}', {{ (r.tags|default([]))|json_encode|e('html_attr') }})">
                <i class="fas fa-tags mr-1"></i> Gérer les tags
            </button>
        </td>
    </tr>
{% else %}
    <tr>
        <td colspan="4" class="text-center text-muted py-4">
            <i class="fas fa-info-circle mr-1"></i> Aucun projet trouvé.
        </td>
    </tr>
{% endfor %}
```

- [ ] **Step 2: Créer le template principal `templates/tags.html.twig`**

```twig
{% extends 'base.html.twig' %}

{% block title %}Gestion des Tags - MDM Dev Console{% endblock %}

{% block content %}
<style>
.tag-interactive-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.82rem;
    padding: 4px 8px;
    margin: 2px;
    border-radius: 4px;
}
.tag-interactive-badge .remove-icon {
    margin-left: 6px;
    cursor: pointer;
}
.tag-interactive-badge .remove-icon:hover {
    color: #dc3545 !important;
}
</style>

<div x-data="tagsDatagrid()">
    <div class="card shadow rounded mb-4" id="tags-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-tags mr-2" style="color: #17a2b8;"></i>Gestion des Tags</h5>
            <span class="badge badge-info">{{ allTags|length }} tag(s) existant(s)</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 text-nowrap" id="projects-table">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th><a class="sortable-header" @click.prevent="sortBy('name')">Projet <i :class="getSortIconClass('name')" :style="getSortIconStyle('name')"></i></a></th>
                            <th>Tags associés</th>
                            <th class="text-center" style="width: 160px;">Actions</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="filter_name" class="form-control filter-input" x-model="filters.name" @input.debounce.300ms="onFilterChange()" placeholder="Filtrer par projet..." aria-label="Filtrer par projet">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" @click="filters.name = ''; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="filter_tag" class="form-control filter-input" x-model="filters.tag" @input.debounce.300ms="onFilterChange()" placeholder="Filtrer par tag..." aria-label="Filtrer par tag">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" @click="filters.tag = ''; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <button class="btn btn-outline-light btn-sm btn-block" type="button" @click="resetFilters()" title="Réinitialiser les filtres"><i class="fa-solid fa-rotate-left"></i></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="projects-tbody" x-html="rowsHtml">
                        {% include 'common/_tags_rows.html.twig' with { results: [], offset: 0 } %}
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center">
            <div>
                <select id="rows_per_page" class="form-control form-control-sm d-inline-block w-auto" x-model="rowsPerPage" @change="currentPage = 1; fetchData()">
                    <option value="10">10 par page</option>
                    <option value="25">25 par page</option>
                    <option value="50">50 par page</option>
                    <option value="1000">Tous</option>
                </select>
                <span class="text-muted ml-2 small" x-text="totalRowsText"></span>
            </div>
            <div>
                <ul class="pagination pagination-sm mb-0" x-html="paginationHtml"></ul>
            </div>
        </div>
    </div>

    <!-- Modale de gestion des tags du projet sélectionné -->
    <div class="modal" :class="{ 'show': showModal }" style="background: rgba(0,0,0,0.5);" x-show="showModal" x-cloak>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tags text-info mr-2"></i>Gérer les tags : <strong class="text-primary" x-text="selectedProject"></strong>
                    </h5>
                    <button type="button" class="close" @click="closeModal()">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <template x-if="modalError">
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="fas fa-exclamation-circle mr-1"></i><span x-text="modalError"></span>
                        </div>
                    </template>
                    <template x-if="modalSuccess">
                        <div class="alert alert-success py-2 small mb-3">
                            <i class="fas fa-check-circle mr-1"></i><span x-text="modalSuccess"></span>
                        </div>
                    </template>

                    <label class="font-weight-bold small text-muted text-uppercase mb-2">Tags actuels du projet</label>
                    <div class="d-flex flex-wrap align-items-center mb-3 p-2 bg-light border rounded" style="min-height: 48px;">
                        <template x-for="tag in currentTags" :key="tag">
                            <span class="badge badge-primary tag-interactive-badge">
                                <i class="fa-solid fa-tag mr-1 text-light small"></i>
                                <span x-text="tag"></span>
                                <i class="fa-solid fa-xmark remove-icon text-light" @click="removeTag(tag)" title="Supprimer ce tag"></i>
                            </span>
                        </template>
                        <template x-if="currentTags.length === 0">
                            <span class="text-muted font-italic small">Aucun tag associé à ce projet.</span>
                        </template>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-muted text-uppercase">Ajouter un tag</label>
                        <div class="input-group">
                            <input type="text" class="form-control" list="existing-tags-datalist"
                                   x-model="newTagInput"
                                   @keyup.enter="addTag()"
                                   placeholder="Ex: paiement, batch, gcp...">
                            <datalist id="existing-tags-datalist">
                                {% for t in allTags|default([]) %}
                                    <option value="{{ t }}"></option>
                                {% endfor %}
                            </datalist>
                            <div class="input-group-append">
                                <button class="btn btn-success" type="button" @click="addTag()" :disabled="!newTagInput.trim()">
                                    <i class="fas fa-plus mr-1"></i> Ajouter
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Longueur de 3 à 50 caractères alphanumériques et tirets.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeModal()">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/datagrid.js?v=1.0.3"></script>
{% endblock %}
```

- [ ] **Step 3: Ajouter le composant `tagsDatagrid()` dans `public/js/datagrid.js`**

Dans `public/js/datagrid.js`, ajouter l'enregistrement de `tagsDatagrid` :

```javascript
function registerTagsDatagrid() {
    if (typeof window.datagrid !== 'function') {
        setTimeout(registerTagsDatagrid, 50);
        return;
    }
    Alpine.data('tagsDatagrid', () => {
        const base = window.datagrid({ pageName: 'tags' });
        return {
            ...base,
            showModal: false,
            selectedProject: '',
            currentTags: [],
            newTagInput: '',
            modalError: '',
            modalSuccess: '',

            openManageModal(projectName, tags) {
                this.selectedProject = projectName;
                this.currentTags = Array.isArray(tags) ? [...tags] : [];
                this.newTagInput = '';
                this.modalError = '';
                this.modalSuccess = '';
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
                this.fetchData();
            },

            async addTag() {
                const tag = this.newTagInput.trim();
                if (!tag) return;

                if (tag.length < 3 || tag.length > 50) {
                    this.modalError = 'Le tag doit contenir entre 3 et 50 caractères.';
                    return;
                }

                this.modalError = '';
                this.modalSuccess = '';

                try {
                    const response = await fetch('?page=tags&action=addProjectTag', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ projectName: this.selectedProject, tag })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.currentTags = data.tags;
                        this.newTagInput = '';
                        this.modalSuccess = `Tag "${tag}" ajouté avec succès.`;
                        setTimeout(() => { this.modalSuccess = ''; }, 3000);
                        this.updateRowTagsDom(this.selectedProject, this.currentTags);
                    } else {
                        this.modalError = data.error || 'Erreur lors de l\'ajout du tag';
                    }
                } catch (e) {
                    this.modalError = 'Erreur réseau lors de l\'ajout du tag.';
                }
            },

            async removeTag(tag) {
                this.modalError = '';
                this.modalSuccess = '';

                try {
                    const response = await fetch('?page=tags&action=removeProjectTag', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ projectName: this.selectedProject, tag })
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.currentTags = data.tags;
                        this.modalSuccess = `Tag "${tag}" supprimé.`;
                        setTimeout(() => { this.modalSuccess = ''; }, 3000);
                        this.updateRowTagsDom(this.selectedProject, this.currentTags);
                    } else {
                        this.modalError = data.error || 'Erreur lors de la suppression du tag';
                    }
                } catch (e) {
                    this.modalError = 'Erreur réseau lors de la suppression du tag.';
                }
            },

            updateRowTagsDom(projectName, tags) {
                const row = document.querySelector(`.project-row[data-project="${projectName}"]`);
                if (!row) return;

                const tagsContainer = row.querySelector('.project-tags');
                if (tagsContainer) {
                    if (!tags || tags.length === 0) {
                        tagsContainer.innerHTML = '<span class="text-muted font-italic small no-tags-placeholder">Aucun tag</span>';
                    } else {
                        tagsContainer.innerHTML = tags.map(t =>
                            `<span class="badge badge-light border text-secondary" style="font-size: 0.75rem; padding: 3px 6px;"><i class="fa-solid fa-tag mr-1 text-muted"></i>${t}</span>`
                        ).join(' ');
                    }
                }

                const btn = row.querySelector('.btn-manage-tags');
                if (btn) {
                    btn.setAttribute('@click', `openManageModal('${projectName}', ${JSON.stringify(tags)})`);
                }
            }
        };
    });
}

if (window.Alpine) {
    registerTagsDatagrid();
} else {
    document.addEventListener('alpine:init', registerTagsDatagrid);
}
```

- [ ] **Step 4: Écrire un test de rendu pour `TagAdminTemplateTest.php`**

Créer `tests/templates/TagAdminTemplateTest.php` :

```php
<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\controller\TagAdminController;
use App\controller\UserAdminController;
use App\tests\AbstractTestCase;

class TagAdminTemplateTest extends AbstractTestCase
{
    public function testBaseTemplateRendersAdminDropdownWhenAdmin(): void
    {
        $html = self::$twig->render('base.html.twig', [
            'current_route' => TagAdminController::ROUTE_TAGS,
            'session' => [
                'user_id' => 1,
                'user_role' => 'ROLE_ADMIN',
                'user_email' => 'admin@mdm.com'
            ]
        ]);

        $this->assertStringContainsString('Administration', $html);
        $this->assertStringContainsString('?page=' . UserAdminController::ROUTE_USERS, $html);
        $this->assertStringContainsString('?page=' . TagAdminController::ROUTE_TAGS, $html);
        $this->assertStringContainsString('Gestion des tags', $html);
    }

    public function testTagsRowsRendersManageButtonAndTags(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringContainsString('api-orders', $html);
        $this->assertStringContainsString('paiement', $html);
        $this->assertStringContainsString('checkout', $html);
        $this->assertStringContainsString('btn-manage-tags', $html);
        $this->assertStringContainsString('Gérer les tags', $html);
    }
}
```

- [ ] **Step 5: Vérifier que le test passe**

Exécuter : `./vendor/bin/phpunit tests/templates/TagAdminTemplateTest.php`
Résultat attendu : OK (2 tests, 9 assertions).

- [ ] **Step 6: Commit**

```bash
git add templates/tags.html.twig templates/common/_tags_rows.html.twig public/js/datagrid.js tests/templates/TagAdminTemplateTest.php
git commit -m "feat: page d'administration des tags avec composant Alpine et modale d'edition instantanee"
```

---

### Task 6: Validation globale et non-régression

**Files:**
- Verification only

- [ ] **Step 1: Exécuter la suite complète de tests PHPUnit**

Exécuter : `./vendor/bin/phpunit`
Résultat attendu : Tous les tests passent (0 failures, 0 errors).

- [ ] **Step 2: Exécuter l'analyse statique PHPStan**

Exécuter : `./vendor/bin/phpstan analyse --no-progress`
Résultat attendu : [OK] No errors.

- [ ] **Step 3: Vérifier le statut git**

Exécuter : `git status -s`
Résultat attendu : Workspace propre, aucune modification non trackée ou orpheline.
