# Helm probes.basePath Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extraire le chemin `mdm-workload.config.deployment.container.probes.basePath` des fichiers Helm `chart/values.yaml` et `chart/values-<env>.yaml` pour configurer dynamiquement le préfixe d'URI des URLs Actuator (`health` et `info`).

**Architecture:** Enrichir `ChartParser` avec la méthode `parseProbesBasePath`, propager la propriété `$basePaths` à travers `Project`, `ProjectEntity`, `ProjectMapper` et la persistance SQLite `RepositoryService`, faire évoluer `MonitoringUtils::buildUrlActuator` pour utiliser le `basePath` effectif par environnement, et orchestrer le chargement concurrent de `values.yaml` dans `GitlabService`.

**Tech Stack:** PHP 8.4, Symfony Yaml, PHPUnit 11, SQLite (PDO), GuzzleHttp.

---

### Task 1: Parsing de `probes.basePath` dans `ChartParser`

**Files:**
- Modify: `src/parser/ChartParser.php`
- Test: `tests/parser/ChartParserTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour `parseProbesBasePath`**

Ajouter dans `tests/parser/ChartParserTest.php` le DataProvider et la méthode de test suivante :

```php
    public static function probesBasePathProvider(): array
    {
        return [
            'valid basePath with leading slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
        probes:
          basePath: "/v2"
YAML,
                'expected' => '/v2',
            ],
            'valid basePath without leading slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "v2"
YAML,
                'expected' => '/v2',
            ],
            'valid basePath with trailing slash' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/v2/"
YAML,
                'expected' => '/v2',
            ],
            'multi-segment basePath' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/api/v3/"
YAML,
                'expected' => '/api/v3',
            ],
            'values without probes section' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
YAML,
                'expected' => null,
            ],
            'values with empty basePath' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: ""
