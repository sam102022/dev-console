# Spécification de Conception : Verrouillage de l'Interface Postman & Workspace Obligatoire

Date : 2026-10-12  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

L'interface de gestion des collections Postman de Dev Console (`?page=postman`) permet de créer des workspaces, des environnements et d'importer des spécifications OpenAPI. Les appels à l'API Postman nécessitent une clé d'API personnelle (`PMAK-...`) qui est configurable par chaque utilisateur dans ses paramètres de compte (`?page=settings`).

Aujourd'hui :
1. Si un utilisateur accède à la page Postman sans avoir configuré sa clé personnelle dans son profil, les formulaires restent actifs et les actions échouent silencieusement ou renvoient des erreurs génériques.
2. Lors de la création d'un environnement ou de l'import d'un fichier OpenAPI, le champ workspace est facultatif (`Sélectionnez un workspace (optionnel)`), ce qui peut conduire à des ressources orphelines ou à des erreurs d'API.

L'objectif de cette fonctionnalité est :
1. **Verrouiller l'interface Postman** si l'utilisateur connecté ne possède pas de clé d'API Postman configurée dans son profil (`users.postman_api_key`).
2. **Afficher un bandeau d'alerte explicatif** indiquant qu'il manque sa clé API et guidant l'utilisateur avec un lien direct vers la page de configuration (**Mon compte > Paramètres**).
3. **Sécuriser côté serveur (`PostmanController::handleRequest`)** les requêtes AJAX en renvoyant une réponse HTTP 403 si l'utilisateur n'a pas de clé configurée.
4. **Rendre obligatoire la sélection d'un workspace** lors de la création d'un environnement et de l'import d'un contrat OpenAPI, avec validation côté client (JavaScript) et côté serveur (HTTP 400).

---

## 2. Spécifications Fonctionnelles & UX

### 2.1 Détection de la Clé API Utilisateur
- Un utilisateur connecté (`$_SESSION['user_id']`) est considéré comme ayant configuré sa clé Postman si son enregistrement en base de données SQLite (`users`) contient une chaîne `postman_api_key` non vide (`trim($key) !== ''`).
- Si aucun utilisateur n'est connecté ou si `postman_api_key` est `null` ou vide, `has_postman_api_key` est évalué à `false`.

### 2.2 Verrouillage Visuel & Bandeau d'Alerte (`postman.html.twig`)
Lorsque `has_postman_api_key` est `false` :
- **Bandeau d'information / avertissement** en haut du corps de la page :
  - Style : `alert alert-warning` avec icône cadenas (`fas fa-lock`).
  - Message explicite : *"Clé API Postman manquante : Pour utiliser l'interface Postman, vous devez renseigner votre clé personnelle. Rendez-vous dans **Mon compte > Paramètres**."*
  - Bouton d'action direct : `<a href="?page=settings" class="btn btn-warning btn-sm font-weight-bold">Mon compte > Paramètres</a>`.
- **Overlay de verrouillage (Calque transparent)** :
  - Un conteneur parent en `position: relative` enveloppe les cartes d'actions et de listes.
  - Un calque semi-transparent (`rgba(255, 255, 255, 0.7)`, `backdrop-filter: blur(1.5px)`) recouvre l'ensemble des éléments avec `cursor: not-allowed` et un badge central discret : *"Interface verrouillée - Configurez votre clé API pour débloquer les actions"*.
- **Suppression des appels réseau inutiles au chargement** :
  - Les fonctions JS `initWorkspacesList()`, `loadWorkspaces()` et `loadTree()` ne sont exécutées que si `has_postman_api_key` est vrai.

