# GKE Actuator extraHosts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extraire les champs `data.extraHosts` et `management-api.extraHosts` des fichiers `chart/values-<env>.yaml` pour garantir les URLs Actuator exactes et persister ces hôtes dans les projets GKE.

**Architecture:** Enrichir `ChartParser` avec la méthode `parseValuesYaml`, adapter le modèle `Project`/`ProjectEntity`/`ProjectMapper` et la table SQLite `projects` pour stocker `extraHosts`, faire évoluer `MonitoringUtils::buildUrlActuator` avec priorité `management-api` > `data` > fallback historique, et orchestrer le chargement parallèle des values dans `GitlabService`.

**Tech Stack:** PHP 8.4, Symfony Components (Dependency Injection, Yaml), PHPUnit 11, SQLite (PDO), GuzzleHttp.

---

### Task 1: Parsing values YAML dans `ChartParser`

**Files:**
- Modify: `src/parser/ChartParser.php`
- Test: `tests/parser/ChartParserTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour `parseValuesYaml`**

Ajouter dans `tests/parser/ChartParserTest.php` les cas de test vérifiant l'extraction de `data.extraHosts` et `management-api.extraHosts` :

```php
    public static function valuesYamlContentProvider(): array
    {
        return [
            'valid values with both data and management-api extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment:
      container:
        env:
  istioVirtualServices:
    data:
      extraHosts:
        - api-store-stock.dev.mdm-int.net
    management-api:
      extraHosts:
        - management-api-store-stock.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => ['api-store-stock.dev.mdm-int.net'],
                    'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
                ],
            ],
            'values with only management-api extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  istioVirtualServices:
    management-api:
      extraHosts:
        - management-api-only.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => [],
                    'management-api' => ['management-api-only.dev.mdm-int.net'],
                ],
            ],
            'values with only data extraHosts' => [
                'content' => <<<YAML
mdm-workload:
  istioVirtualServices:
    data:
      extraHosts:
        - api-data-only.dev.mdm-int.net
YAML,
                'expected' => [
                    'data' => ['api-data-only.dev.mdm-int.net'],
                    'management-api' => [],
                ],
            ],
            'values without istioVirtualServices' => [
                'content' => <<<YAML
mdm-workload:
  config:
    deployment: {}
YAML,
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
            'empty content' => [
                'content' => '',
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
            'malformed yaml content' => [
                'content' => "mdm-workload: [unclosed",
                'expected' => [
                    'data' => [],
                    'management-api' => [],
                ],
            ],
        ];
    }

    #[DataProvider('valuesYamlContentProvider')]
    final public function testParseValuesYaml(string $content, array $expected): void
    {
        $this->assertEquals($expected, $this->parser->parseValuesYaml($content));
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Run: `vendor/bin/phpunit --filter testParseValuesYaml`
Expected: FAIL avec "Error: Call to undefined method App\parser\ChartParser::parseValuesYaml()"

- [ ] **Step 3: Implémenter `parseValuesYaml` dans `ChartParser`**

Ajouter la méthode suivante dans `src/parser/ChartParser.php` :

```php
    /**
     * Analyse le contenu d'un fichier values-<env>.yaml pour extraire les extraHosts de mdm-workload.
     *
     * @param string $content Le contenu du fichier values-<env>.yaml.
     * @return array{data: list<string>, management-api: list<string>}
     */
    public function parseValuesYaml(string $content): array
    {
        $default = [
            'data' => [],
            'management-api' => [],
        ];

        if (trim($content) === '') {
            return $default;
        }

        try {
            $parsed = YamlParser::parse($content);
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($parsed)) {
            return $default;
        }

        $istio = $parsed['mdm-workload']['istioVirtualServices'] ?? [];
        if (!is_array($istio)) {
            return $default;
        }

        $extractHosts = static function (mixed $section): array {
            if (!is_array($section)) {
                return [];
            }
            $extraHosts = $section['extraHosts'] ?? [];
            if (!is_array($extraHosts)) {
                return [];
            }
            return array_values(array_filter(
                array_map('strval', $extraHosts),
                static fn(string $h): bool => trim($h) !== ''
            ));
        };

        return [
            'data' => $extractHosts($istio['data'] ?? null),
            'management-api' => $extractHosts($istio['management-api'] ?? null),
        ];
    }
```

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Run: `vendor/bin/phpunit --filter testParseValuesYaml`
Expected: PASS (6 tests, 6 assertions)

- [ ] **Step 5: Committer le code**

Run: `git add src/parser/ChartParser.php tests/parser/ChartParserTest.php; git commit -m "feat: ajout parseValuesYaml dans ChartParser"`

---

### Task 2: Modèle, Entité et Mapper pour `extraHosts`

**Files:**
- Modify: `src/model/Project.php`
- Modify: `src/repository/model/ProjectEntity.php`
- Modify: `src/repository/mapper/ProjectMapper.php`
- Modify: `tests/repository/mapper/ProjectMapperTest.php`

- [ ] **Step 1: Écrire le test unitaire pour `extraHosts` dans `ProjectMapperTest`**

Dans `tests/repository/mapper/ProjectMapperTest.php`, ajouter `'extraHosts'` dans `getSampleProjectData()` et un test spécifique `testExtraHostsMapping` :

```php
    // Dans getSampleProjectData() :
    'extraHosts' => [
        'dev' => [
            'data' => ['api-store-stock.dev.mdm-int.net'],
            'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
        ],
    ],
```

Ajouter la méthode de test :
```php
    final public function testExtraHostsMapping(): void
    {
        $data = $this->getSampleProjectData();
        $project = ProjectMapper::projectFromArray($data);
        $this->assertEquals($data['extraHosts'], $project->getExtraHosts());

        $entity = ProjectMapper::toEntity($project);
        $this->assertEquals($data['extraHosts'], $entity->getExtraHosts());

        $recreatedProject = ProjectMapper::fromEntity($entity);
        $this->assertEquals($data['extraHosts'], $recreatedProject->getExtraHosts());

        $array = ProjectMapper::toArray($entity);
        $this->assertEquals($data['extraHosts'], $array['extraHosts']);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Run: `vendor/bin/phpunit --filter testExtraHostsMapping`
Expected: FAIL avec "Error: Call to undefined method App\model\Project::getExtraHosts()"

- [ ] **Step 3: Mettre à jour `Project`, `ProjectEntity`, et `ProjectMapper`**

1. Dans `src/model/Project.php` :
   - Ajouter `public array $extraHosts = [];`
   - Ajouter :
     ```php
     public function getExtraHosts(): array
     {
         return $this->extraHosts;
     }

     public function setExtraHosts(array $extraHosts): self
     {
         $this->extraHosts = $extraHosts;
         return $this;
     }
     ```
   - Mettre à jour `createProject` et `toArray` pour inclure `extraHosts`.

2. Dans `src/repository/model/ProjectEntity.php` :
   - Ajouter `private array $extraHosts = [];`
   - Ajouter :
     ```php
     public function getExtraHosts(): array
     {
         return $this->extraHosts;
     }

     public function setExtraHosts(array $extraHosts): void
     {
         $this->extraHosts = $extraHosts;
     }
     ```
   - Mettre à jour `createProjectEntity` pour inclure `array $extraHosts = []`.

3. Dans `src/repository/mapper/ProjectMapper.php` :
   - Dans `projectEntityFromArray` : `$projectEntity->setExtraHosts($data['extraHosts'] ?? []);`
   - Dans `projectFromArray` : `$project->setExtraHosts($data['extraHosts'] ?? []);`
   - Dans `fromEntity` : `$project->setExtraHosts($entity->getExtraHosts());`
   - Dans `toEntity` : passer `$project->getExtraHosts()`
   - Dans `toArray` : `'extraHosts' => $projectEntity->getExtraHosts(),`

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Run: `vendor/bin/phpunit --filter ProjectMapperTest`
Expected: PASS

- [ ] **Step 5: Committer le code**

Run: `git add src/model/Project.php src/repository/model/ProjectEntity.php src/repository/mapper/ProjectMapper.php tests/repository/mapper/ProjectMapperTest.php; git commit -m "feat: gestion de extraHosts dans Project, ProjectEntity et ProjectMapper"`

---

### Task 3: Persistance SQLite de `extraHosts` dans `RepositoryService`

**Files:**
- Modify: `src/service/RepositoryService.php`
- Modify: `tests/service/RepositoryServiceTest.php`

- [ ] **Step 1: Écrire le test d'intégration pour `extraHosts` dans `RepositoryServiceTest`**

Dans `tests/service/RepositoryServiceTest.php`, dans `testProjectOperations()`, enrichir le tableau d'entrée `$projects` avec `extraHosts` et vérifier qu'il est correctement rechargé :

```php
        'extraHosts' => [
            'dev' => [
                'data' => ['api-a.dev.mdm-int.net'],
                'management-api' => ['management-api-a.dev.mdm-int.net'],
            ],
        ],
```
Et ajouter l'assertion :
```php
        $this->assertEquals([
            'dev' => [
                'data' => ['api-a.dev.mdm-int.net'],
                'management-api' => ['management-api-a.dev.mdm-int.net'],
            ],
        ], $loadedProjects[0]['extraHosts']);
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Run: `vendor/bin/phpunit --filter testProjectOperations`
Expected: FAIL car `extraHosts` n'est pas encore persisté dans la base SQLite.

- [ ] **Step 3: Mettre à jour `RepositoryService` pour persister `extraHosts`**

1. Dans `src/service/RepositoryService.php` :
   - Dans `initDatabase()` :
     - Ajouter `extraHosts TEXT,` dans la requête `CREATE TABLE IF NOT EXISTS projects (...)`.
     - Ajouter une vérification / migration douce de schéma :
       ```php
       $cols = $this->db->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_COLUMN, 1);
       if (!in_array('extraHosts', $cols, true)) {
           $this->db->exec("ALTER TABLE projects ADD COLUMN extraHosts TEXT");
       }
       ```
   - Dans `saveProjects(array $projects)` et `saveProject(array $proj)` :
     - Ajouter `extraHosts` dans la liste des colonnes de `INSERT INTO projects (...)`.
     - Ajouter `:extraHosts` dans les paramètres préparés :
       `':extraHosts' => json_encode($proj['extraHosts'] ?? []),`
     - Ajouter `extraHosts = excluded.extraHosts` dans le bloc `ON CONFLICT(name) DO UPDATE SET`.
   - Dans `getProjects()`, `findProjectByName()`, `findProjectsByNames()` :
     - Décoder le champ JSON :
       `$row['extraHosts'] = json_decode($row['extraHosts'] ?? '[]', true);`

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Run: `vendor/bin/phpunit --filter testProjectOperations`
Expected: PASS

- [ ] **Step 5: Committer le code**

Run: `git add src/service/RepositoryService.php tests/service/RepositoryServiceTest.php; git commit -m "feat: persistance sqlite de extraHosts dans RepositoryService"`

---

### Task 4: Résolution d'URLs Actuator dans `MonitoringUtils`

**Files:**
- Modify: `src/util/MonitoringUtils.php`
- Modify: `tests/util/MonitoringUtilsTest.php`

- [ ] **Step 1: Écrire les tests unitaires pour la priorité d'hôte dans `MonitoringUtilsTest`**

Dans `tests/util/MonitoringUtilsTest.php`, ajouter des cas dans `buildUrlActuatorHealthProvider` et `buildUrlActuatorInfoProvider` :

```php
            'GKE project with management-api extraHost' => [
                ProjectFixtures::getMonitoringProject('api-store-stock', true)
                    ->setExtraHosts([
                        'dev' => [
                            'data' => ['api-store-stock.dev.mdm-int.net'],
                            'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
                        ],
                    ]),
                EnumEnvironment::DEV,
                [],
                'https://management-api-store-stock.dev.mdm-int.net/v1/actuator/health'
            ],
            'GKE project with only data extraHost fallback' => [
                ProjectFixtures::getMonitoringProject('api-store-stock', true)
                    ->setExtraHosts([
                        'dev' => [
                            'data' => ['api-store-stock.dev.mdm-int.net'],
                            'management-api' => [],
                        ],
                    ]),
                EnumEnvironment::DEV,
                [],
                'https://api-store-stock.dev.mdm-int.net/v1/actuator/health'
            ],
            'GKE project with extraHost and custom pathLivenessProbe' => [
                ProjectFixtures::getMonitoringProject('api-store-stock', true)
                    ->setExtraHosts([
                        'dev' => [
                            'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
                        ],
                    ])
                    ->setPathLivenessProbe('/custom/health'),
                EnumEnvironment::DEV,
                [],
                'https://management-api-store-stock.dev.mdm-int.net/custom/health'
            ],
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Run: `vendor/bin/phpunit --filter testBuildUrlHealthCheck`
Expected: FAIL car `MonitoringUtils::buildUrlActuator` n'utilise pas encore `extraHosts`.

- [ ] **Step 3: Mettre à jour `MonitoringUtils::buildUrlActuator`**

Dans `src/util/MonitoringUtils.php`, modifier `buildUrlActuator` :

```php
    public static function buildUrlActuator(Project $project, ?EnumEnvironment $env, array $projectsInGke, EnumActuatorEndpoint $actuatorEndpoint): string
    {
        $projectName = $project->getname();
        $cloudGCP = $project->isCloudGCP();

        if ($project->getPathLivenessProbe()) {
            $uriActuator = $project->getPathLivenessProbe();
        } else {
            $uriActuator = '';
            if (str_starts_with($projectName, 'api')) {
                $uriActuator .= '/v1';
            }
            $uriActuator .= '/actuator/' . $actuatorEndpoint->value;
        }

        // Vérification de la présence d'extraHosts pour l'environnement
        $envKey = $env?->value ?? '';
        $envExtraHosts = $project->getExtraHosts()[$envKey] ?? [];

        $host = null;
        if (!empty($envExtraHosts['management-api'][0])) {
            $host = $envExtraHosts['management-api'][0];
        } elseif (!empty($envExtraHosts['data'][0])) {
            $host = $envExtraHosts['data'][0];
        }

        if ($host !== null) {
            return 'https://' . rtrim($host, '/') . '/' . ltrim($uriActuator, '/');
        }

        // Construction historique par défaut si aucun extraHost configuré
        $envLocal = $env?->value ?? '';
        if ($cloudGCP) {
            $domain = self::PATTERN_DOMAIN_CLOUD_GCP;
        } else {
            $domain = self::PATTERN_DOMAIN_RANCHER;
            if ($env && $env->value === EnumEnvironment::PROD->value && in_array($projectName, $projectsInGke, true)) {
                $domain = self::PATTERN_DOMAIN_CLOUD_GCP;
            } else {
                $domain = $project->getSf() . "." . $domain;
                $envLocal = ($env && $env->value === EnumEnvironment::PROD->value) ? '' : '-' . ($env ? $env->value : '');
            }
        }

        $urlHealthCheck = "https://management-$projectName.$domain$uriActuator";

        return sprintf($urlHealthCheck, $envLocal);
    }
```

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Run: `vendor/bin/phpunit --filter testBuildUrlHealthCheck`
Expected: PASS

- [ ] **Step 5: Committer le code**

Run: `git add src/util/MonitoringUtils.php tests/util/MonitoringUtilsTest.php; git commit -m "feat: résolution prioritaire des URLs Actuator via extraHosts dans MonitoringUtils"`

---

### Task 5: Scan asynchrone et hydratation dans `GitlabService`

**Files:**
- Modify: `src/service/GitlabService.php`
- Modify: `tests/service/GitlabServiceTest.php`

- [ ] **Step 1: Écrire le test unitaire pour le scan des `chart/values-<env>.yaml` dans `GitlabServiceTest`**

Dans `tests/service/GitlabServiceTest.php`, mettre à jour ou ajouter une méthode `testScanHydratesExtraHostsFromValues()` :

```php
    final public function testScanHydratesExtraHostsFromValues(): void
    {
        $this->projectRepository->method('findAll')->willThrowException(new TechnicalException("Vide"));
        $gitlabProjects = [GitlabProjectEntityFixtures::getGitlabProjectEntityA()];
        $this->gitLabRepository->method('findAll')->willReturn($gitlabProjects);

        $this->client->method('getFileAsync')
            ->willReturnCallback(function ($id, $path, $raw, $branch) {
                $content = null;
                if ($path === 'chart/Chart.yaml') {
                    $content = "dependencies:\n  - name: mdm-workload\n    version: 1.5.0";
                } elseif ($path === 'chart/values-dev.yaml') {
                    $content = "mdm-workload:\n  istioVirtualServices:\n    management-api:\n      extraHosts:\n        - management-api-a.dev.mdm-int.net";
                }
                return \GuzzleHttp\Promise\Create::promiseFor($content);
            });

        $this->chartParser->method('parseChartYaml')->willReturn('1.5.0');
        $this->chartParser->method('parseValuesYaml')->willReturn([
            'data' => [],
            'management-api' => ['management-api-a.dev.mdm-int.net'],
        ]);

        $this->projectRepository->expects($this->once())->method('updateAll')
            ->with($this->callback(function (array $projects) {
                /** @var Project $project */
                $project = $projects[0];
                $extraHosts = $project->getExtraHosts();
                return isset($extraHosts['dev']['management-api'])
                    && $extraHosts['dev']['management-api'][0] === 'management-api-a.dev.mdm-int.net'
                    && str_contains($project->getUrlHealthCheck()['dev'], 'management-api-a.dev.mdm-int.net');
            }));

        $results = $this->service->scan();
        $this->assertNotEmpty($results);
    }
```

- [ ] **Step 2: Exécuter le test pour vérifier l'échec**

Run: `vendor/bin/phpunit --filter testScanHydratesExtraHostsFromValues`
Expected: FAIL car `GitlabService` ne charge pas encore tous les `values-<env>.yaml` et n'hydrate pas `extraHosts`.

- [ ] **Step 3: Mettre à jour `GitlabService` pour charger et analyser les values de chaque environnement**

Dans `src/service/GitlabService.php` :
1. Dans `$promises` :
   ```php
   'values_dev'  => $this->client->getFileAsync($id, 'chart/values-dev.yaml', true, $branch),
   'values_rec'  => $this->client->getFileAsync($id, 'chart/values-rec.yaml', true, $branch),
   'values_pp'   => $this->client->getFileAsync($id, 'chart/values-pp.yaml', true, $branch),
   'values_prod' => $this->client->getFileAsync($id, 'chart/values-prod.yaml', true, $branch),
   ```
2. Dans `$files` :
   ```php
   'values_dev'  => $results['values_dev']['state'] === 'fulfilled' ? $results['values_dev']['value'] : null,
   'values_rec'  => $results['values_rec']['state'] === 'fulfilled' ? $results['values_rec']['value'] : null,
   'values_pp'   => $results['values_pp']['state'] === 'fulfilled' ? $results['values_pp']['value'] : null,
   'values_prod' => $results['values_prod']['state'] === 'fulfilled' ? $results['values_prod']['value'] : null,
   ```
3. Extraire les `extraHosts` :
   ```php
   $extraHosts = [];
   foreach (EnumEnvironment::cases() as $env) {
       $valKey = 'values_' . $env->value;
       $valContent = $files[$valKey] ?? null;
       if ($valContent) {
           $extraHosts[$env->value] = $this->chartParser->parseValuesYaml($valContent);
       }
   }
   ```
4. Ajouter `'extraHosts' => $extraHosts,` dans `$data` passé à `ProjectMapper::projectFromArray($data)`.
5. Dans `getSubscriptionName(array $files)`, laisser l'utilisation de `$files['values_dev']` qui fonctionne à l'identique.

- [ ] **Step 4: Exécuter le test pour vérifier le succès**

Run: `vendor/bin/phpunit --filter testScanHydratesExtraHostsFromValues`
Expected: PASS

- [ ] **Step 5: Committer le code**

Run: `git add src/service/GitlabService.php tests/service/GitlabServiceTest.php; git commit -m "feat: scan asynchrone des values-<env>.yaml et hydratation de extraHosts dans GitlabService"`

---

### Task 6: Validation globale de non-régression

**Files:**
- Test: Exécution globale de tous les tests

- [ ] **Step 1: Lancer la suite complète de tests PHPUnit**

Run: `vendor/bin/phpunit`
Expected: Tous les tests (553+) passent avec succès sans aucune régression.

- [ ] **Step 2: Vérifier le statut git**

Run: `git status`
Expected: Working tree clean (sauf fichiers non trackés pré-existants ignorés).