YAML,
                'expected' => null,
            ],
            'empty content' => [
                'content' => '',
                'expected' => null,
            ],
            'null content' => [
                'content' => null,
                'expected' => null,
            ],
            'malformed yaml' => [
                'content' => "mdm-workload: [unclosed",
                'expected' => null,
            ],
        ];
    }

    #[DataProvider('probesBasePathProvider')]
    final public function testParseProbesBasePath(?string $content, ?string $expected): void
    {
        $this->assertEquals($expected, $this->parser->parseProbesBasePath($content));
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter testParseProbesBasePath`  
Expected: FAIL avec "Error: Call to undefined method App\parser\ChartParser::parseProbesBasePath()"

- [ ] **Step 3: Implémenter `parseProbesBasePath` dans `ChartParser`**

Ajouter la méthode dans `src/parser/ChartParser.php` :

```php
    /**
     * Analyse le contenu d'un fichier values.yaml ou values-<env>.yaml pour extraire le probes.basePath.
     *
     * @param string|null $content Le contenu YAML du fichier de values.
     * @return string|null Le basePath normalisé (ex: "/v2"), ou null s'il n'est pas défini.
     */
    public function parseProbesBasePath(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        try {
            $parsed = YamlParser::parse($content);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($parsed)) {
            return null;
        }

        $basePath = $parsed['mdm-workload']['config']['deployment']['container']['probes']['basePath'] ?? null;
        if (!is_string($basePath)) {
            return null;
        }

        $trimmed = trim($basePath);
        if ($trimmed === '' || $trimmed === '/') {
            return null;
        }

        $normalized = '/' . trim($trimmed, '/');

        return $normalized;
    }
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter testParseProbesBasePath`  
Expected: PASS (9 assertions)

- [ ] **Step 5: Commit**

Run: `git add src/parser/ChartParser.php tests/parser/ChartParserTest.php; git commit -m "feat: ajout parseProbesBasePath dans ChartParser"`

---

### Task 2: Modèle, Entité et Mapper pour `basePaths`

**Files:**
- Modify: `src/model/Project.php`
- Modify: `src/repository/model/ProjectEntity.php`
- Modify: `src/repository/mapper/ProjectMapper.php`
- Test: `tests/repository/mapper/ProjectMapperTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour `basePaths` dans `ProjectMapperTest`**

Mettre à jour `getSampleProjectData` dans `tests/repository/mapper/ProjectMapperTest.php` pour inclure `'basePaths'` :

```php
            'basePaths' => [
                'dev' => '/v2',
                'rec' => '/v2',
                'pp' => '/v2',
                'prod' => '/v2',
            ],
```

Et ajouter des assertions dans `testProjectEntityFromArray`, `testProjectFromArray`, et `testFromEntity` :

```php
        $this->assertEquals(['dev' => '/v2', 'rec' => '/v2', 'pp' => '/v2', 'prod' => '/v2'], $entity->getBasePaths());
```

```php
        $this->assertEquals(['dev' => '/v2', 'rec' => '/v2', 'pp' => '/v2', 'prod' => '/v2'], $project->getBasePaths());
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter ProjectMapperTest`  
Expected: FAIL avec "Error: Call to undefined method App\repository\model\ProjectEntity::getBasePaths()"

- [ ] **Step 3: Mettre à jour `Project.php`, `ProjectEntity.php`, et `ProjectMapper.php`**

Dans `src/model/Project.php` :
1. Ajouter la propriété :
```php
    public array $basePaths = [];
```
2. Ajouter le getter et setter :
```php
    public function getBasePaths(): array
    {
        return $this->basePaths;
    }

    public function setBasePaths(array $basePaths): self
    {
        $this->basePaths = $basePaths;
        return $this;
    }
```
3. Mettre à jour `build()` pour accepter `array $basePaths = []` et appeler `$project->setBasePaths($basePaths);`.

Dans `src/repository/model/ProjectEntity.php` :
1. Ajouter la propriété :
```php
    private array $basePaths = [];
```
2. Ajouter le getter et setter :
```php
    public function getBasePaths(): array
    {
        return $this->basePaths;
    }

    public function setBasePaths(array $basePaths): void
    {
        $this->basePaths = $basePaths;
    }
```
3. Mettre à jour `build()` pour accepter `array $basePaths = []` et appeler `$project->setBasePaths($basePaths);`.

Dans `src/repository/mapper/ProjectMapper.php` :
1. Dans `projectEntityFromArray`:
```php
        $projectEntity->setBasePaths($data['basePaths'] ?? []);
```
2. Dans `projectFromArray`:
```php
        $project->setBasePaths($data['basePaths'] ?? []);
```
3. Dans `fromEntity`:
```php
        $project->setBasePaths($entity->getBasePaths());
```
4. Dans `toEntity`:
Ajouter `$project->getBasePaths()` dans l'appel `ProjectEntity::build(...)`.
5. Dans `toArray`:
```php
        'basePaths' => $projectEntity->getBasePaths(),
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter ProjectMapperTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add src/model/Project.php src/repository/model/ProjectEntity.php src/repository/mapper/ProjectMapper.php tests/repository/mapper/ProjectMapperTest.php; git commit -m "feat: gestion de basePaths dans Project, ProjectEntity et ProjectMapper"`

---

### Task 3: Persistance SQLite de `basePaths` dans `RepositoryService`

**Files:**
- Modify: `src/service/RepositoryService.php`
- Test: `tests/service/RepositoryServiceTest.php`

- [ ] **Step 1: Écrire les assertions de test dans `RepositoryServiceTest`**

Dans `tests/service/RepositoryServiceTest.php`, dans `testProjectOperations` :
1. Ajouter `'basePaths' => ['dev' => '/v2', 'prod' => '/v2']` dans l'élément du tableau `$projects`.
2. Ajouter l'assertion :
```php
        $this->assertEquals(['dev' => '/v2', 'prod' => '/v2'], $loadedProjects[0]['basePaths']);
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter testProjectOperations`  
Expected: FAIL car `basePaths` n'est pas encore présent ou retourné.

- [ ] **Step 3: Implémenter la persistance de `basePaths` dans `RepositoryService`**

Dans `src/service/RepositoryService.php` :
1. Dans `createTables()`, ajouter `basePaths TEXT,` dans la table `projects` :
```sql
                urlsDeploymentGcp TEXT,
                basePaths TEXT
```
Et la migration automatique :
```php
        try {
            $this->pdo->exec("ALTER TABLE projects ADD COLUMN basePaths TEXT");
        } catch (\Throwable) {
            // Ignorer si la colonne existe déjà
        }
```
2. Dans `saveProjects(array $projects)` :
Ajouter `basePaths` dans la liste des colonnes de `INSERT OR REPLACE INTO projects`, `:basePaths` dans `VALUES`, et dans le bind :
```php
        ':basePaths' => json_encode($proj['basePaths'] ?? [])
```
3. Dans `saveProject(array $proj)` :
Ajouter `basePaths` dans les colonnes `INSERT`, `VALUES`, `DO UPDATE SET basePaths = excluded.basePaths`, et le bind `:basePaths => json_encode($proj['basePaths'] ?? [])`.
4. Dans `getProjects()` et `findProjectByName(string $name)` :
Ajouter :
```php
        $row['basePaths'] = json_decode($row['basePaths'] ?? '[]', true);
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter testProjectOperations`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add src/service/RepositoryService.php tests/service/RepositoryServiceTest.php; git commit -m "feat: persistance sqlite du champ basePaths dans RepositoryService"`

---

### Task 4: Prise en compte de `basePaths` dans `MonitoringUtils::buildUrlActuator`

**Files:**
- Modify: `src/util/MonitoringUtils.php`
- Test: `tests/util/MonitoringUtilsTest.php`

- [ ] **Step 1: Ajouter des cas de test dans `MonitoringUtilsTest`**

Dans `tests/util/MonitoringUtilsTest.php` :
1. Dans `buildUrlActuatorHealthProvider()`, ajouter :
```php
            'API project with custom basePath /v2' => [
                ProjectFixtures::getMonitoringProject('api-orders', true)
                    ->setBasePaths(['dev' => '/v2']),
                EnumEnvironment::DEV,
                [],
                'https://management-api-orders.dev.mdm-int.net/v2/actuator/health'
            ],
            'Non-API project with custom basePath /v2' => [
                ProjectFixtures::getMonitoringProject('my-service', true)
                    ->setBasePaths(['dev' => '/v2']),
                EnumEnvironment::DEV,
                [],
                'https://management-my-service.dev.mdm-int.net/v2/actuator/health'
            ],
            'API project with custom basePath and custom pathLivenessProbe' => [
                ProjectFixtures::getMonitoringProject('api-orders', true)
                    ->setBasePaths(['dev' => '/v2'])
                    ->setPathLivenessProbe('/custom/health'),
                EnumEnvironment::DEV,
                [],
                'https://management-api-orders.dev.mdm-int.net/custom/health'
            ],
```
2. Dans `buildUrlActuatorInfoProvider()`, ajouter :
```php
            'API project with custom basePath /v2' => [
                ProjectFixtures::getMonitoringProject('api-orders', true)
                    ->setBasePaths(['dev' => '/v2']),
                EnumEnvironment::DEV,
                [],
                'https://management-api-orders.dev.mdm-int.net/v2/actuator/info'
            ],
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter testBuildUrlHealthCheck`  
Expected: FAIL ("https://management-api-orders.dev.mdm-int.net/v1/actuator/health" does not match expected ".../v2/actuator/health")

- [ ] **Step 3: Mettre à jour `MonitoringUtils::buildUrlActuator`**

Dans `src/util/MonitoringUtils.php`, modifier `buildUrlActuator` :

```php
        if ($project->getPathLivenessProbe()) {
            $uriActuator = $project->getPathLivenessProbe();
        } else {
            $basePaths = $project->getBasePaths();
            $basePath = ($env !== null && !empty($basePaths[$env->value]))
                ? $basePaths[$env->value]
                : null;

            $prefix = '';
            if ($basePath !== null) {
                $prefix = $basePath;
            } elseif (str_starts_with($projectName, 'api')) {
                $prefix = '/v1';
            }

            $uriActuator = $prefix . '/actuator/' . $actuatorEndpoint->value;
        }
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter MonitoringUtilsTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add src/util/MonitoringUtils.php tests/util/MonitoringUtilsTest.php; git commit -m "feat: prise en compte de basePaths dans MonitoringUtils::buildUrlActuator"`

---

### Task 5: Orchestration du scan GitLab et hydratation dans `GitlabService`

**Files:**
- Modify: `src/service/GitlabService.php`
- Test: `tests/service/GitlabServiceTest.php`

- [ ] **Step 1: Écrire les tests unitaires dans `GitlabServiceTest`**

Ajouter une méthode de test `testScanHydratesBasePathsWithValuesAndEnvOverride` dans `tests/service/GitlabServiceTest.php` :

```php
    final public function testScanHydratesBasePathsWithValuesAndEnvOverride(): void
    {
        $this->projectRepository->method('findAll')->willThrowException(new TechnicalException("Le cache est vide"));

        $projectEntity = GitlabProjectEntityFixtures::getGitlabProjectEntityA();
        $this->gitLabRepository->method('findAll')->willReturn([$projectEntity]);

        $this->client->method('getFileAsync')
            ->willReturnCallback(function ($id, $path, $raw, $branch) {
                $content = null;
                if ($path === 'chart/Chart.yaml') {
                    $content = 'dependencies: [{name: mdm-workload, version: 1.5.0}]';
                }
                if ($path === 'chart/values.yaml') {
                    $content = <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/v2"
YAML;
                }
                if ($path === 'chart/values-dev.yaml') {
                    $content = <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        probes:
          basePath: "/v3"
YAML;
                }
                return \GuzzleHttp\Promise\Create::promiseFor($content);
            });

        $this->chartParser->method('parseChartYaml')->willReturn('1.5.0');
        $this->chartParser->method('parseProbesBasePath')
            ->willReturnCallback(function (?string $content) {
                if ($content !== null && str_contains($content, '"/v3"')) return '/v3';
                if ($content !== null && str_contains($content, '"/v2"')) return '/v2';
                return null;
            });

        $savedProjects = [];
        $this->projectRepository->expects($this->once())
            ->method('updateAll')
            ->willReturnCallback(function (array $projects) use (&$savedProjects) {
                $savedProjects = $projects;
            });

        $results = $this->service->scan();

        $this->assertCount(1, $results);
        $this->assertEquals('/v3', $results[0]->getBasePaths()['dev']);
        $this->assertEquals('/v2', $results[0]->getBasePaths()['rec']);
        $this->assertEquals('/v2', $results[0]->getBasePaths()['pp']);
        $this->assertEquals('/v2', $results[0]->getBasePaths()['prod']);
    }
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `vendor/bin/phpunit --filter testScanHydratesBasePathsWithValuesAndEnvOverride`  
Expected: FAIL car `values.yaml` n'est pas encore chargé ni résolu dans `GitlabService`.

- [ ] **Step 3: Mettre à jour `GitlabService::buildProject`**

Dans `src/service/GitlabService.php` :
1. Dans `$promises` :
```php
            'values' => $this->client->getFileAsync($id, 'chart/values.yaml', true, $branch),
            'values_dev' => $this->client->getFileAsync($id, 'chart/values-dev.yaml', true, $branch),
            'values_rec' => $this->client->getFileAsync($id, 'chart/values-rec.yaml', true, $branch),
            'values_pp' => $this->client->getFileAsync($id, 'chart/values-pp.yaml', true, $branch),
            'values_prod' => $this->client->getFileAsync($id, 'chart/values-prod.yaml', true, $branch),
```
2. Dans `$files` :
```php
            'values' => $results['values']['state'] === 'fulfilled' ? $results['values']['value'] : null,
            'values_dev' => $results['values_dev']['state'] === 'fulfilled' ? $results['values_dev']['value'] : null,
            'values_rec' => $results['values_rec']['state'] === 'fulfilled' ? $results['values_rec']['value'] : null,
            'values_pp' => $results['values_pp']['state'] === 'fulfilled' ? $results['values_pp']['value'] : null,
            'values_prod' => $results['values_prod']['state'] === 'fulfilled' ? $results['values_prod']['value'] : null,
```
3. Calculer `$basePaths` :
```php
        $commonBasePath = $this->chartParser->parseProbesBasePath($files['values']);
        $basePaths = [];
        foreach (EnumEnvironment::cases() as $env) {
            $envBasePath = $this->chartParser->parseProbesBasePath($files['values_' . $env->value]);
            $basePaths[$env->value] = $envBasePath ?? $commonBasePath;
        }
```
4. Ajouter dans `$data` pour `ProjectMapper::projectFromArray($data)` :
```php
            'basePaths' => $basePaths,
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/phpunit --filter GitlabServiceTest`  
Expected: PASS

- [ ] **Step 5: Commit**

Run: `git add src/service/GitlabService.php tests/service/GitlabServiceTest.php; git commit -m "feat: orchestration du scan GitLab pour chart/values.yaml et basePaths"`

---

### Task 6: Validation globale de non-régression

**Files:** Tous les fichiers modifiés

- [ ] **Step 1: Exécuter la suite complète de tests PHPUnit**

Run: `vendor/bin/phpunit`  
Expected: All tests pass (0 failures, 0 errors).

- [ ] **Step 2: Vérifier le statut git**

Run: `git status`  
Expected: Working tree clean (sur les fichiers trackés).
