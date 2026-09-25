# Plan d'implémentation : Colonne et filtre Archivé sur la Gestion des Tags

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter la colonne "Archivé" triable et son filtre déroulant (avec valeur par défaut "Non") dans la page "Gestion des tags", à l'identique de l'interface de monitoring.

**Architecture:** 
- Le contrôleur `TagAdminController` injecte la propriété booléenne `'archived' => $project->isArchived()` dans les données des lignes de datagrid, traitées par `DatagridHelper`.
- Le template `tags.html.twig` ajoute la colonne triable `Archivé` et le filtre `#filter_archived` (options: `all`, `non`, `oui`) entre les colonnes `Projet` et `Tags associés`.
- Le template partiel `_tags_rows.html.twig` affiche `<td class="col-archived">` avec l'icône `<i class="fa-solid fa-box-archive text-danger mr-1" title="Projet archivé"></i>` pour les projets archivés, et met à jour le `colspan` à 7.
- Le script `datagrid.js` initialise le filtre `archived` à `'non'` par défaut au chargement et lors de la réinitialisation (`resetFilters`) pour la page `tags`.

**Tech Stack:** PHP 8.4, Twig, Alpine.js, Bootstrap 4, PHPUnit 11.

---

### Task 1: Backend - Enrichissement de `TagAdminController::getDatagridRows` avec la propriété `archived`

**Files:**
- Modify: `src/controller/TagAdminController.php:105-125`
- Test: `tests/controller/TagAdminControllerTest.php`

- [ ] **Step 1: Écrire le test unitaire vérifiant la présence de `'archived'` et le filtrage**

Dans `tests/controller/TagAdminControllerTest.php`, ajouter la méthode `testGetDatagridRowsIncludesArchivedPropertyAndFiltersByArchived` :

```php
    public function testGetDatagridRowsIncludesArchivedPropertyAndFiltersByArchived(): void
    {
        $project1 = new Project();
        $project1->setName('api-active');
        $project1->setDomain('pdv');
        $project1->setSf('buyers');
        $project1->setArchived(false);

        $project2 = new Project();
        $project2->setName('api-archived');
        $project2->setDomain('pdv');
        $project2->setSf('buyers');
        $project2->setArchived(true);

        $this->gitlabService->method('scan')->willReturn([$project1, $project2]);
        $this->repositoryService->method('getTagsByProject')->willReturn([]);

        $capturedContext = [];
        $this->mockTwig->method('render')
            ->willReturnCallback(function (string $template, array $context) use (&$capturedContext) {
                $capturedContext = $context;
                return '<tr>rendered rows</tr>';
            });

        // 1. Sans filtre : les 2 éléments sont renvoyés avec la clé 'archived'
        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['totalRows']);
        $this->assertCount(2, $capturedContext['results']);
        $this->assertFalse($capturedContext['results'][0]['archived']);
        $this->assertTrue($capturedContext['results'][1]['archived']);

        // 2. Filtre filter_archived = 'non'
        $_REQUEST['filter_archived'] = 'non';
        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);
        $this->assertEquals(1, $data['totalRows']);
        $this->assertEquals('api-active', $capturedContext['results'][0]['name']);

        // 3. Filtre filter_archived = 'oui'
        $_REQUEST['filter_archived'] = 'oui';
        $response = $this->controller->handleRequest(TagAdminController::ACTION_GET_DATAGRID_ROWS);
        $data = json_decode($response, true);
        $this->assertEquals(1, $data['totalRows']);
        $this->assertEquals('api-archived', $capturedContext['results'][0]['name']);

        unset($_REQUEST['filter_archived']);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit tests/controller/TagAdminControllerTest.php --filter testGetDatagridRowsIncludesArchivedPropertyAndFiltersByArchived`  
Expected: FAIL car la clé `'archived'` n'existe pas dans `$capturedContext['results'][0]` et le filtre n'a aucun effet.

- [ ] **Step 3: Implémenter l'ajout de `'archived'` dans `TagAdminController.php`**

Dans `src/controller/TagAdminController.php`, modifier la boucle de construction de `$items` dans la méthode `getDatagridRows()` :

```php
        $items = [];
        foreach ($projects as $project) {
            $projectName = $project->getName();
            $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
            $items[] = [
                'name' => $projectName,
                'domain' => $project->getDomain() ?? '',
                'sf' => $project->getSf() ?? '',
                'archived' => $project->isArchived(),
                'tags' => array_values($tags)
            ];
        }
```

