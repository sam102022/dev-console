# Postman API Key Lock & Mandatory Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verrouiller l'interface Postman avec affichage d'un bandeau explicatif vers les paramètres quand l'utilisateur n'a pas configuré sa clé API, bloquer les requêtes côté serveur en 403, et rendre obligatoire la sélection d'un workspace pour la création d'environnement et l'import OpenAPI.

**Architecture:** Injecter `RepositoryService` dans `PostmanController` pour détecter la présence de `postman_api_key` pour l'utilisateur en session, transmettre l'état au template `postman.html.twig` pour afficher le bandeau et l'overlay bloquant, valider côté serveur dans `handleRequest` (403 si clé absente, 400 si workspace vide), et ajouter les validations côté client dans `postman.js`.

**Tech Stack:** PHP 8.4, Twig 3, JavaScript (Vanilla / Bootstrap), PHPUnit 11, SQLite PDO.

---

### Task 1: Contrôle d'accès et validation dans `PostmanController`

**Files:**
- Modify: `src/controller/PostmanController.php`
- Test: `tests/controller/PostmanControllerTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour `PostmanController`**

Dans `tests/controller/PostmanControllerTest.php` :
1. Ajouter la propriété `$repositoryService`:
```php
    private RepositoryService $repositoryService;
```
2. Dans `setUp()`:
```php
        $this->repositoryService = $this->createMock(RepositoryService::class);

        $this->controller = new PostmanController(
            $this->viewModelFactory,
            $this->postmanService,
            $this->context,
            $this->twigMocked,
            $this->repositoryService,
            self::$loggerFactory
        );
```
3. Mettre à jour `testIndex()` pour vérifier la présence de `has_postman_api_key`:
```php
        $_SESSION['user_id'] = 1;
        $this->repositoryService->method('findUserById')->with(1)->willReturn([
            'id' => 1,
            'email' => 'user@mdm.com',
            'postman_api_key' => 'PMAK-123',
        ]);
```
Et dans le callback Twig assert :
```php
        $this->assertTrue($subject['has_postman_api_key']);
```
4. Ajouter les tests spécifiques pour clé absente et validation workspace :
```php
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
```
5. Dans `handleRequestProvider()` : s'assurer que dans les tests nominaux, `$_SESSION['user_id'] = 1` et le mock retourne un utilisateur avec clé API valide.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter PostmanControllerTest`  
Expected: FAIL car le constructeur et la méthode `hasUserPostmanApiKey` ne sont pas encore adaptés.

- [ ] **Step 3: Mettre à jour `PostmanController.php`**

Dans `src/controller/PostmanController.php` :
1. Injecter `RepositoryService`:
```php
use App\service\RepositoryService;
...
    public function __construct(
        private readonly IndexViewModelFactory $viewModelFactory,
        private readonly PostmanService        $postmanService,
        private readonly IndexContext          $context,
        private readonly Environment           $twig,
        private readonly RepositoryService     $repositoryService,
        LoggerFactory                          $loggerFactory
    ) {
        $this->logger = $loggerFactory->get(self::class);
    }
```
2. Ajouter `hasUserPostmanApiKey()` :
```php
    private function hasUserPostmanApiKey(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId === null) {
            return false;
        }
        $user = $this->repositoryService->findUserById((int)$userId);
        return !empty(trim((string)($user['postman_api_key'] ?? '')));
    }
```
3. Dans `render()` :
```php
        try {
            $viewModel = $this->viewModelFactory->build($this->context, $messages);
            $viewModel['current_route'] = self::ROUTE_POSTMAN;
            $viewModel['has_postman_api_key'] = $this->hasUserPostmanApiKey();
            echo $this->twig->render(
                'postman.html.twig',
                $viewModel
            );
        }
```
4. Dans `handleRequest()` :
Au début de la méthode :
```php
        if (!$this->hasUserPostmanApiKey()) {
            http_response_code(403);
            return json_encode(['error' => 'Clé API Postman manquante. Veuillez la configurer dans Mon compte > Paramètres.']);
        }
```
Et dans le `switch ($action)` :
```php
                case ACTION_POSTMAN_CREATE_ENVIRONMENT:
                    $workspaceId = trim((string)($input['workspaceId'] ?? ''));
                    if ($workspaceId === '') {
                        http_response_code(400);
                        return json_encode(['error' => 'Le workspace est obligatoire.']);
                    }
                    $response = $this->postmanService->createEnvironment(
                        $workspaceId,
                        $input['name'] ?? '',
                        $input['variables'] ?? []
                    );
                    break;

                case ACTION_POSTMAN_IMPORT_OPENAPI:
                    $workspaceId = trim((string)($input['workspaceId'] ?? ''));
                    if ($workspaceId === '') {
                        http_response_code(400);
                        return json_encode(['error' => 'Le workspace est obligatoire.']);
                    }
                    $response = $this->postmanService->importOpenApi(
                        $workspaceId,
                        $input['fileContent'] ?? ''
                    );
                    break;
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter PostmanControllerTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add src/controller/PostmanController.php tests/controller/PostmanControllerTest.php; git commit -m "feat: securisation backend PostmanController avec controle cle api et workspace obligatoire"`

