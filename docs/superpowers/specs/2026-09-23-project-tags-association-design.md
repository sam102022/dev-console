# Spécification Technique & Fonctionnelle : Association de Projets par Tags Libres

**Date :** 2026-09-23  
**Statut :** Validé (avec extension multi-interfaces)  
**Auteur :** Gemini CLI & Utilisateur  

---

## 1. Contexte & Objectifs

Dans l'application **dev-console**, les utilisateurs doivent pouvoir associer certains projets entre eux via des étiquettes (tags / mots-clés libres) afin de retrouver facilement tous les projets liés à un même domaine fonctionnel, une refonte technique, ou un écosystème applicatif partagé (ex: `paiement`, `omnichannel`, `flux-commandes`, `refonte-2026`).

### Objectifs principaux :
1. **Association par tags libres** : possibilité d'associer un ou plusieurs tags à n'importe quel projet par son nom technique unique (`name` / `projectName`).
2. **Disponibilité sur 3 interfaces majeures** :
   - **Page Projets GitLab (`index`)**
   - **Page Monitoring (`monitoring`)**
   - **Page Rundeck (`rundeck`)**
3. **Persistance robuste** : les tags sont conservés lors des scans GitLab ou Rundeck (qui réinitialisent leurs tables de cache respectives).
4. **Contrôle d'accès (RBAC)** :
   - Visibilité : **globale** (tous les utilisateurs connectés voient les tags sur les 3 interfaces).
   - Gestion (ajout/suppression) : réservée aux utilisateurs ayant le rôle **`ROLE_ADMIN`**, disponible directement en *inline* sur les 3 interfaces.
5. **Expérience utilisateur fluide** :
   - Affichage sous le nom du projet dans le tableau de chaque interface.
   - Édition *inline* directe pour les administrateurs (champ compact avec validation `Entrée` et suppression par clic sur une croix `×`).
   - Filtrage instantané : clic direct sur un badge de tag pour filtrer le tableau, et intégration des tags dans le filtre textuel de la colonne "Nom / Projet".

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

**Propriété clé :** Cette table est complètement découplée des tables `projects`, `gitlab_projects` et `rundeck_projects`. Lors d'un scan ou rafraîchissement de cache, la table `project_tags` n'est jamais purgée.

### 2.2 Modèles PHP & Mappers
- **`App\repository\model\ProjectEntity`** :
  - Propriété `private array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\model\Project`** :
  - Propriété `public array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\model\RundeckProject`** :
  - Propriété `private array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\repository\model\RundeckProjectEntity`** :
  - Propriété `private array $tags = [];`
  - Getters / Setters : `getTags(): array`, `setTags(array $tags): self`.
- **`App\repository\mapper\ProjectMapper` & `RundeckProjectMapper`** :
  - Prise en charge du champ `tags` lors des conversions array <-> entity <-> model.
  - Normalisation des tags (trim, minuscules, suppression des doublons).

### 2.3 Méthodes dans `RepositoryService`
- `initDatabase()` : exécution de la création de la table `project_tags` et de ses index.
- `getTagsByProject(): array` : renvoie un dictionnaire `[projectName => [tag1, tag2, ...]]`.
- `getTagsForProject(string $projectName): array` : renvoie la liste des tags d'un projet donné.
- `addProjectTag(string $projectName, string $tag): bool` : insère une association `(project_name, tag)` avec `INSERT OR IGNORE`.
- `removeProjectTag(string $projectName, string $tag): bool` : supprime une association `DELETE FROM project_tags WHERE project_name = :p AND tag = :t`.
- `getAllTags(): array` : retourne la liste unique de tous les tags existants triés alphabétiquement.

### 2.4 Intégration dans `GitlabService` et `RundeckService`
- Lors de la récupération des projets dans `GitlabService::scan()` et `RundeckService::findAll()`, les tags issus de `project_tags` sont associés à chaque projet retourné.

---

## 3. Endpoints API & Sécurité

Deux actions AJAX globales sont exposées (accessibles depuis n'importe quelle page via le routeur / contrôleurs) :

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
  - Rôle requis : `($_SESSION['user_role'] ?? '') === 'ROLE_ADMIN'`.
  - En cas de non-autorisation : HTTP 403 `{"success": false, "error": "Accès réservé aux administrateurs."}`.
- **Validation** :
  - `projectName` et `tag` non vides.
  - Normalisation : `strtolower(trim($tag))`, suppression des caractères interdits (alphanumérique, tirets, underscores), max 50 caractères.
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
  - Rôle `ROLE_ADMIN` requis (HTTP 403 si absent).
- **Réponse succès** :
  ```json
  {
    "success": true,
    "tags": ["refonte"]
  }
  ```

---

## 4. Interface Utilisateur & Intégration Datagrid

### 4.1 Modèles de lignes (`_index_rows.html.twig`, `_monitoring_rows.html.twig`, `_rundeck_rows.html.twig`)
Dans la colonne **Nom / Projet** de chacune des 3 vues :
- Affichage du conteneur de tags sous le lien du projet :
  - Badges de tags existants (`badge badge-light border text-secondary mr-1`).
  - Clic sur un badge `@click.stop="filterByTag('{{ tag }}')"` : remplit le champ de recherche textuelle du tableau avec le tag et filtre instantanément.
  - Si l'utilisateur est administrateur (`session.user_role == 'ROLE_ADMIN'`) :
    - Petite croix de suppression `@click.stop="removeTag('{{ r.name }}', '{{ tag }}')"` sur chaque badge.
    - Bouton `+` déclenchant le mode d'édition *inline*.

### 4.2 Édition Inline Alpine.js (`datagrid.js`)
- Gestion d'état Alpine.js partagée ou composant dédié pour la saisie :
  - Champ texte compact affiché directement sous le projet.
  - Touche `Entrée` : appel AJAX `add_project_tag`, mise à jour immédiate du DOM.
  - Touche `Échap` / Clic extérieur : annulation de la saisie.
- La méthode de suppression appelle AJAX `remove_project_tag` et retire le badge sans rechargement de page.

### 4.3 Filtrage et Recherche multi-vues (`DatagridHelper.php`)
- Pour les 3 vues (`index`, `monitoring`, `rundeck`), le filtre sur la colonne `name` recherche à la fois :
  - Dans le nom du projet (`stripos($item['name'], $query) !== false`)
  - ET dans les tags associés (`in_array / stripos` sur la liste `$item['tags']`).

---

## 5. Stratégie de Tests

1. **Tests Unitaires Backend** :
   - `RepositoryServiceTest` : initialisation de la table `project_tags`, opérations CRUD sur les tags, unicité `(project_name, tag)`.
   - `ProjectMapperTest` & `RundeckProjectMapperTest` : sérialisation/désérialisation du champ `tags`.
   - `DatagridHelperTest` : filtrage de projets par tag pour les différentes vues.
   - Contrôleurs / API : vérification du contrôle d'accès `ROLE_ADMIN` sur les routes de tags.
2. **Tests d'Intégration** :
   - Rendu des 3 templates (`_index_rows`, `_monitoring_rows`, `_rundeck_rows`) avec tags et sans tags.
   - Vérification du comportement non-admin (badges visibles mais boutons d'édition absents).
