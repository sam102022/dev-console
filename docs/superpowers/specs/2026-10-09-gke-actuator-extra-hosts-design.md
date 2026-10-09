# Spécification de Conception : Récupération des extraHosts Helm pour les URLs Actuator GKE

Date : 2026-10-09  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

Dans l'application Dev Console, les URLs de monitoring Spring Boot Actuator (`health`, `info`) pour les projets déployés sur Google Kubernetes Engine (GKE) étaient historiquement reconstruites selon un gabarit par défaut :
```text
https://management-<projectName>.<env>.mdm-int.net[/v1]/actuator/<endpoint>
```

Cependant, les projets déployés via le Helm Chart d'entreprise `mdm-workload` configurent explicitement leurs routes d'accès et noms d'hôtes virtuels (Istio Virtual Services) dans les fichiers de valeurs `chart/values-<env>.yaml` :
```yaml
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
```

L'objectif de cette fonctionnalité est :
1. D'extraire automatiquement `data.extraHosts` et `management-api.extraHosts` pour chaque environnement (`dev`, `rec`, `pp`, `prod`) depuis les fichiers `chart/values-<env>.yaml`.
2. De garantir l'exactitude des URLs Actuator (`urlHealthCheck` et `urlActuatorInfo`) en s'appuyant en priorité sur l'hôte déclaré dans `management-api.extraHosts`, avec repli sur `data.extraHosts`, puis sur le format historique par défaut.
3. De persister la structure complète des `extraHosts` dans l'entité/modèle `Project` et la base de données locale SQLite pour exploitation future (ex: affichage d'URLs de données, liens directs vers l'API, etc.).

---

## 2. Règles Métier & Logique de Résolution

### 2.1 Extraction des hôtes par environnement
Pour chaque projet scanné depuis GitLab ayant une configuration Helm GKE :
- Les fichiers `chart/values-dev.yaml`, `chart/values-rec.yaml`, `chart/values-pp.yaml`, et `chart/values-prod.yaml` sont chargés de façon asynchrone et concurrente.
- Pour chaque environnement, les listes d'hôtes sont extraites :
  - `management-api.extraHosts` : liste d'hôtes pour l'API de gestion (Actuator).
  - `data.extraHosts` : liste d'hôtes pour l'API applicative métier.

### 2.2 Ordre de priorité pour la construction des URLs Actuator
Lors de l'appel à `MonitoringUtils::buildUrlActuator(Project $project, ?EnumEnvironment $env, array $projectsInGke, EnumActuatorEndpoint $actuatorEndpoint)` :
1. **Priorité 1 :** Si un premier hôte non vide est présent dans `$project->getExtraHosts()[$env->value]['management-api']`, il est sélectionné :
   ```text
   https://<management-api.extraHost>/<uriActuator>
   ```
2. **Priorité 2 :** Sinon, si un premier hôte non vide est présent dans `$project->getExtraHosts()[$env->value]['data']`, il est utilisé comme repli :
   ```text
   https://<data.extraHost>/<uriActuator>
   ```
3. **Priorité 3 (Fallback historique) :** Si aucun extraHost n'est défini pour cet environnement (ou si le projet n'est pas sur GKE), la logique actuelle basée sur les gabarits `PATTERN_DOMAIN_CLOUD_GCP` ou `PATTERN_DOMAIN_RANCHER` est appliquée sans modification :
   ```text
   https://management-<projectName>.<domain><uriActuator>
   ```

