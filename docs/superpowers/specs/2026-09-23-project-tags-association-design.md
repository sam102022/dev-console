# Spécification Technique & Fonctionnelle : Association de Projets par Tags Libres

**Date :** 2026-09-23  
**Statut :** Validé  
**Auteur :** Gemini CLI & Utilisateur  

---

## 1. Contexte & Objectifs

Dans l'application **dev-console**, les utilisateurs doivent pouvoir associer certains projets entre eux via des étiquettes (tags / mots-clés libres) afin de retrouver facilement tous les projets liés à un même domaine fonctionnel, une refonte technique, ou un écosystème applicatif partagé (ex: `paiement`, `omnichannel`, `flux-commandes`, `refonte-2026`).

### Objectifs principaux :
1. **Association par tags libres** : possibilité d'associer un ou plusieurs tags à n'importe quel projet.
2. **Persistance robuste** : les tags doivent être conservés lors des scans GitLab (qui réinitialisent la table `projects`).
3. **Contrôle d'accès (RBAC)** :
   - Visibilité : **globale** (tous les utilisateurs connectés voient les tags).
   - Gestion (ajout/suppression) : réservée aux utilisateurs ayant le rôle **`ROLE_ADMIN`**.
4. **Expérience utilisateur fluide** :
   - Affichage sous le nom du projet dans le tableau principal.
   - Édition *inline* directe pour les administrateurs (champ compact avec validation `Entrée` et suppression par clic sur une croix `×`).
   - Filtrage instantané : clic direct sur un badge de tag pour filtrer le tableau, et intégration des tags dans le filtre textuel de la colonne "Projet".

---

## 2. Modèle de Données & Persistance

### 2.1 Table SQLite `project_tags`
La gestion des tags repose sur une table SQLite dédiée, créée automatiquement à l'initialisation dans `RepositoryService` :

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

**Propriété clé :** Cette table est complètement distincte de `projects`. Lors de l'exécution de `ScanCommand` ou du rafraîchissement des projets (`DELETE FROM projects`), la table `project_tags` n'est pas altérée.

### 2.2 Modèles PHP & Mappers
- **`App\repository\model\ProjectEntity`** :
  - Ajout de la propriété `private array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\model\Project`** :
  - Ajout de la propriété `public array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\repository\mapper\ProjectMapper`** :
  - Prise en charge du champ `tags` lors des conversions `projectEntityFromArray`, `toArray`, `fromEntity`, `projectFromArray`.
  - Normalisation des tags (suppression des espaces superflus, minuscules, suppression des doublons).

### 2.3 Méthodes dans `RepositoryService`
- `initDatabase()` : exécution de la création de la table `project_tags` et de ses index.
- `getTagsByProject(): array` : renvoie un tableau associatif `[projectName => [tag1, tag2, ...]]`.
- `addProjectTag(string $projectName, string $tag): bool` : insère une association `(project_name, tag)` avec `INSERT OR IGNORE`.
- `removeProjectTag(string $projectName, string $tag): bool` : supprime une association `DELETE FROM project_tags WHERE project_name = :p AND tag = :t`.
- `getAllTags(): array` : retourne la liste unique de tous les tags existants triés alphabétiquement.

### 2.4 Intégration dans `GitlabService` / `ProjectRepository`
- Lors de la récupération des projets (`findAll` ou `scan`), les tags sont hydratés en mémoire et injectés dans chaque objet `Project` / `ProjectEntity`.

---

## 3. Endpoints API & Sécurité

Deux actions AJAX sont exposées via `IndexController::handleRequest` :

### 3.1 `ACTION_ADD_PROJECT_TAG` (`add_project_tag`)
- **Méthode** : POST
- **Payload JSON** :
  ```json
  {
    "projectName": "api-orders",
    "tag": "paiement"
  }
  ```
- **Contrôle de sécurité** :
  - Vérification de l'authentification et du rôle : `($_SESSION['user_role'] ?? '') === 'ROLE_ADMIN'`.
  - Si non autorisé : réponse HTTP 403 `{"success": false, "error": "Accès réservé aux administrateurs."}`.
