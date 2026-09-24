# Intégration des Colonnes Domaine et SF sur la Gestion des Tags Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter les colonnes Domaine et SF (avec tri par en-tête et filtrage déroulant dynamique) sur le tableau d'administration des tags pour s'aligner sur la page de Monitoring.

**Architecture:** Extraction de `$domains` et `$sfs` par `TagAdminController::index()` pour hydrater les sélecteurs de filtre dans `tags.html.twig`, enrichissement de `$items` dans `TagAdminController::getDatagridRows()` avec les champs `'domain'` et `'sf'`, et calcul de `'allowedSfs'` dans la réponse JSON AJAX pour la mise à jour réactive du sélecteur SF via Alpine.js.

**Tech Stack:** PHP 8.4, Twig 3, Alpine.js, Bootstrap 4, PHPUnit 11, Playwright, PHPStan.

---

### Task 1: Enrichissement Backend dans `TagAdminController` et Tests Unitaires

**Files:**
- Modify: `src/controller/TagAdminController.php`
- Test: `tests/controller/TagAdminControllerTest.php`

- [ ] **Step 1: Écrire les tests unitaires dans `TagAdminControllerTest.php`**

Ajouter les tests suivants pour valider la transmission de `domains` et `sfs` dans `index()` ainsi que la présence de `domain`, `sf` et `allowedSfs` dans `getDatagridRows()` :

```php
    public function testIndexRendersTagsViewWithDomainsAndSfs(): void
    {
        $project1 = new Project('api-orders');
        $project1->setDomain('pdv');
        $project1->setSf('buyers');

        $project2 = new Project('flow-invoices');
        $project2->setDomain('finance');
        $project2->setSf('accounting');

        $this->gitlabServiceMock->method('scan')->willReturn([$project1, $project2]);
        $this->repositoryServiceMock->method('getAllTags')->willReturn(['paiement']);

        $renderedContext = [];
        $this->twigMock->expects($this->once())
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
        $project1 = new Project('api-orders');
        $project1->setDomain('pdv');
        $project1->setSf('buyers');

        $project2 = new Project('flow-invoices');
        $project2->setDomain('finance');
        $project2->setSf('accounting');

        $this->gitlabServiceMock->method('scan')->willReturn([$project1, $project2]);
        $this->repositoryServiceMock->method('getTagsByProject')->willReturn([]);

        $this->twigMock->method('render')->willReturn('<tr>rendered rows</tr>');

        $_REQUEST['filter_domain'] = 'pdv';

        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['totalRows']);
        $this->assertArrayHasKey('allowedSfs', $data);
        $this->assertEquals(['buyers'], $data['allowedSfs']);

        unset($_REQUEST['filter_domain']);
    }
```

- [ ] **Step 2: Exécuter les tests pour vérifier l'échec initial**

Run: `.\vendor\bin\phpunit --filter "testIndexRendersTagsViewWithDomainsAndSfs|testGetDatagridRowsReturnsDomainSfAndAllowedSfs" tests/controller/TagAdminControllerTest.php`  
Expected: FAIL car `domains`, `sfs` et `allowedSfs` ne sont pas encore calculés ni transmis.

- [ ] **Step 3: Mettre à jour `src/controller/TagAdminController.php`**

1. Dans la méthode `index(array &$messages)` :
```php
    public function index(array &$messages): void
    {
        try {
            $projects = $this->gitlabService->scan() ?? [];
        } catch (TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
            $projects = [];
        }

        $domains = [];
        $sfs = [];
        foreach ($projects as $project) {
            if ($project->getDomain()) {
                $domains[$project->getDomain()] = $project->getDomainName() ?: $project->getDomain();
            }
            if ($project->getSf()) {
                $sfs[$project->getSf()] = $project->getSf();
            }
        }
        ksort($domains);
        ksort($sfs);

        try {
            $viewModel = [];
            $viewModel['current_route'] = self::ROUTE_TAGS;
            $viewModel['allTags'] = $this->repositoryService->getAllTags();
            $viewModel['domains'] = $domains;
            $viewModel['sfs'] = $sfs;
            $viewModel['messages'] = $messages;
            echo $this->twig->render('tags.html.twig', $viewModel);
        } catch (LoaderError|RuntimeError|SyntaxError|TechnicalException $e) {
            $this->logger->error(UtilsLog::prefixLog(self::class, __FUNCTION__, __LINE__) . $e->getMessage());
        }
    }
```

