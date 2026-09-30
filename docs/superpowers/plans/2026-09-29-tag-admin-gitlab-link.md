# Plan d'implémentation : Lien GitLab sur le Nom du Projet dans la Gestion des Tags

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un lien cliquable vers le dépôt GitLab distant sur le nom du projet dans le tableau de gestion des tags (`/?page=tags`), avec icône GitLab, infobulle et troncature à 35 caractères, à l'identique de l'interface Monitoring.

**Architecture:** Enrichir le mapping des items dans `TagAdminController::getDatagridRows()` avec `webUrl`, sécuriser la valeur par défaut dans `Project` et `GitlabProject`, et adapter le template partiel `templates/common/_tags_rows.html.twig` pour générer le lien hypertexte avec ses attributs et icône.

**Tech Stack:** PHP 8.4, Twig 3, Bootstrap 4, FontAwesome 6, PHPUnit 10.

---

### Task 1: Sécurisation des Modèles `Project` et `GitlabProject`

**Files:**
- Modify: `src/model/Project.php:20`
- Modify: `src/model/GitlabProject.php:16`
- Test: `tests/model/ProjectTest.php` (ou exécution de `TagAdminControllerTest`)

- [ ] **Step 1: Vérifier le comportement actuel sur propriété non initialisée**

Vérifier que si un `new Project()` est instancié sans appeler `setWebUrl()`, un appel à `getWebUrl()` peut échouer sans valeur par défaut.

- [ ] **Step 2: Initialiser `$webUrl = ''` par défaut dans `Project.php` et `GitlabProject.php`**

Dans `src/model/Project.php` :
```php
    private string $webUrl = '';
```

Dans `src/model/GitlabProject.php` :
```php
    private string $webUrl = '';
```

- [ ] **Step 3: Exécuter les tests unitaires des modèles**

Run: `vendor\bin\phpunit tests/model/ProjectTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/model/Project.php src/model/GitlabProject.php
git commit -m "fix(model): initialisation par defaut de webUrl dans Project et GitlabProject"
```

---

### Task 2: Test unitaire et Template Twig `_tags_rows.html.twig`

**Files:**
- Modify: `templates/common/_tags_rows.html.twig:6-8`
- Modify: `tests/templates/TagAdminTemplateTest.php`

- [ ] **Step 1: Écrire le test unitaire pour le lien GitLab dans `tests/templates/TagAdminTemplateTest.php`**

Ajouter une méthode de test `testTagsRowsRendersGitlabLinkWhenWebUrlProvided()` qui teste :
1. Projet avec `webUrl` et nom de plus de 35 caractères :
   - Présence de `<td class="col-name">`
   - Présence de `<a href="https://gitlab.com/mdm/very-long-project-name-more-than-35-characters"`
   - Présence de `class="url-link text-decoration-none"`
   - Présence de `target="_blank"`
   - Présence de `title="Voir le projet Gitlab archivé very-long-project-name-more-than-35-characters"`
   - Présence de `<i class="fa-brands fa-gitlab text-warning"></i>`
   - Présence du nom tronqué : `very-long-project-name-more-than...`
2. Projet sans `webUrl` :
   - Rendu du nom simple sans balise `<a>`

```php
    public function testTagsRowsRendersGitlabLinkWhenWebUrlProvided(): void
    {
        $results = [
            [
                'name' => 'very-long-project-name-more-than-35-characters',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'archived' => true,
                'webUrl' => 'https://gitlab.com/mdm/very-long-project-name-more-than-35-characters',
                'tags' => ['tag1']
            ],
            [
                'name' => 'api-catalog',
                'domain' => 'pdv',
                'sf' => 'catalog',
                'archived' => false,
                'webUrl' => '',
                'tags' => []
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="col-name"', $html);
        $this->assertStringContainsString('https://gitlab.com/mdm/very-long-project-name-more-than-35-characters', $html);
        $this->assertStringContainsString('class="url-link text-decoration-none"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('title="Voir le projet Gitlab archivé very-long-project-name-more-than-35-characters"', $html);
        $this->assertStringContainsString('fa-brands fa-gitlab text-warning', $html);
        $this->assertStringContainsString('very-long-project-name-more-than...', $html);

        // Fallback sans lien pour le projet sans webUrl
        $this->assertStringContainsString('api-catalog', $html);
        $this->assertStringNotContainsString('href=""', $html);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec initial (TDD)**

Run: `vendor\bin\phpunit tests/templates/TagAdminTemplateTest.php --filter testTagsRowsRendersGitlabLinkWhenWebUrlProvided`
Expected: FAIL (assertion `assertStringContainsString('class="col-name"', ...)` ou url GitLab échoue).

- [ ] **Step 3: Mettre à jour `templates/common/_tags_rows.html.twig`**

Remplacer la cellule `<td><strong>{{ r.name }}</strong></td>` par :
```twig
        <td class="col-name">
            {% set projectName = r.name|length > 35
                ? r.name|slice(0, 35) ~ '...'
                : r.name
            %}
            {% if r.webUrl is defined and r.webUrl != '' %}
                <a href="{{ r.webUrl }}"
                   class="url-link text-decoration-none"
                   target="_blank"
                   title="Voir le projet Gitlab{% if r.archived %} archivé{% endif %} {{ r.name }}">
                    <i class="fa-brands fa-gitlab text-warning"></i> {{ projectName }}
                </a>
            {% else %}
                {{ projectName }}
            {% endif %}
        </td>