- **Validation** :
  - `projectName` et `tag` non vides.
  - Normalisation du tag (trim, minuscules, suppression de caractères dangereux, max 50 caractères).
- **Réponse succès** :
  ```json
  {
    "success": true,
    "tags": ["paiement", "refonte"]
  }
  ```

### 3.2 `ACTION_REMOVE_PROJECT_TAG` (`remove_project_tag`)
- **Méthode** : POST
- **Payload JSON** :
  ```json
  {
    "projectName": "api-orders",
    "tag": "paiement"
  }
  ```
- **Contrôle de sécurité** :
  - Rôle `ROLE_ADMIN` requis (HTTP 403 en cas d'échec).
- **Réponse succès** :
  ```json
  {
    "success": true,
    "tags": ["refonte"]
  }
  ```

---

## 4. Interface Utilisateur & Intégration Datagrid

### 4.1 Affichage sous le nom du projet (`templates/common/_index_rows.html.twig`)
Dans la cellule de la colonne **Projet** :
- Le lien vers le projet GitLab est maintenu.
- Juste en dessous, un conteneur `<div class="project-tags mt-1">` affiche :
  - Pour chaque tag : un badge interactif (ex: `badge badge-light border text-secondary mr-1`).
  - Chaque badge a un événement `@click.stop="filterByTag('{{ tag }}')"` qui place la valeur dans le champ de filtre et déclenche la recherche instantanée.
  - Si l'utilisateur connecté est administrateur (`user_role == 'ROLE_ADMIN'`) :
    - Une petite croix `@click.stop="removeTag('{{ r.name }}', '{{ tag }}')"` sur chaque badge.
    - Un bouton compact `+` ouvrant le champ inline d'ajout de tag.

### 4.2 Édition Inline Alpine.js
- Un composant local ou intégré à `datagrid.js` gère l'état d'édition pour la ligne :
  - `isEditingTag: false`, `newTagText: ''`.
  - En mode édition : affichage d'un `<input type="text" class="form-control form-control-xs">` avec :
    - `@keyup.enter="saveTag(projectName)"`
    - `@keyup.escape="cancelEdit()"`
    - `@click.outside="cancelEdit()"`
- Lors de l'ajout ou du retrait d'un tag, l'appel AJAX met à jour la liste des tags de la ligne de manière réactive.

### 4.3 Filtrage et Recherche (`DatagridHelper.php`)
- La logique de filtrage sur la colonne `name` est étendue :
  ```php
  if ($key === 'name') {
      $matchName = stripos((string)$item['name'], (string)$val) !== false;
      $tags = $item['tags'] ?? [];
      $matchTags = false;
      foreach ($tags as $t) {
          if (stripos((string)$t, (string)$val) !== false) {
              $matchTags = true;
              break;
          }
      }
      if (!$matchName && !$matchTags) {
          return false;
      }
      continue;
  }
  ```
- Ainsi, saisir un nom de projet ou un tag dans le filtre de recherche trouve immédiatement les projets correspondants.

---

## 5. Stratégie de Tests

1. **Tests Unitaires Backend** :
   - `RepositoryServiceTest` : initialisation de la table `project_tags`, insertion, récupération groupée, suppression d'un tag, unicité `(project_name, tag)`.
   - `ProjectMapperTest` : sérialisation et désérialisation du champ `tags`.
   - `DatagridHelperTest` : filtrage d'un projet par nom, filtrage d'un projet par tag existant, non-concordance si aucun tag ne matche.
   - `IndexControllerTest` : sécurisation des actions AJAX `add_project_tag` et `remove_project_tag` (accès refusé si non connecté ou non-admin, succès si admin).
2. **Tests d'Intégration / E2E** :
   - Vérification du bon rendu HTML des badges dans le datagrid.
   - Non-régression sur le scan GitLab et la persistance des tags après scan.
