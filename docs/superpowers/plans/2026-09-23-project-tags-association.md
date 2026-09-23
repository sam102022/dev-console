# Association de Projets par Tags Libres - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre d'associer des projets entre eux via des tags libres persistés en base SQLite, visibles et filtrables sur les 3 interfaces (GitLab, Monitoring, Rundeck), avec édition inline pour les administrateurs.

**Architecture:** Table SQLite dédiée `project_tags` découplée des tables de cache, hydratation des tags sur `Project` et `RundeckProject`, filtrage multi-critères (nom + tags) dans `DatagridHelper`, endpoints d'API sécurisés par rôle `ROLE_ADMIN` dans `IndexController`, et composants d'affichage / édition inline Alpine.js dans les templates de lignes.

**Tech Stack:** PHP 8.4, SQLite (PDO), Symfony MicroKernel DI, Twig 3, Alpine.js, Bootstrap 4, FontAwesome 6, PHPUnit 11.

---

### Task 1: Persistance SQLite & Méthodes RepositoryService pour les Tags

**Files:**
- Modify: `src/service/RepositoryService.php`
- Modify: `tests/service/RepositoryServiceTest.php`

- [ ] **Step 1: Écrire le test unitaire pour les opérations CRUD sur les tags dans RepositoryServiceTest**

Dans `tests/service/RepositoryServiceTest.php`, ajouter les tests de `addProjectTag`, `getTagsByProject`, `getTagsForProject`, `removeProjectTag`, `getAllTags`, et s'assurer que l'initialisation de la table `project_tags` fonctionne sans écrasement des tags :

```php
    public function testProjectTagsCrud(): void
    {
        // 1. Initialement vide
        $tags = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertEmpty($tags);

        // 2. Ajout de tags
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'paiement'));
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'checkout'));
        $this->assertTrue($this->repositoryService->addProjectTag('flow-orders', 'paiement'));

        // 3. Récupération par projet
        $ordersTags = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(2, $ordersTags);
        $this->assertContains('paiement', $ordersTags);
        $this->assertContains('checkout', $ordersTags);

        // 4. Récupération groupée
        $allGrouped = $this->repositoryService->getTagsByProject();
        $this->assertArrayHasKey('api-orders', $allGrouped);
        $this->assertArrayHasKey('flow-orders', $allGrouped);
        $this->assertContains('paiement', $allGrouped['flow-orders']);

        // 5. Récupération de tous les tags uniques
        $uniqueTags = $this->repositoryService->getAllTags();
        $this->assertEquals(['checkout', 'paiement'], $uniqueTags);

        // 6. Suppression d'un tag
        $this->assertTrue($this->repositoryService->removeProjectTag('api-orders', 'checkout'));
        $ordersTagsAfter = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(1, $ordersTagsAfter);
        $this->assertNotContains('checkout', $ordersTagsAfter);

        // 7. Doublon ignoré (idempotence)
        $this->assertTrue($this->repositoryService->addProjectTag('api-orders', 'paiement'));
        $ordersTagsDedup = $this->repositoryService->getTagsForProject('api-orders');
        $this->assertCount(1, $ordersTagsDedup);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Exécuter : `vendor/bin/phpunit tests/service/RepositoryServiceTest.php --filter=testProjectTagsCrud`  
Résultat attendu : FAIL (méthode `getTagsForProject` inexistante).

- [ ] **Step 3: Implémenter la table SQLite et les méthodes dans RepositoryService**

Dans `src/service/RepositoryService.php` :
1. Dans `initDatabase()`, ajouter dans le script SQL de création :
```sql
            CREATE TABLE IF NOT EXISTS project_tags (
                project_name TEXT NOT NULL,
                tag TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (project_name, tag)
            );
            CREATE INDEX IF NOT EXISTS idx_project_tags_name ON project_tags(project_name);
            CREATE INDEX IF NOT EXISTS idx_project_tags_tag ON project_tags(tag);
