# Spécification de Conception : Colonne et Filtre Archivé sur la Gestion des Tags

Date : 2026-09-25  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

La page d'administration des tags (`/?page=tags`) permet aux administrateurs de filtrer les projets et d'associer ou supprimer des tags.
Actuellement, le tableau principal affiche les colonnes `#`, `Domaine`, `SF`, `Projet`, `Tags associés` et `Actions`.
L'interface de **Monitoring** (`/?page=monitoring`) dispose d'une colonne `Archivé` avec une icône visuelle indiquant l'état archivé d'un projet, et d'un filtre déroulant permettant de filtrer les projets par statut d'archivage (`Tous`, `Non`, `Oui`), positionné par défaut sur `Non`.

L'objectif est d'aligner l'interface du tableau de gestion des tags avec l'interface de **Monitoring** en intégrant :
1. Une colonne **Archivé** positionnée entre **Projet** et **Tags associés**, avec tri par colonne (`sortBy('archived')`) et icône `<i class="fa-solid fa-box-archive text-danger mr-1" title="Projet archivé"></i>` pour les projets archivés.
2. Un sélecteur déroulant de filtre (`#filter_archived`) avec les options `Tous`, `Non`, `Oui`, avec bouton d'effacement rapide.
3. Une valeur par défaut initialisée à `Non` au chargement de la page et lors du reset des filtres, identique au comportement de la page Monitoring.

---

## 2. Architecture & Backend (`TagAdminController`)

### 2.1 Méthode `getDatagridRows(): string`
- Enrichit chaque élément de `$items` avec la propriété booléenne `archived` issue de l'entité/modèle `Project` :
  ```php
  $items[] = [
      'name' => $projectName,
      'domain' => $project->getDomain() ?? '',
      'sf' => $project->getSf() ?? '',
      'archived' => $project->isArchived(),
      'tags' => array_values($tags)
  ];
  ```
- Délègue le filtrage, le tri et la pagination à `DatagridHelper::process($items, $filters, $sortCol, $sortDir, $page, $limit)`.
  - `DatagridHelper` supporte déjà nativement le filtre `archived` (`oui` / `non` / `all`).
  - Le tri par `archived` (`sortBy('archived')`) est également pris en charge nativement par `DatagridHelper`.
- Transmet la liste paginée `$paginated['items']` au template `common/_tags_rows.html.twig`.

---

## 3. Interface Utilisateur & Templates Twig

### 3.1 `templates/tags.html.twig`
- **Structure des colonnes de l'en-tête (`<tr>`)** :
  1. `#` (`width: 50px`)
  2. `Domaine` : triable avec `@click.prevent="sortBy('domain')"`
  3. `SF` : triable avec `@click.prevent="sortBy('sf')"`
  4. `Projet` : triable avec `@click.prevent="sortBy('name')"`
  5. `Archivé` : triable avec `@click.prevent="sortBy('archived')"`
  6. `Tags associés`
  7. `Actions` (`width: 160px`)

- **Ligne de filtrage (`<thead>`)** :
  - Cellule de filtre `#filter_archived` insérée entre la colonne *Projet* et la colonne *Tags associés* :
    ```html
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
    ```

### 3.2 `templates/common/_tags_rows.html.twig`
- Ajout de la cellule `td.col-archived` :
  ```html
  <td class="col-archived" data-column="archived" data-value="{% if r.archived %}oui{% else %}non{% endif %}">
      {% if r.archived %}<i class="fa-solid fa-box-archive text-danger mr-1" title="Projet archivé"></i>{% endif %}
  </td>
  ```
- Mise à jour du `colspan` de l'état vide : `<td colspan="7" class="text-center text-muted py-4">`.

---

## 4. Frontend Réactif Alpine.js & Datagrid (`public/js/datagrid.js`)

- **Initialisation des filtres (`init`)** :
  - Intégrer `tags` dans la condition par défaut pour la clé `archived` :
    ```javascript
    if (key === 'archived' && (this.pageName === 'monitoring' || this.pageName === 'tags')) {
        val = 'non';
    }
    ```
- **Réinitialisation des filtres (`resetFilters`)** :
  - Intégrer `tags` dans la condition de réinitialisation pour la clé `archived` :
    ```javascript
    if (key === 'archived' && (this.pageName === 'monitoring' || this.pageName === 'tags')) {
        this.filters[key] = 'non';
    }
    ```

---

## 5. Stratégie de Test et Validation

1. **`TagAdminControllerTest.php`** :
   - Tester que `getDatagridRows()` transmet la clé `'archived'` (booléen) pour chaque élément au template de lignes.
   - Tester le filtrage par `filter_archived = oui` et `filter_archived = non`.
   - Tester le tri par `sort_column = archived`.

2. **`TagAdminTemplateTest.php`** :
   - Tester que `tags.html.twig` contient bien l'en-tête triable `sortBy('archived')` et le sélecteur `id="filter_archived"`.
   - Tester que `_tags_rows.html.twig` génère bien `<td class="col-archived">` avec l'icône `<i class="fa-solid fa-box-archive text-danger"` si `archived == true` et sans icône si `false`.
   - Tester que la ligne vide dans `_tags_rows.html.twig` possède bien `colspan="7"`.

3. **Validation globale** :
   - Exécution de l'ensemble de la suite de tests PHPUnit (`vendor/bin/phpunit`).
