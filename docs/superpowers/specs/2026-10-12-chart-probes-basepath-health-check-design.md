# Spécification de Conception : Récupération du probes.basePath Helm pour l'URI Health Check & Actuator

Date : 2026-10-12  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

Dans l'application Dev Console, les URLs de monitoring Spring Boot Actuator (`health`, `info`) pour les projets déployés sur Google Kubernetes Engine (GKE) via Helm s'appuyaient jusqu'à présent sur un préfixe d'API codé en dur `/v1` pour les projets dont le nom commence par `api-` :
```text
https://management-<projectName>.<env>.mdm-int.net/v1/actuator/<endpoint>
```

Cependant, les projets déployés via le Helm Chart d'entreprise `mdm-workload` configurent explicitement le chemin de base des sondes de vie/disponibilité (liveness / readiness probes) dans le fichier de valeurs Helm `chart/values.yaml` (et optionnellement surchargé dans `chart/values-<env>.yaml`) :
```yaml
mdm-workload:
  config:
    deployment:
      # Container java configuration
      container:
        # common Env variables dans le container
        env:
        probes:
          basePath: "/v2"
```

L'objectif de cette fonctionnalité est :
1. D'extraire automatiquement `mdm-workload.config.deployment.container.probes.basePath` depuis `chart/values.yaml` (valeur commune partagée) et depuis les fichiers d'environnement `chart/values-<env>.yaml` (`dev`, `rec`, `pp`, `prod`).
2. De résoudre le `basePath` effectif pour chaque environnement selon la règle : **surcharge par environnement (`values-<env>.yaml`) > valeur commune (`values.yaml`) > `null`**.
3. De persister la structure résolue des `basePaths` (`['dev' => '/v2', 'rec' => '/v2', ...]`) dans l'entité/modèle `Project` et la base de données SQLite locale (`projects.basePaths`).
4. D'utiliser ce `basePath` dans la génération des URLs Actuator (`urlHealthCheck` et `urlActuatorInfo`) dans `MonitoringUtils::buildUrlActuator` en remplacement du préfixe `/v1` par défaut.

---

## 2. Règles Métier & Logique de Résolution

### 2.1 Extraction et Normalisation du `basePath`
La méthode d'extraction analyse la structure YAML sous :
`mdm-workload` > `config` > `deployment` > `container` > `probes` > `basePath`

Règles de normalisation de la valeur extraite :
- Si la chaîne est vide ou non définie, retourner `null`.
- Nettoyage des espaces (`trim`).
- Suppression du slash final éventuel (ex: `"/v2/"` devient `"/v2"`).
- Ajout d'un slash initial s'il est manquant (ex: `"v2"` devient `"/v2"`).
- Si le YAML est vide ou malformé, retourner `null` sans lever d'exception.

### 2.2 Résolution du `basePath` par Environnement
Lors du scan GitLab dans `GitlabService` :
- Le fichier `chart/values.yaml` est chargé en parallèle des fichiers `chart/values-<env>.yaml` (`dev`, `rec`, `pp`, `prod`).
- Pour chaque environnement `$env` (`EnumEnvironment::cases()`) :
  - Si un `basePath` est extrait de `chart/values-<env>.yaml`, il est retenu en priorité.
  - Sinon, si un `basePath` est extrait du fichier commun `chart/values.yaml`, il est utilisé.
  - Sinon, la valeur pour cet environnement est `null`.
- Le résultat est stocké sous forme de dictionnaire associatif :
  ```php
  [
      'dev' => '/v2',
      'rec' => '/v2',
      'pp'  => '/v2',
      'prod' => '/v2',
  ]
  ```

### 2.3 Ordre de Priorité pour l'URI Actuator (`MonitoringUtils::buildUrlActuator`)
Pour déterminer l'URI du endpoint Actuator (`$uriActuator`) :
1. **Priorité 1 :** Si `$project->getPathLivenessProbe()` est défini (ex: sonde personnalisée extraite du fichier legacy `deploy.yml`), cette URI complète est conservée telle quelle (rétrocompatibilité historique).
2. **Priorité 2 :** Si un `basePath` est défini pour l'environnement concerné dans `$project->getBasePaths()[$env->value]`, l'URI devient :
   ```text
   <basePath>/actuator/<endpoint>
   ```
   *(Exemple : `/v2/actuator/health` ou `/v2/actuator/info`)*
3. **Priorité 3 (Fallback historique) :** Si aucun `basePath` n'est défini :
   - Si `$projectName` commence par `'api'`, le préfixe `/v1` est appliqué : `/v1/actuator/<endpoint>`.
   - Sinon, aucun préfixe : `/actuator/<endpoint>`.