```
2. Ajouter les méthodes publiques :
```php
    public function getTagsByProject(): array
    {
        if (!$this->useSqlite) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT project_name, tag FROM project_tags ORDER BY tag ASC");
        $rows = $stmt->fetchAll();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['project_name']][] = $row['tag'];
        }
        return $grouped;
    }

    public function getTagsForProject(string $projectName): array
    {
        if (!$this->useSqlite) {
            return [];
        }
        $stmt = $this->pdo->prepare("SELECT tag FROM project_tags WHERE project_name = :project_name ORDER BY tag ASC");
        $stmt->execute([':project_name' => $projectName]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    public function addProjectTag(string $projectName, string $tag): bool
    {
        if (!$this->useSqlite) {
            return false;
        }
        $tag = strtolower(trim($tag));
        if ($tag === '' || $projectName === '') {
            return false;
        }
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO project_tags (project_name, tag) VALUES (:project_name, :tag)");
        return $stmt->execute([':project_name' => $projectName, ':tag' => $tag]);
    }

    public function removeProjectTag(string $projectName, string $tag): bool
    {
        if (!$this->useSqlite) {
            return false;
        }
        $tag = strtolower(trim($tag));
        $stmt = $this->pdo->prepare("DELETE FROM project_tags WHERE project_name = :project_name AND tag = :tag");
        return $stmt->execute([':project_name' => $projectName, ':tag' => $tag]);
    }

    public function getAllTags(): array
    {
        if (!$this->useSqlite) {
            return [];
        }
        $stmt = $this->pdo->query("SELECT DISTINCT tag FROM project_tags ORDER BY tag ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }
```

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Exécuter : `vendor/bin/phpunit tests/service/RepositoryServiceTest.php --filter=testProjectTagsCrud`  
Résultat attendu : PASS (1 test, 12 assertions).

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add src/service/RepositoryService.php tests/service/RepositoryServiceTest.php
git commit -m "feat: persistance sqlite et methodes repositoryService pour les tags"
```

---

### Task 2: Modèles & Mappers (Project et RundeckProject)

**Files:**
- Modify: `src/model/Project.php`
- Modify: `src/repository/model/ProjectEntity.php`
- Modify: `src/repository/mapper/ProjectMapper.php`
- Modify: `src/model/RundeckProject.php`
- Modify: `src/repository/model/RundeckProjectEntity.php`
- Modify: `src/repository/mapper/RundeckProjectMapper.php`
- Modify: `tests/repository/mapper/ProjectMapperTest.php`
- Modify: `tests/repository/mapper/RundeckProjectMapperTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour le mapping des tags**

Dans `tests/repository/mapper/ProjectMapperTest.php` :
Tester qu'un tableau contenant `tags: ['paiement', 'checkout']` est correctement mappé en `ProjectEntity` puis en `Project`, et vice-versa.

Dans `tests/repository/mapper/RundeckProjectMapperTest.php` :
Tester qu'un `RundeckProjectEntity` et `RundeckProject` gèrent et mappent correctement `$tags`.

- [ ] **Step 2: Exécuter les tests pour vérifier l'échec**

Exécuter : `vendor/bin/phpunit tests/repository/mapper/ProjectMapperTest.php tests/repository/mapper/RundeckProjectMapperTest.php`  
Résultat attendu : FAIL (propriétés ou méthodes `getTags()` / `setTags()` non reconnues ou absentes du mapper).

- [ ] **Step 3: Ajouter `tags` dans les modèles et mappers**

1. Dans `src/model/Project.php` :
```php
    public array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
        return $this;
    }
```

2. Dans `src/repository/model/ProjectEntity.php` :
```php
    private array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
        return $this;
    }
```

3. Dans `src/repository/mapper/ProjectMapper.php` :
Dans `projectEntityFromArray` :
```php
$entity->setTags($data['tags'] ?? []);
```
Dans `toArray` :
```php
$data['tags'] = $entity->getTags();
```
Dans `fromEntity` :
```php
$project->setTags($entity->getTags());
```
Dans `projectFromArray` :
```php
$project->setTags($data['tags'] ?? []);
```

4. Dans `src/model/RundeckProject.php` :
```php
    private array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
        return $this;
    }
```

5. Dans `src/repository/model/RundeckProjectEntity.php` :
```php
    private array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): self
    {
        $this->tags = array_values(array_unique(array_filter(array_map('trim', $tags))));
        return $this;
    }