---

### Task 2: Interface Twig : bandeau d'alerte, overlay de verrouillage et sélecteurs de workspace

**Files:**
- Modify: `templates/postman.html.twig`

- [ ] **Step 1: Mettre à jour `templates/postman.html.twig`**

1. En haut de la carte, juste avant `<div class="card-body">` ou en début de `card-body`:
Si `not has_postman_api_key`, afficher le bandeau :
```twig
        {% if not has_postman_api_key %}
            <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4 shadow-sm">
                <div>
                    <i class="fas fa-lock text-warning mr-2 fa-lg"></i>
                    <strong>Clé API Postman manquante :</strong> Pour utiliser l'interface Postman, vous devez renseigner votre clé personnelle. Rendez-vous dans <strong>Mon compte &gt; Paramètres</strong>.
                </div>
                <a href="?page=settings" class="btn btn-warning btn-sm font-weight-bold ml-3 text-nowrap">
                    <i class="fas fa-user-cog mr-1"></i> Mon compte &gt; Paramètres
                </a>
            </div>
        {% endif %}
```

2. Englober les sections interactives dans un conteneur relatif avec l'overlay bloquant :
```twig
        <div class="position-relative">
            {% if not has_postman_api_key %}
                <div class="position-absolute w-100 h-100 d-flex flex-column align-items-center justify-content-center"
                     style="top: 0; left: 0; z-index: 100; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(1.5px); cursor: not-allowed;">
                    <div class="card p-3 shadow-sm text-center border-warning bg-white">
                        <i class="fas fa-lock fa-2x text-warning mb-2"></i>
                        <span class="font-weight-bold text-dark">Interface verrouillée</span>
                        <small class="text-muted">Configurez votre clé API pour débloquer les actions</small>
                    </div>
                </div>
            {% endif %}

            <!-- Reste du contenu (formulaires, import, liste workspaces) -->
        </div>
```

3. Mettre à jour les deux `<select>` :
Remplacer :
```html
<select id="workspaceIdEnv" class="form-control mb-3" placeholder="ID du workspace (optionnel)">
    <option value="">Aucun workspace</option>
</select>
```
Par :
```html
<select id="workspaceIdEnv" class="form-control mb-3" required>
    <option value="">Sélectionnez un workspace (obligatoire)</option>
</select>
```
Et pour `workspaceIdOpenApi` :
```html
<select id="workspaceIdOpenApi" class="form-control mb-3" required>
    <option value="">Sélectionnez un workspace (obligatoire)</option>
</select>
```

4. Dans la balise `<script>`, conditionner le chargement initial :
```twig
    {% if has_postman_api_key %}
    initWorkspacesList();
    // Charger la liste au démarrage
    loadWorkspaces();
    loadTree();
    {% endif %}
```

- [ ] **Step 2: Vérifier la syntaxe du template et les tests Twig**

Run: `vendor/bin/phpunit --filter PostmanControllerTest`  
Expected: PASS

- [ ] **Step 3: Commit**

Run: `git add templates/postman.html.twig; git commit -m "feat: bandeau alerte, overlay bloquant et options workspace obligatoire dans postman.html.twig"`

---

### Task 3: Validation JavaScript côté client dans `public/js/postman.js`

**Files:**
- Modify: `public/js/postman.js`

- [ ] **Step 1: Mettre à jour les validations dans `public/js/postman.js`**

1. Dans `beforeCreateEnvironment(btn)` :
Ajouter la vérification du workspace :
```javascript
    if (!workspaceId) {
        return showMessage("Veuillez sélectionner un workspace (obligatoire)", "warning");
    }
```
2. Dans `importOpenApi(btn)` :
Ajouter la vérification du workspace avant l'import :
```javascript
    if (!workspaceId) {
        return showMessage("Veuillez sélectionner un workspace (obligatoire)", "warning");
    }
```
3. Dans `initWorkspacesList()` :
Modifier le libellé par défaut :
```javascript
    ["workspaceIdEnv", "workspaceIdOpenApi"].forEach(id => {
        const list1 = document.getElementById(id);
        if (list1) {
            list1.innerHTML = `<option value="">Sélectionnez un workspace (obligatoire)</option>` + workspacesHtml;
        }
    });
```

- [ ] **Step 2: Vérifier l'absence d'erreurs de syntaxe JS**

Run: `node -c public/js/postman.js`  
Expected: (empty output, 0 errors)

- [ ] **Step 3: Commit**

Run: `git add public/js/postman.js; git commit -m "feat: validation obligatoire du workspace dans postman.js"`

---

### Task 4: Validation globale de non-régression

**Files:** Tous les fichiers modifiés

- [ ] **Step 1: Exécuter la suite complète de tests PHPUnit**

Run: `vendor/bin/phpunit`  
Expected: All tests pass (0 failures, 0 errors).

- [ ] **Step 2: Exécuter l'analyse statique PHPStan**

Run: `vendor/bin/phpstan analyse`  
Expected: [OK] No errors.

- [ ] **Step 3: Vérifier le statut git**

Run: `git status`  
Expected: Working tree clean (sur les fichiers trackés).