### 2.3 Règle de conservation de l'URI
Le chemin de terminaison Actuator (`$uriActuator`) conserve strictement le comportement actuel validé :
- Si `$project->getPathLivenessProbe()` est défini (ex: issu d'une sonde personnalisée), c'est cette valeur qui est conservée.
- Sinon, si le nom du projet commence par `'api'`, le préfixe `/v1` est ajouté.
- Le suffixe `/actuator/{endpoint}` (`health` ou `info`) est ensuite ajouté.

---

## 3. Architecture Technique

### 3.1 Évolution de `ChartParser` (`src/parser/ChartParser.php`)

Ajout d'une méthode publique dédiée à l'analyse des fichiers de valeurs Helm :
```php
/**
 * Analyse le contenu d'un fichier values-<env>.yaml pour extraire les extraHosts de mdm-workload.
 *
 * @param string $content Le contenu YAML du fichier de values.
 * @return array{data: list<string>, management-api: list<string>}
 */
public function parseValuesYaml(string $content): array
```
- Robustesse : si `$content` est vide ou si une `ParseException` survient, la méthode retourne `['data' => [], 'management-api' => []]`.
- Navigation : extraction sécurisée sous `mdm-workload` > `istioVirtualServices`.
- Normalisation : conversion systématique en liste de chaînes de caractères nettoyées (`array_values(array_filter(...))`).

### 3.2 Modèle & Entité `Project` (`src/model/Project.php`, `src/repository/model/ProjectEntity.php`)

Ajout d'une propriété `$extraHosts` :
```php
public array $extraHosts = [];
```
Structure du tableau associatif :
```php
[
    'dev' => [
        'data' => ['api-store-stock.dev.mdm-int.net'],
        'management-api' => ['management-api-store-stock.dev.mdm-int.net'],
    ],
    'rec' => [ ... ],
    'pp'  => [ ... ],
    'prod' => [ ... ],
]
```
- Méthodes `getExtraHosts(): array` et `setExtraHosts(array $extraHosts): self` (ou `void` pour l'entité).

### 3.3 Mapping (`src/repository/mapper/ProjectMapper.php`)

Prise en compte de `$extraHosts` dans :
- `fromEntity(ProjectEntity $entity): Project`
- `toEntity(Project $project): ProjectEntity`
- `projectFromArray(array $data): Project`
- `entityFromArray(array $data): ProjectEntity`
- `toArray(ProjectEntity $projectEntity): array`

### 3.4 Persistance SQLite (`src/service/RepositoryService.php`)

- Déclaration de la colonne `extraHosts TEXT` dans la définition DDL de la table `projects`.
- Ajout d'une vérification d'évolution de schéma automatique lors de l'initialisation (`ALTER TABLE projects ADD COLUMN extraHosts TEXT` si la colonne n'est pas encore présente).
- Encodage JSON lors de l'insertion / mise à jour (`:extraHosts => json_encode($proj['extraHosts'] ?? [])`).
- Décodage JSON lors de la lecture (`$row['extraHosts'] = json_decode($row['extraHosts'] ?? '[]', true)`).

### 3.5 Orchestration du scan (`src/service/GitlabService.php`)

Dans `GitlabService::buildProject` :
- Les fichiers de valeurs pour chaque environnement (`dev`, `rec`, `pp`, `prod`) sont ajoutés aux promesses asynchrones de Guzzle :
  ```php
  'values_dev'  => $this->client->getFileAsync($id, 'chart/values-dev.yaml', true, $branch),
  'values_rec'  => $this->client->getFileAsync($id, 'chart/values-rec.yaml', true, $branch),
  'values_pp'   => $this->client->getFileAsync($id, 'chart/values-pp.yaml', true, $branch),
  'values_prod' => $this->client->getFileAsync($id, 'chart/values-prod.yaml', true, $branch),
  ```
- Après résolution (`settle`), pour chaque environnement :
  ```php
  $extraHosts = [];
  foreach (EnumEnvironment::cases() as $env) {
      $valKey = 'values_' . $env->value;
      $yamlContent = $files[$valKey] ?? null;
      if ($yamlContent) {
          $extraHosts[$env->value] = $this->chartParser->parseValuesYaml($yamlContent);
      }
  }
  ```
- `$data['extraHosts'] = $extraHosts;` est transmis à `ProjectMapper::projectFromArray($data)`.
- La boucle de construction des URLs (`urlsHealth`, `urlsActuatorInfo`) appelle `MonitoringUtils::buildUrlActuator...` qui utilise le `$project` déjà hydraté avec ses `extraHosts`.

### 3.6 Logique d'URL Actuator (`src/util/MonitoringUtils.php`)

Dans `MonitoringUtils::buildUrlActuator` :
```php
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

// Fallback historique si aucun hôte explicite
```

---

## 4. Gestion des Erreurs & Cas Limites

1. **Fichiers de values inexistants (404) :**
   Les promesses Guzzle pour des fichiers absents sont rejetées gracieusement sans bloquer le scan des autres fichiers ou projets.
2. **Fichiers YAML syntaxiquement invalides :**
   `ChartParser::parseValuesYaml` capture toute exception et retourne un tableau vide par défaut.
3. **Format partiel ou hétérogène :**
   Si `istioVirtualServices` ne contient que `data` ou que `management-api`, la normalisation garantit que les deux clés existent toujours dans le tableau de retour avec un tableau vide `[]` par défaut si non déclarées.
4. **Rétro-compatibilité :**
   Les projets Rancher, les projets non GKE, ou les projets sans `values-<env>.yaml` continuent d'utiliser la génération d'URL historique sans aucune régression.

---

## 5. Stratégie de Validation & Tests

### 5.1 Tests Unitaires
1. **`tests/parser/ChartParserTest.php`** :
   - Test avec YAML complet contenant `data.extraHosts` et `management-api.extraHosts`.
   - Test avec seulement `management-api.extraHosts`.
   - Test avec seulement `data.extraHosts`.
   - Test avec clés manquantes ou YAML vide/invalide.
2. **`tests/util/MonitoringUtilsTest.php`** :
   - Test `buildUrlActuator` avec `management-api.extraHosts` prioritaire.
   - Test `buildUrlActuator` avec repli sur `data.extraHosts`.
   - Test `buildUrlActuator` sans `extraHosts` (fallback historique conservé).
   - Test avec sonder livenessProbe personnalisée.
3. **`tests/repository/mapper/ProjectMapperTest.php`** :
   - Vérification du cycle complet de mapping de la propriété `extraHosts`.
4. **`tests/service/RepositoryServiceTest.php`** :
   - Vérification de la création de table, persistance et relecture SQLite pour `extraHosts`.
5. **`tests/service/GitlabServiceTest.php`** :
   - Vérification des promesses parallèles pour `values_dev`, `values_rec`, `values_pp`, `values_prod`.
   - Vérification de l'hydratation de `extraHosts` sur le projet final.

### 5.2 Validation Globale
- Exécution de la suite complète PHPUnit (`vendor/bin/phpunit`) pour vérifier l'absence totale de régressions sur les 552 tests existants.
