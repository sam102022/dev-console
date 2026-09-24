# Spécification de Conception : Colonnes Domaine et SF sur la Gestion des Tags

Date : 2026-09-24  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

La page d'administration des tags (`/?page=tags`) permet aux administrateurs de filtrer les projets et d'associer ou supprimer des tags.
Actuellement, le tableau principal n'affiche que le nom du projet (avec un badge discret pour le domaine), la liste des tags associés et le bouton d'action.

L'objectif est d'aligner l'interface du tableau de gestion des tags avec l'interface de **Monitoring** (`/?page=monitoring`) en intégrant deux colonnes complètes :
1. **Domaine** : avec tri par colonne (`sortBy('domain')`) et sélecteur déroulant de filtre (`#filter_domain`).
2. **SF** (Sous-fonctionnalité) : avec tri par colonne (`sortBy('sf')`), sélecteur déroulant de filtre (`#filter_sf`), et filtrage dynamique automatique des options selon le domaine sélectionné (`allowedSfs`).

---

## 2. Architecture & Backend (`TagAdminController`)

### 2.1 Méthode `index(array &$messages): void`
- Scanne les projets GitLab via `$this->gitlabService->scan()`.
- Extrait la liste ordonnée des domaines uniques :
  `$domains[$project->getDomain()] = $project->getDomainName() ?: $project->getDomain();` (en ignorant les valeurs vides).
- Extrait la liste ordonnée des sous-fonctionnalités uniques :
  `$sfs[$project->getSf()] = $project->getSf();` (en ignorant les valeurs vides).
- Transmet `domains` et `sfs` à la vue `tags.html.twig`.

### 2.2 Méthode `getDatagridRows(): string`
- Enrichit chaque élément de `$items` :
  ```php
  $items[] = [
      'name' => $projectName,
      'domain' => $project->getDomain() ?? '',
      'sf' => $project->getSf() ?? '',
      'tags' => array_values($tags)
  ];
  ```
- Traite le filtrage, le tri et la pagination via `DatagridHelper::process($items, $filters, $sortCol, $sortDir, $page, $limit)`.
- Calcule dynamiquement les sous-fonctionnalités autorisées (`allowedSfs`) selon le domaine sélectionné (`$filters['domain'] ?? 'all'`) :
  ```php
  $domainFilter = $filters['domain'] ?? 'all';
  $allowedSfs = [];
  foreach ($items as $item) {
      if (($domainFilter === 'all' || $domainFilter === '' || $item['domain'] === $domainFilter) && !empty($item['sf'])) {
          $allowedSfs[] = $item['sf'];
      }
  }
  $allowedSfs = array_values(array_unique($allowedSfs));
  ```
- Retourne la réponse JSON avec :
  ```json
  {
      "success": true,
      "html": "...",
      "totalRows": 42,
      "allowedSfs": ["accounting", "orders"]
  }
  ```

---

## 3. Interface Utilisateur & Templates Twig

### 3.1 `templates/tags.html.twig`
- **Structure des colonnes** :
  1. `#` (`width: 50px`)
  2. `Domaine` : en-tête triable avec `@click.prevent="sortBy('domain')"`
  3. `SF` : en-tête triable avec `@click.prevent="sortBy('sf')"`
  4. `Projet` : en-tête triable avec `@click.prevent="sortBy('name')"`
  5. `Tags associés`
  6. `Actions` (`width: 160px`)

- **Ligne de filtrage (`<thead>`)** :
  - Colonne `#` : cellule vide.
  - Colonne `Domaine` :
    ```html
    <div class="input-group input-group-sm">
        <select id="filter_domain" class="form-control custom-select filter-input" x-model="filters.domain" @change="onFilterChange()">
            <option value="all">Tous</option>
            {% for key, value in domains %}
                <option value="{{ key }}">{{ value }}</option>
            {% endfor %}
        </select>
        <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" @click="filters.domain = 'all'; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    ```
  - Colonne `SF` :
    ```html
    <div class="input-group input-group-sm">
        <select id="filter_sf" class="form-control custom-select filter-input" x-model="filters.sf" @change="onFilterChange()">
            <option value="all">Tous</option>
            {% for key, value in sfs %}
                <option value="{{ key }}">{{ value }}</option>
            {% endfor %}
        </select>
        <div class="input-group-append">
            <button class="btn btn-outline-secondary" type="button" @click="filters.sf = 'all'; onFilterChange()" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
    ```
  - Colonne `Projet` : input `#filter_name` existant.
  - Colonne `Tags` : input `#filter_tag` existant.
  - Colonne `Actions` : bouton `resetFilters()`.

### 3.2 `templates/common/_tags_rows.html.twig`
- Ajout des cellules de données :
  ```html
  <td class="col-domain">{{ r.domain }}</td>
  <td class="col-sf">{{ r.sf }}</td>
  ```
- Suppression du badge domaine à côté du nom du projet (désormais superflu).
- Adaptation de l'état vide : `<td colspan="6" class="text-center text-muted py-4">`.

---

## 4. Frontend Réactif Alpine.js & Datagrid

- Le composant `tagsDatagrid()` hérite de `window.datagrid({ pageName: 'tags' })`.
- Lors du changement de filtre domaine, `fetchData()` reçoit `allowedSfs` et appelle `updateSfFilterDropdown()` pour restreindre le sélecteur `#filter_sf` aux seules options valides.
- `resetFilters()` réinitialise les select `#filter_domain` et `#filter_sf` à `'all'`.

---

## 5. Stratégie de Test et Validation

1. **`TagAdminControllerTest.php`** :
   - Tester l'injection de `domains` et `sfs` dans le rendu de `index()`.
   - Tester la présence de `domain`, `sf` et `allowedSfs` dans le retour JSON de `getDatagridRows()`.
   - Tester le filtrage par `filter_domain` et `filter_sf`.

2. **`TagAdminTemplateTest.php`** :
   - Tester que `tags.html.twig` génère bien les en-têtes triables, le select de domaine et le select de SF.
   - Tester que `_tags_rows.html.twig` génère bien les colonnes `<td class="col-domain">` et `<td class="col-sf">`.

3. **`console.spec.ts` (Playwright)** :
   - Tester la sélection d'un filtre domaine sur la page `/?page=tags` et vérifier le rechargement asynchrone des lignes sans erreurs Alpine.