```

6. Dans `src/repository/mapper/RundeckProjectMapper.php` :
Mapper `$tags` dans `fromEntity`, `toEntity`, `fromArray`, `toArray`.

- [ ] **Step 4: Exécuter les tests pour vérifier le succès**

Exécuter : `vendor/bin/phpunit tests/repository/mapper/ProjectMapperTest.php tests/repository/mapper/RundeckProjectMapperTest.php`  
Résultat attendu : PASS.

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add src/model/Project.php src/repository/model/ProjectEntity.php src/repository/mapper/ProjectMapper.php src/model/RundeckProject.php src/repository/model/RundeckProjectEntity.php src/repository/mapper/RundeckProjectMapper.php tests/repository/mapper/
git commit -m "feat: ajout gestion des tags dans Project, RundeckProject et leurs mappers"
```

---

### Task 3: Hydratation des Tags dans GitlabService et RundeckService

**Files:**
- Modify: `src/service/GitlabService.php`
- Modify: `src/service/RundeckService.php`
- Modify: `tests/service/GitlabServiceTest.php`
- Modify: `tests/service/RundeckServiceTest.php`

- [ ] **Step 1: Écrire le test d'hydratation des tags dans GitlabServiceTest et RundeckServiceTest**

Dans `tests/service/GitlabServiceTest.php`, vérifier que lorsque `RepositoryService::getTagsByProject()` renvoie `['project1' => ['mon-tag']]`, les `Project` retournés par `scan()` ou `getProjectByCode()` possèdent bien `tags = ['mon-tag']`.

Dans `tests/service/RundeckServiceTest.php`, vérifier que les `RundeckProject` retournés par `findAll()` ont bien leurs `tags` hydratés.

- [ ] **Step 2: Exécuter les tests pour vérifier l'échec**

Exécuter : `vendor/bin/phpunit tests/service/GitlabServiceTest.php tests/service/RundeckServiceTest.php`  
Résultat attendu : FAIL (tags non hydratés).

- [ ] **Step 3: Implémenter l'hydratation des tags**

1. Dans `src/service/GitlabService.php` :
Injecter `RepositoryService` (ou utiliser `$this->projectRepository->getTagsByProject()`).
Dans `scan()` :
```php
$tagsByProject = $this->repositoryService->getTagsByProject();
foreach ($projects as $project) {
    if (isset($tagsByProject[$project->getName()])) {
        $project->setTags($tagsByProject[$project->getName()]);
    }
}
```
Faire de même dans `getProjectByCode(string $code)`.

2. Dans `src/service/RundeckService.php` :
Dans `findAll()` :
```php
$tagsByProject = $this->repositoryService->getTagsByProject();
foreach ($rundeckProjects as $rp) {
    if (isset($tagsByProject[$rp->getName()])) {
        $rp->setTags($tagsByProject[$rp->getName()]);
    }
}
```

- [ ] **Step 4: Exécuter les tests pour vérifier le succès**