- [ ] **Step 4: Exécuter le test pour vérifier qu'il passe**

Run: `vendor/bin/phpunit tests/controller/TagAdminControllerTest.php --filter testGetDatagridRowsIncludesArchivedPropertyAndFiltersByArchived`  
Expected: PASS

- [ ] **Step 5: Exécuter l'ensemble de `TagAdminControllerTest`**

Run: `vendor/bin/phpunit tests/controller/TagAdminControllerTest.php`  
Expected: PASS (tous les tests passent)

- [ ] **Step 6: Commit**

```bash
git add src/controller/TagAdminController.php tests/controller/TagAdminControllerTest.php
git commit -m "feat(tags): injection de la propriete archived et support du filtrage dans TagAdminController"
```

---

### Task 2: Templates - Colonne et filtre Archivé dans `tags.html.twig` et `_tags_rows.html.twig`

**Files:**
- Modify: `templates/tags.html.twig:40-95`
- Modify: `templates/common/_tags_rows.html.twig:1-35`
- Test: `tests/templates/TagAdminTemplateTest.php`
- Test: `tests/templates/TagsRowTemplateTest.php`

- [ ] **Step 1: Écrire les tests vérifiant le rendu des templates**

Dans `tests/templates/TagAdminTemplateTest.php`, mettre à jour et enrichir `testTagsPageRendersCardAndDatalist` et `testTagsRowsRendersManageButtonAndTags` :

Dans `testTagsRowsRendersManageButtonAndTags` :
```php
    public function testTagsRowsRendersManageButtonAndTags(): void
    {
        $results = [
            [
                'name' => 'api-orders',
                'domain' => 'pdv',
                'sf' => 'buyers',
                'archived' => true,
                'tags' => ['paiement', 'checkout']
            ],
            [
                'name' => 'api-catalog',
                'domain' => 'pdv',
                'sf' => 'catalog',
                'archived' => false,
                'tags' => []
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
        $this->assertStringContainsString('class="col-archived"', $html);
        $this->assertStringContainsString('fa-box-archive text-danger', $html);
        $this->assertStringContainsString('Projet archivé', $html);
        $this->assertStringContainsString('paiement', $html);
        $this->assertStringContainsString('checkout', $html);
        $this->assertStringContainsString('btn-manage-tags', $html);
        $this->assertStringContainsString('Gérer les tags', $html);
        $this->assertStringContainsString('manageProjectTags(this)', $html);
    }
```

Ajouter dans `tests/templates/TagAdminTemplateTest.php` :
```php
    public function testTagsRowsRendersEmptyColspanSeven(): void
    {
        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => [],
            'offset' => 0
        ]);

        $this->assertStringContainsString('colspan="7"', $html);
    }
```

Dans `testTagsPageRendersCardAndDatalist` dans `tests/templates/TagAdminTemplateTest.php`, ajouter les assertions :
```php
        $this->assertStringContainsString('sortBy(\'archived\')', $html);
        $this->assertStringContainsString('id="filter_archived"', $html);
        $this->assertStringContainsString('<option value="non">Non</option>', $html);
        $this->assertStringContainsString('<option value="oui">Oui</option>', $html);
```

- [ ] **Step 2: Exécuter les tests de template pour vérifier qu'ils échouent**

Run: `vendor/bin/phpunit tests/templates/TagAdminTemplateTest.php`  
Expected: FAIL (manque `sortBy('archived')`, `filter_archived`, `col-archived`, `colspan="7"`)

- [ ] **Step 3: Mettre à jour `templates/tags.html.twig`**

Dans `templates/tags.html.twig` :
1. Dans la ligne d'en-tête `<thead><tr>` : ajouter la colonne `Archivé` après `Projet` :
```html
                            <th style="width: 50px;">#</th>
                            <th><a class="sortable-header" @click.prevent="sortBy('domain')">Domaine <i :class="getSortIconClass('domain')" :style="getSortIconStyle('domain')"></i></a></th>
                            <th><a class="sortable-header" @click.prevent="sortBy('sf')">SF <i :class="getSortIconClass('sf')" :style="getSortIconStyle('sf')"></i></a></th>
                            <th><a class="sortable-header" @click.prevent="sortBy('name')">Projet <i :class="getSortIconClass('name')" :style="getSortIconStyle('name')"></i></a></th>
                            <th><a class="sortable-header" @click.prevent="sortBy('archived')">Archivé <i :class="getSortIconClass('archived')" :style="getSortIconStyle('archived')"></i></a></th>
                            <th>Tags associés</th>
                            <th class="text-center" style="width: 160px;">Actions</th>
```
2. Dans la ligne de filtres `<thead><tr>` : ajouter la cellule `#filter_archived` après `#filter_name` :
```html
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
                                    <select id="filter_archived" class="form-control custom-select filter-input" x-model="filters.archived" @change="onFilterChange()" aria-label="Filtrer par statut archivé">
                                        <option value="all">Tous</option>
                                        <option value="non">Non</option>
                                        <option value="oui">Oui</option>
                                    </select>
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" @click="filters.archived = 'all'; onFilterChange()" title="Effacer" aria-label="Effacer le filtre archivé"><i class="fa-solid fa-xmark"></i></button>
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
```