```

- [ ] **Step 4: Exécuter à nouveau les tests de templates**

Run: `vendor\bin\phpunit tests/templates/TagAdminTemplateTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add templates/common/_tags_rows.html.twig tests/templates/TagAdminTemplateTest.php
git commit -m "feat(tags): affichage du lien gitlab sur le nom du projet dans _tags_rows"
```

---

### Task 3: Contrôleur `TagAdminController` et Injection de `webUrl`

**Files:**
- Modify: `src/controller/TagAdminController.php:104-114`
- Modify: `tests/controller/TagAdminControllerTest.php`

- [ ] **Step 1: Écrire le test unitaire dans `tests/controller/TagAdminControllerTest.php`**

Ajouter une méthode de test vérifiant que `webUrl` est extrait de chaque `Project` et fourni à Twig dans `getDatagridRows()` :
```php
    public function testGetDatagridRowsPassesWebUrlToTwig(): void
    {
        $project = new Project();
        $project->setName('api-orders');
        $project->setDomain('pdv');
        $project->setSf('buyers');
        $project->setWebUrl('https://gitlab.com/mdm/api-orders');

        $this->gitlabService->method('scan')->willReturn([$project]);
        $this->repositoryService->method('getTagsByProject')->willReturn([]);

        $renderedContext = [];
        $this->mockTwig->expects($this->once())
            ->method('render')
            ->with(
                'common/_tags_rows.html.twig',
                $this->callback(function (array $context) use (&$renderedContext) {
                    $renderedContext = $context;
                    return true;
                })
            )
            ->willReturn('<tr>rendered rows</tr>');

        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertNotEmpty($renderedContext['results']);
        $this->assertEquals('https://gitlab.com/mdm/api-orders', $renderedContext['results'][0]['webUrl']);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec (TDD)**

Run: `vendor\bin\phpunit tests/controller/TagAdminControllerTest.php --filter testGetDatagridRowsPassesWebUrlToTwig`
Expected: FAIL (`$renderedContext['results'][0]['webUrl']` est indéfini ou null).

- [ ] **Step 3: Mettre à jour `TagAdminController::getDatagridRows()`**

Dans `src/controller/TagAdminController.php` :
```php
        foreach ($projects as $project) {
            $projectName = $project->getName();
            $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
            $items[] = [
                'name' => $projectName,
                'domain' => $project->getDomain() ?? '',
                'sf' => $project->getSf() ?? '',
                'archived' => $project->isArchived(),
                'webUrl' => $project->getWebUrl(),
                'tags' => array_values($tags)
            ];
        }
```

- [ ] **Step 4: Exécuter les tests du contrôleur**

Run: `vendor\bin\phpunit tests/controller/TagAdminControllerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/controller/TagAdminController.php tests/controller/TagAdminControllerTest.php
git commit -m "feat(tags): inclusion de webUrl dans les donnees du datagrid de TagAdminController"
```

---

### Task 4: Validation Complète & Non-Régression

**Files:**
- Test: ensemble de la suite de tests

- [ ] **Step 1: Exécuter la suite complète des tests PHPUnit**

Run: `vendor\bin\phpunit`
Expected: PASS (tous les tests passent, aucune régression).

- [ ] **Step 2: Vérifier le statut Git**

Run: `git status`
Expected: Arbre de travail propre.