Exécuter : `vendor/bin/phpunit tests/service/GitlabServiceTest.php tests/service/RundeckServiceTest.php`  
Résultat attendu : PASS.

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add src/service/GitlabService.php src/service/RundeckService.php tests/service/GitlabServiceTest.php tests/service/RundeckServiceTest.php
git commit -m "feat: hydratation des tags dans GitlabService et RundeckService"
```

---

### Task 4: ViewModels & DatagridHelper (Filtrage multi-critères Nom + Tags)

**Files:**
- Modify: `src/viewModel/IndexViewModelFactory.php`
- Modify: `src/viewModel/RundeckViewModelFactory.php`
- Modify: `src/util/DatagridHelper.php`
- Modify: `tests/viewModel/IndexViewModelFactoryTest.php`
- Modify: `tests/viewModel/RundeckViewModelFactoryTest.php`
- Modify: `tests/util/DatagridHelperTest.php`

- [ ] **Step 1: Écrire le test unitaire pour le filtrage par tag dans DatagridHelperTest**

Dans `tests/util/DatagridHelperTest.php` :
Tester :
- Recherche par nom : `"orders"` filtre les projets ayant `"orders"` dans le nom.
- Recherche par tag : `"paiement"` filtre les projets ayant le tag `"paiement"`, même si leur nom ne contient pas `"paiement"`.
- Recherche insensible à la casse : `"PAIEMENT"` trouve le tag `"paiement"`.
- Non correspondance : `"inconnu"` ne retourne rien.

- [ ] **Step 2: Exécuter DatagridHelperTest pour vérifier l'échec**

Exécuter : `vendor/bin/phpunit tests/util/DatagridHelperTest.php`  
Résultat attendu : FAIL (la recherche ne prend pas encore en compte les tags).

- [ ] **Step 3: Mettre à jour IndexViewModelFactory, RundeckViewModelFactory et DatagridHelper**

1. Dans `src/viewModel/IndexViewModelFactory.php` :
Ajouter dans `$formattedResults[]` :
```php
'tags' => $project->getTags(),
```

2. Dans `src/viewModel/RundeckViewModelFactory.php` :
Ajouter dans `$formattedResults[]` :
```php
'tags' => $rundeckProject->getTags(),
```

3. Dans `src/util/DatagridHelper.php` :
Dans la boucle de filtrage, pour le filtre `name` :
```php
if ($key === 'name') {
    $matchName = stripos((string)($item['name'] ?? ''), (string)$val) !== false;
    $tags = $item['tags'] ?? [];
    $matchTags = false;
    if (is_array($tags)) {
        foreach ($tags as $t) {
            if (stripos((string)$t, (string)$val) !== false) {
                $matchTags = true;
                break;
            }
        }
    }
    if (!$matchName && !$matchTags) {
        return false;
    }
    continue;
}
```

- [ ] **Step 4: Exécuter les tests pour vérifier le succès**

Exécuter : `vendor/bin/phpunit tests/util/DatagridHelperTest.php tests/viewModel/IndexViewModelFactoryTest.php tests/viewModel/RundeckViewModelFactoryTest.php`  
Résultat attendu : PASS.

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add src/viewModel/IndexViewModelFactory.php src/viewModel/RundeckViewModelFactory.php src/util/DatagridHelper.php tests/viewModel/ tests/util/DatagridHelperTest.php
git commit -m "feat: transmission des tags aux vues et filtrage projet par nom et tags dans DatagridHelper"
```

---

### Task 5: Endpoints d'API AJAX & Contrôle d'Accès Sécurisé