### 2.3 Workspace Obligatoire
Lors de la création d'un environnement ou de l'import d'un fichier OpenAPI :
- Les libellés d'option par défaut deviennent : `Sélectionnez un workspace (obligatoire)`.
- Si l'utilisateur tente de valider le formulaire sans avoir sélectionné de workspace :
  - Côté client, l'action est stoppée et un message d'alerte s'affiche : *"Veuillez sélectionner un workspace (obligatoire)"*.
  - Côté serveur, si le `workspaceId` reçu est vide ou manquant, la requête est rejetée avec un statut HTTP 400 et le JSON `{"error": "Le workspace est obligatoire."}`.

---

## 3. Architecture Technique & Modifications

### 3.1 `PostmanController` (`src/controller/PostmanController.php`)
- **Injection de dépendance :**
  Injection de `App\service\RepositoryService $repositoryService` dans le constructeur.
- **Méthode privée de vérification :**
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
- **Méthode `render()` :**
  Transmission de la variable au template Twig :
  ```php
  $viewModel['has_postman_api_key'] = $this->hasUserPostmanApiKey();
  ```
- **Méthode `handleRequest(string $action, ?string $rawInput = null)` :**
  1. Vérification préalable de la clé API :
     ```php
     if (!$this->hasUserPostmanApiKey()) {
         http_response_code(403);
         return json_encode(['error' => 'Clé API Postman manquante. Veuillez la configurer dans Mon compte > Paramètres.']);
     }
     ```
  2. Validation de `workspaceId` pour les actions concernées :
     ```php
     case ACTION_POSTMAN_CREATE_ENVIRONMENT:
         $workspaceId = trim((string)($input['workspaceId'] ?? ''));
         if ($workspaceId === '') {
             http_response_code(400);
             return json_encode(['error' => 'Le workspace est obligatoire.']);
         }
         ...
     case ACTION_POSTMAN_IMPORT_OPENAPI:
         $workspaceId = trim((string)($input['workspaceId'] ?? ''));
         if ($workspaceId === '') {
             http_response_code(400);
             return json_encode(['error' => 'Le workspace est obligatoire.']);
         }
         ...
     ```

### 3.2 Template Twig (`templates/postman.html.twig`)
- Ajout du bandeau `alert alert-warning` si `not has_postman_api_key`.
- Ajout de l'overlay de verrouillage autour des sections interactives si `not has_postman_api_key`.
- Remplacement du placeholder `placeholder="ID du workspace (optionnel)"` et libellé `<option value="">Aucun workspace</option>` par `<option value="">Sélectionnez un workspace (obligatoire)</option>`.
- Conditionnement de l'exécution initiale des scripts `initWorkspacesList()`, `loadWorkspaces()` et `loadTree()` via `{% if has_postman_api_key %}`.

### 3.3 Logique JavaScript (`public/js/postman.js`)
- Dans `initWorkspacesList()` : libellé de l'option vide modifié en `<option value="">Sélectionnez un workspace (obligatoire)</option>`.
- Dans `beforeCreateEnvironment(btn)` : vérification `if (!workspaceId) return showMessage("Veuillez sélectionner un workspace (obligatoire)", "warning");`.
- Dans `importOpenApi(btn)` : vérification `if (!workspaceId) return showMessage("Veuillez sélectionner un workspace (obligatoire)", "warning");`.

---

## 4. Stratégie de Tests

1. **`tests/controller/PostmanControllerTest.php`** :
   - Tester que `index()` transmet `has_postman_api_key = false` lorsque l'utilisateur n'a pas de clé configurée en base ou n'est pas connecté.
   - Tester que `index()` transmet `has_postman_api_key = true` lorsque l'utilisateur a une clé configurée en base.
   - Tester que `handleRequest()` retourne une erreur HTTP 403 et le message approprié si l'utilisateur n'a pas de clé d'API.
   - Tester que `handleRequest()` avec action `createEnvironment` retourne un code HTTP 400 si `workspaceId` est vide.
   - Tester que `handleRequest()` avec action `importOpenApi` retourne un code HTTP 400 si `workspaceId` est vide.
   - Tester le comportement nominal (200) de `handleRequest()` lorsque la clé et le workspace sont fournis.