- [ ] **Step 4: Mettre à jour `templates/common/_tags_rows.html.twig`**

Dans `templates/common/_tags_rows.html.twig` :
1. Insérer la cellule `td.col-archived` après le nom du projet :
```html
        <td>
            <strong>{{ r.name }}</strong>
        </td>
        <td class="col-archived" data-column="archived" data-value="{% if r.archived %}oui{% else %}non{% endif %}">
            {% if r.archived %}<i class="fa-solid fa-box-archive text-danger mr-1" title="Projet archivé"></i>{% endif %}
        </td>
        <td>
```
2. Mettre à jour le `colspan` dans la section vide :
```html
{% else %}
    <tr>
        <td colspan="7" class="text-center text-muted py-4">
            <i class="fas fa-info-circle mr-1"></i> Aucun projet trouvé.
        </td>
    </tr>
{% endfor %}
```

- [ ] **Step 5: Exécuter les tests de template pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit tests/templates/TagAdminTemplateTest.php`  
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add templates/tags.html.twig templates/common/_tags_rows.html.twig tests/templates/TagAdminTemplateTest.php
git commit -m "feat(tags): ajout de la colonne archive et du filtre dans les templates tags"
```

---

### Task 3: Frontend JS - Initialisation et réinitialisation de `archived` dans `public/js/datagrid.js`

**Files:**
- Modify: `public/js/datagrid.js:65-75, 235-255`

- [ ] **Step 1: Mettre à jour `init()` dans `public/js/datagrid.js`**

Dans `public/js/datagrid.js` (méthode `init()`, vers la ligne 67) :
Remplacer :
```javascript
                if (val === null) {
                    if (key === 'archived' && this.pageName === 'monitoring') {
                        val = 'non';
                    } else {
                        val = defaultFilters[key] || (input.tagName === 'SELECT' ? 'all' : '');
                    }
                }
```
Par :
```javascript
                if (val === null) {
                    if (key === 'archived' && (this.pageName === 'monitoring' || this.pageName === 'tags')) {
                        val = 'non';
                    } else {
                        val = defaultFilters[key] || (input.tagName === 'SELECT' ? 'all' : '');
                    }
                }
```

- [ ] **Step 2: Mettre à jour `resetFilters()` dans `public/js/datagrid.js`**

Dans `public/js/datagrid.js` (méthode `resetFilters()`, vers la ligne 241) :
Remplacer :
```javascript
        resetFilters() {
            Object.keys(this.filters).forEach(key => {
                if (key === 'archived' && this.pageName === 'monitoring') {
                    this.filters[key] = 'non';
                } else {
```
Par :
```javascript
        resetFilters() {
            Object.keys(this.filters).forEach(key => {
                if (key === 'archived' && (this.pageName === 'monitoring' || this.pageName === 'tags')) {
                    this.filters[key] = 'non';
                } else {
```

- [ ] **Step 3: Vérifier la syntaxe JS via Node ou linter si disponible**

Run: `node -c public/js/datagrid.js`  
Expected: Pas d'erreur de syntaxe.

- [ ] **Step 4: Commit**

```bash
git add public/js/datagrid.js
git commit -m "feat(datagrid): prise en compte de la page tags pour la valeur par defaut du filtre archived"
```

---

### Task 4: Validation complète et non-régression

**Files:**
- Test: ensemble des suites de tests PHPUnit

- [ ] **Step 1: Exécuter la suite complète de tests PHPUnit**

Run: `vendor/bin/phpunit`  
Expected: PASS (533+ tests passants, 0 régression)

- [ ] **Step 2: Vérifier le statut git**

Run: `git status`  
Expected: Working tree clean (sur les fichiers trackés).