**Files:**
- Modify: `src/config/config.php`
- Modify: `src/controller/IndexController.php`
- Modify: `src/router/IndexRouter.php`
- Modify: `tests/controller/IndexControllerTest.php`
- Modify: `tests/router/IndexRouterTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour les actions addProjectTag et removeProjectTag**

Dans `tests/controller/IndexControllerTest.php` :
- Test 1 : Appel de `ACTION_ADD_PROJECT_TAG` sans session admin -> Erreur HTTP 403 Forbidden.
- Test 2 : Appel de `ACTION_ADD_PROJECT_TAG` avec session `ROLE_ADMIN`, `projectName` et `tag` valides -> HTTP 200 `{"success": true, "tags": [...]}`.
- Test 3 : Appel de `ACTION_REMOVE_PROJECT_TAG` avec session `ROLE_ADMIN` -> HTTP 200 `{"success": true, "tags": [...]}`.

Dans `tests/router/IndexRouterTest.php` :
- Vérifier le routage des actions `ACTION_ADD_PROJECT_TAG` et `ACTION_REMOVE_PROJECT_TAG`.

- [ ] **Step 2: Exécuter les tests pour vérifier l'échec**

Exécuter : `vendor/bin/phpunit tests/controller/IndexControllerTest.php tests/router/IndexRouterTest.php`  
Résultat attendu : FAIL.

- [ ] **Step 3: Implémenter les constantes, le contrôleur et le routeur**

1. Dans `src/config/config.php` :
```php
const ACTION_ADD_PROJECT_TAG = 'addProjectTag';
const ACTION_REMOVE_PROJECT_TAG = 'removeProjectTag';
```

2. Dans `src/controller/IndexController.php` :
Ajouter `RepositoryService $repositoryService` dans le constructeur.
Dans `handleRequest(string $action): string` :
```php
case ACTION_ADD_PROJECT_TAG:
    if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
        http_response_code(403);
        return json_encode(['success' => false, 'error' => 'Accès refusé. Rôle administrateur requis.']);
    }
    $projectName = trim($input['projectName'] ?? '');
    $tag = trim($input['tag'] ?? '');
    if ($projectName === '' || $tag === '') {
        http_response_code(400);
        return json_encode(['success' => false, 'error' => 'Paramètres manquants.']);
    }
    $this->repositoryService->addProjectTag($projectName, $tag);
    $tags = $this->repositoryService->getTagsForProject($projectName);
    return json_encode(['success' => true, 'tags' => $tags]);

case ACTION_REMOVE_PROJECT_TAG:
    if (($_SESSION['user_role'] ?? '') !== 'ROLE_ADMIN') {
        http_response_code(403);
        return json_encode(['success' => false, 'error' => 'Accès refusé. Rôle administrateur requis.']);
    }
    $projectName = trim($input['projectName'] ?? '');
    $tag = trim($input['tag'] ?? '');
    if ($projectName === '' || $tag === '') {
        http_response_code(400);
        return json_encode(['success' => false, 'error' => 'Paramètres manquants.']);
    }
    $this->repositoryService->removeProjectTag($projectName, $tag);
    $tags = $this->repositoryService->getTagsForProject($projectName);
    return json_encode(['success' => true, 'tags' => $tags]);
```

3. Dans `src/router/IndexRouter.php` :
Dans `switch ($action)` :
```php
case ACTION_ADD_PROJECT_TAG:
case ACTION_REMOVE_PROJECT_TAG:
    echo $this->indexController->handleRequest($action);
    return;
```

- [ ] **Step 4: Exécuter les tests pour vérifier le succès**

Exécuter : `vendor/bin/phpunit tests/controller/IndexControllerTest.php tests/router/IndexRouterTest.php`  
Résultat attendu : PASS.

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add src/config/config.php src/controller/IndexController.php src/router/IndexRouter.php tests/controller/IndexControllerTest.php tests/router/IndexRouterTest.php
git commit -m "feat: endpoints api securises addProjectTag et removeProjectTag pour administrateurs"
```

---

### Task 6: Intégration Frontend Datagrid & Templates (GitLab, Monitoring, Rundeck)

**Files:**
- Modify: `templates/common/_index_rows.html.twig`
- Modify: `templates/common/_monitoring_rows.html.twig`
- Modify: `templates/common/_rundeck_rows.html.twig`
- Modify: `public/js/datagrid.js`

- [ ] **Step 1: Modifier `templates/common/_index_rows.html.twig`**