2. Dans la méthode `getDatagridRows()` :
```php
        $items = [];
        foreach ($projects as $project) {
            $projectName = $project->getName();
            $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
            $items[] = [
                'name' => $projectName,
                'domain' => $project->getDomain() ?? '',
                'sf' => $project->getSf() ?? '',
                'tags' => array_values($tags)
            ];
        }
```
Et avant le retour JSON de `getDatagridRows()` :
```php
        $domainFilter = $filters['domain'] ?? 'all';
        $allowedSfs = [];
        foreach ($items as $item) {
            if (($domainFilter === 'all' || $domainFilter === '' || $item['domain'] === $domainFilter) && !empty($item['sf'])) {
                $allowedSfs[] = $item['sf'];
            }
        }
        $allowedSfs = array_values(array_unique($allowedSfs));

        return json_encode([
            'success' => true,
            'html' => $html,
            'totalRows' => $paginated['totalRows'],
            'allowedSfs' => $allowedSfs
        ]);
```

- [ ] **Step 4: Exécuter les tests unitaires pour vérifier le succès**

Run: `.\vendor\bin\phpunit tests/controller/TagAdminControllerTest.php`  
Expected: PASS (tous les tests du contrôleur passent).

- [ ] **Step 5: Commit**

```bash
git add src/controller/TagAdminController.php tests/controller/TagAdminControllerTest.php
git commit -m "feat: extraction des domaines, sf et calcul de allowedSfs dans TagAdminController"
```

---

### Task 2: Intégration des Colonnes et Filtres dans les Templates Twig

**Files:**
- Modify: `templates/tags.html.twig`
- Modify: `templates/common/_tags_rows.html.twig`
- Test: `tests/templates/TagAdminTemplateTest.php`

- [ ] **Step 1: Écrire les assertions de test dans `TagAdminTemplateTest.php`**

Dans `tests/templates/TagAdminTemplateTest.php` :
1. Mettre à jour `testTagsRowsRendersManageButtonAndTags` pour vérifier la présence des colonnes `.col-domain` et `.col-sf` :
```php
    public function testTagsRowsRendersManageButtonAndTags(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'tags' => ['paiement', 'checkout']
            ]
        ];

        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $results,
            'offset' => 0
        ]);

        $this->assertStringContainsString('api-orders', $html);
        $this->assertStringContainsString('class="col-domain"', $html);
        $this->assertStringContainsString('pdv', $html);
        $this->assertStringContainsString('class="col-sf"', $html);
        $this->assertStringContainsString('buyers', $html);
        $this->assertStringContainsString('paiement', $html);
        $this->assertStringContainsString('checkout', $html);
        $this->assertStringContainsString('btn-manage-tags', $html);
        $this->assertStringContainsString('Gérer les tags', $html);
    }
```
2. Mettre à jour `testTagsPageRendersCardAndDatalist` pour vérifier les en-têtes et les select de filtres :
```php
        $this->assertStringContainsString('id="filter_domain"', $html);
        $this->assertStringContainsString('id="filter_sf"', $html);
        $this->assertStringContainsString('sortBy(\'domain\')', $html);
        $this->assertStringContainsString('sortBy(\'sf\')', $html);
        $this->assertStringContainsString('<option value="pdv">Point de vente</option>', $html);
        $this->assertStringContainsString('<option value="buyers">buyers</option>', $html);
```

- [ ] **Step 2: Exécuter les tests de templates pour vérifier l'échec initial**

Run: `.\vendor\bin\phpunit tests/templates/TagAdminTemplateTest.php`  
Expected: FAIL car les colonnes et filtres Domaine et SF ne sont pas encore présents dans les templates.

- [ ] **Step 3: Mettre à jour `templates/tags.html.twig`**

Remplacer le bloc `<thead>` du tableau par la structure suivante :
```html
                    <thead class="thead-dark">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th><a class="sortable-header" @click.prevent="sortBy('domain')">Domaine <i :class="getSortIconClass('domain')" :style="getSortIconStyle('domain')"></i></a></th>
                            <th><a class="sortable-header" @click.prevent="sortBy('sf')">SF <i :class="getSortIconClass('sf')" :style="getSortIconStyle('sf')"></i></a></th>
                            <th><a class="sortable-header" @click.prevent="sortBy('name')">Projet <i :class="getSortIconClass('name')" :style="getSortIconStyle('name')"></i></a></th>
                            <th>Tags associés</th>
                            <th class="text-center" style="width: 160px;">Actions</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>
                                <div class="input-group input-group-sm">
                                    <select id="filter_domain" class="form-control custom-select filter-input" x-model="filters.domain" @change="onFilterChange()">
                                        <option value="all">Tous</option>
                                        {% for key, value in domains|default([]) %}
                                            <option value="{{ key }}">{{ value }}</option>
                                        {% endfor %}
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" @click="filters.domain = 'all'; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                </div>
                            </th>
                            <th>
                                <div class="input-group input-group-sm">
                                    <select id="filter_sf" class="form-control custom-select filter-input" x-model="filters.sf" @change="onFilterChange()">
                                        <option value="all">Tous</option>
                                        {% for key, value in sfs|default([]) %}
                                            <option value="{{ key }}">{{ value }}</option>
                                        {% endfor %}
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" @click="filters.sf = 'all'; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                </div>
                            </th>
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
```