---

## 3. Architecture Technique

### 3.1 Évolution de `ChartParser` (`src/parser/ChartParser.php`)

Ajout d'une méthode publique :
```php
/**
 * Analyse le contenu d'un fichier values.yaml ou values-<env>.yaml pour extraire le probes.basePath.
 *
 * @param string|null $content Le contenu YAML du fichier de values.
 * @return string|null Le basePath normalisé (ex: "/v2"), ou null s'il n'est pas défini.
 */
public function parseProbesBasePath(?string $content): ?string
```

### 3.2 Modèle & Entité `Project` (`src/model/Project.php`, `src/repository/model/ProjectEntity.php`)

Ajout d'une propriété `$basePaths` :
```php
/** @var array<string, string> */
public array $basePaths = [];
```
- Méthodes `getBasePaths(): array` et `setBasePaths(array $basePaths): self` (ou `void` pour l'entité).
- Intégration dans le constructeur statique `fromValues`.

### 3.3 Mapping (`src/repository/mapper/ProjectMapper.php`)

Prise en compte de `$basePaths` dans :
- `fromEntity(ProjectEntity $entity): Project`
- `toEntity(Project $project): ProjectEntity`
- `projectFromArray(array $data): Project`
- `entityFromArray(array $data): ProjectEntity`
- `toArray(ProjectEntity $projectEntity): array`

### 3.4 Persistance SQLite (`src/service/RepositoryService.php`)

- Déclaration de la colonne `basePaths TEXT` dans la définition DDL de la table `projects`.
- Vérification automatique de schéma lors de l'initialisation de la BDD :
  `ALTER TABLE projects ADD COLUMN basePaths TEXT` (ignoré silencieusement si la colonne existe déjà).
- Encodage JSON lors de l'insertion et de la mise à jour (`:basePaths => json_encode($proj['basePaths'] ?? [])`).
- Décodage JSON lors de la lecture (`$row['basePaths'] = json_decode($row['basePaths'] ?? '[]', true)`).

### 3.5 Orchestration du scan (`src/service/GitlabService.php`)

Dans `GitlabService::buildProject` :
- Ajout de `chart/values.yaml` dans les requêtes asynchrones de GitLab :
  ```php
  'values' => $this->client->getFileAsync($id, 'chart/values.yaml', true, $branch),
  ```
- Chargement des fichiers de values par environnement (`values_dev`, `values_rec`, `values_pp`, `values_prod`).
- Calcul du tableau `$basePaths` résolu pour les 4 environnements.
- Injection dans le tableau de données du projet :
  ```php
  $data['basePaths'] = $basePaths;
  ```
- Appel à `MonitoringUtils::buildUrlActuator...` qui consomme `$project->getBasePaths()`.

### 3.6 Logique d'URL Actuator (`src/util/MonitoringUtils.php`)

Mise à jour de `MonitoringUtils::buildUrlActuator` :
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

---

## 4. Stratégie de Tests

1. **`tests/parser/ChartParserTest.php`** :
   - YAML complet avec `probes.basePath: "/v2"` -> `"/v2"`.
   - Normalisation sans slash initial : `"v2"` -> `"/v2"`.
   - Normalisation avec slash terminal : `"/v2/"` -> `"/v2"`.
   - YAML sans `probes` ou sans `basePath` -> `null`.
   - Contenu vide ou YAML invalide -> `null`.

2. **`tests/util/MonitoringUtilsTest.php`** :
   - Projet API avec `basePath: '/v2'` -> `https://management-api-xxx.dev.mdm-int.net/v2/actuator/health` et `/v2/actuator/info`.
   - Projet API sans `basePath` -> fallback `/v1/actuator/health`.
   - Projet non-API avec `basePath: '/v2'` -> `/v2/actuator/health`.
   - Projet avec `pathLivenessProbe` personnalisé -> `pathLivenessProbe` prioritaire sur `basePath`.

3. **`tests/repository/mapper/ProjectMapperTest.php`** :
   - Aller-retour `Project` <-> `ProjectEntity` conservant fidèlement le dictionnaire `basePaths`.

4. **`tests/service/GitlabServiceTest.php`** :
   - Projet avec `chart/values.yaml` ayant `basePath: "/v2"` et `chart/values-dev.yaml` sans surcharge -> `basePaths['dev'] === '/v2'`.
   - Projet avec surcharge environnementale `values-dev.yaml` (`"/v3"`) vs `values.yaml` (`"/v2"`) -> `basePaths['dev'] === '/v3'`, `basePaths['prod'] === '/v2'`.