Sous le lien du projet dans la colonne `col-name` :
Afficher la liste des tags avec badges, le clic pour filtrer, la croix de suppression si `session.user_role == 'ROLE_ADMIN'`, et le bouton `+` inline :
```html
<div class="project-tags mt-1 d-flex flex-wrap align-items-center" style="gap: 3px;">
    {% for tag in r.tags|default([]) %}
        <span class="badge badge-light border text-secondary tag-badge" style="cursor: pointer;" @click.stop="filterByTag('{{ tag }}')" title="Filtrer par ce tag">
            <i class="fa-solid fa-tag mr-1 text-muted"></i>{{ tag }}
            {% if session.user_role is defined and session.user_role == 'ROLE_ADMIN' %}
                <i class="fa-solid fa-xmark ml-1 text-danger delete-tag-btn" @click.stop="removeTag('{{ r.name }}', '{{ tag }}')" title="Supprimer ce tag"></i>
            {% endif %}
        </span>
    {% endfor %}
    {% if session.user_role is defined and session.user_role == 'ROLE_ADMIN' %}
        <span class="badge badge-outline-secondary border text-muted add-tag-btn" style="cursor: pointer;" @click.stop="openTagInput('{{ r.name }}')" title="Ajouter un tag" x-show="editingProject !== '{{ r.name }}'">
            <i class="fa-solid fa-plus"></i>
        </span>
        <span x-show="editingProject === '{{ r.name }}'" class="d-inline-flex align-items-center">
            <input type="text" class="form-control form-control-sm px-1 py-0 tag-input" style="height: 20px; font-size: 0.75rem; width: 90px;" placeholder="Tag..." x-model="newTagText" @keyup.enter="saveTag('{{ r.name }}')" @keyup.escape="closeTagInput()" @click.stop="" />
            <button class="btn btn-xs btn-link text-success p-0 ml-1" type="button" @click.stop="saveTag('{{ r.name }}')" title="Valider"><i class="fa-solid fa-check"></i></button>
            <button class="btn btn-xs btn-link text-muted p-0 ml-1" type="button" @click.stop="closeTagInput()" title="Annuler"><i class="fa-solid fa-xmark"></i></button>
        </span>
    {% endif %}
</div>
```

- [ ] **Step 2: Reporter le composant de tags dans `_monitoring_rows.html.twig` et `_rundeck_rows.html.twig`**

Appliquer la même structure dans `_monitoring_rows.html.twig` et `_rundeck_rows.html.twig` dans la colonne `col-name`.

- [ ] **Step 3: Mettre à jour `public/js/datagrid.js`**

Dans l'objet retourné par `datagrid(...)` :
Ajouter les variables d'état et méthodes :
```javascript
editingProject: null,
newTagText: '',

openTagInput(projectName) {
    this.editingProject = projectName;
    this.newTagText = '';
    this.$nextTick(() => {
        const input = document.querySelector('.tag-input');
        if (input) input.focus();
    });
},

closeTagInput() {
    this.editingProject = null;
    this.newTagText = '';
},

async saveTag(projectName) {
    const tag = this.newTagText.trim();
    if (!tag) {
        this.closeTagInput();
        return;
    }
    try {
        const response = await fetch('?action=addProjectTag', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ projectName, tag })
        });
        const data = await response.json();
        if (data.success) {
            this.closeTagInput();
            this.fetchData(); // Rafraîchit les lignes du datagrid
        } else {
            alert(data.error || 'Erreur lors de l\'ajout du tag');
        }
    } catch (e) {
        alert('Erreur réseau lors de l\'ajout du tag');
    }
},

async removeTag(projectName, tag) {
    if (!confirm(`Supprimer le tag "${tag}" du projet ${projectName} ?`)) {
        return;
    }
    try {
        const response = await fetch('?action=removeProjectTag', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ projectName, tag })
        });
        const data = await response.json();
        if (data.success) {
            this.fetchData(); // Rafraîchit les lignes du datagrid
        } else {
            alert(data.error || 'Erreur lors de la suppression du tag');
        }
    } catch (e) {
        alert('Erreur réseau lors de la suppression du tag');
    }
},

filterByTag(tag) {
    this.filters.name = tag;
    this.onFilterChange();
}
```

- [ ] **Step 4: Valider avec la suite complète de tests PHPUnit**

Exécuter : `vendor/bin/phpunit`  
Résultat attendu : 100% PASS, aucune régression.

- [ ] **Step 5: Committer les modifications**

Exécuter :
```bash
git add templates/common/_index_rows.html.twig templates/common/_monitoring_rows.html.twig templates/common/_rundeck_rows.html.twig public/js/datagrid.js
git commit -m "feat: affichage des tags, edition inline admin et filtrage instantane sur index, monitoring et rundeck"
```