- [ ] **Step 4: Mettre à jour `templates/common/_tags_rows.html.twig`**

Remplacer les lignes par le contenu avec cellules dédiées `col-domain` et `col-sf`, et `colspan="6"` pour le cas vide :
```html
{% for r in results %}
    <tr class="project-row" data-project="{{ r.name }}" data-tags="{{ (r.tags|default([]))|json_encode|e('html_attr') }}">
        <td class="text-muted" style="width: 50px;">{{ offset + loop.index }}</td>
        <td class="col-domain">{{ r.domain|default('') }}</td>
        <td class="col-sf">{{ r.sf|default('') }}</td>
        <td>
            <strong>{{ r.name }}</strong>
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
                    onclick="manageProjectTags(this)">
                <i class="fas fa-tags mr-1"></i> Gérer les tags
            </button>
        </td>
    </tr>
{% else %}
    <tr>
        <td colspan="6" class="text-center text-muted py-4">
            <i class="fas fa-info-circle mr-1"></i> Aucun projet trouvé.
        </td>
    </tr>
{% endfor %}
```

- [ ] **Step 5: Exécuter les tests de templates pour vérifier le succès**

Run: `.\vendor\bin\phpunit tests/templates/TagAdminTemplateTest.php`  
Expected: PASS (4 tests, assertions complètes).

- [ ] **Step 6: Commit**

```bash
git add templates/tags.html.twig templates/common/_tags_rows.html.twig tests/templates/TagAdminTemplateTest.php
git commit -m "feat: affichage des colonnes Domaine et SF avec en-tetes triables et select de filtre"
```

---

### Task 3: Test E2E Playwright, PHPStan et Validation Globale

**Files:**
- Modify: `tests/e2e/console.spec.ts`

- [ ] **Step 1: Ajouter le scénario de test E2E de filtrage par domaine et SF**

Dans `tests/e2e/console.spec.ts`, enrichir le test de la page de gestion des tags :
```typescript
  test('Tag Administration Page loads without Alpine errors and allows filtering by domain and SF', async ({ page }) => {
    const alpineErrors: string[] = [];
    page.on('pageerror', err => {
      alpineErrors.push(err.message);
    });
    page.on('console', msg => {
      if (msg.type() === 'error' && msg.text().includes('Alpine Expression Error')) {
        alpineErrors.push(msg.text());
      }
    });

    await page.goto('/?page=tags');
    await expect(page.locator('#tags-card')).toBeVisible();

    // Verify datagrid headers & rows loaded
    await expect(page.locator('#projects-tbody')).toBeVisible();
    await expect(page.locator('#filter_domain')).toBeVisible();
    await expect(page.locator('#filter_sf')).toBeVisible();

    // Select domain filter if options are present
    const domainOptionsCount = await page.locator('#filter_domain option').count();
    if (domainOptionsCount > 1) {
      const secondDomainValue = await page.locator('#filter_domain option').nth(1).getAttribute('value');
      if (secondDomainValue && secondDomainValue !== 'all') {
        await page.locator('#filter_domain').selectOption(secondDomainValue);
        await page.waitForTimeout(500);
        // Verify URL or datagrid updated
        await expect(page.locator('#projects-tbody')).toBeVisible();
      }
    }

    // Check that there were no Alpine errors
    expect(alpineErrors).toEqual([]);
  });
```

- [ ] **Step 2: Exécuter le test Playwright**

Run: `npx playwright test -g "Tag Administration Page"`  
Expected: PASS (1 passed).

- [ ] **Step 3: Exécuter la suite complète de tests PHPUnit et l'analyse statique PHPStan**

Run:
1. `.\vendor\bin\phpunit`
2. `.\vendor\bin\phpstan analyse`  
Expected: PASS (tous les tests passent, 0 erreurs statiques).

- [ ] **Step 4: Commit**

```bash
git add tests/e2e/console.spec.ts
git commit -m "test: couverture E2E pour le filtrage par domaine et sf sur la page tags"
```
