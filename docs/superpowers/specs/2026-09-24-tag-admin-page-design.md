# Spécification Technique : Page d'Administration Dédiée pour la Gestion des Tags

- **Date :** 2026-09-24
- **Statut :** Validé
- **Branche cible :** `feat/tags`

---

## 1. Contexte & Objectif

Auparavant, la gestion des tags (ajout et suppression) était intégrée directement en ligne (inline) sur les vues principales (Index, Monitoring et Rundeck) pour les administrateurs.
L'objectif de cette évolution est de :
1. **Centraliser et déporter la gestion des tags** au sein d'une page dédiée dans l'espace d'administration (`?page=tags`), réservée aux administrateurs (`ROLE_ADMIN`).
2. **Nettoyer les interfaces Index, Monitoring et Rundeck** en retirant les éléments d'édition inline (bouton d'ajout `+` et croix de suppression `x` sur les badges), tout en conservant l'affichage des badges et le filtrage instantané par tag au clic.
3. **Structurer la navigation d'administration** en regroupant "Utilisateurs" et "Gestion des tags" sous un menu déroulant "Administration" dans la barre de navigation.

---

## 2. Architecture & Routage

### 2.1 Nouveau Contrôleur `TagAdminController`
- **Fichier :** `src/controller/TagAdminController.php`
- **Constantes :**
  - `ROUTE_TAGS = 'tags'`
  - `ACTION_GET_DATAGRID_ROWS = 'getDatagridRows'`
  - `ACTION_ADD_PROJECT_TAG = 'addProjectTag'`
  - `ACTION_REMOVE_PROJECT_TAG = 'removeProjectTag'`
- **Sécurité :**
  - Seuls les utilisateurs avec `session.user_role === 'ROLE_ADMIN'` peuvent accéder au contrôleur et exécuter des actions.
  - Tout accès non autorisé renvoie une redirection vers la page de login (`/?page=login`) pour l'affichage, ou une réponse JSON `{ "success": false, "message": "Accès refusé" }` avec code HTTP 403 pour les appels API / AJAX.
- **Méthodes :**
  - `index(array &$messages): void` : prépare le modèle de vue (dont la liste globale de tous les tags existants `allTags` via `RepositoryService::getAllTags()`) et effectue le rendu de `tags.html.twig`.
  - `handleRequest(string $action): string` : routeur d'actions JSON/HTML (`getDatagridRows`, `addProjectTag`, `removeProjectTag`).
  - `getDatagridRows(): string` : filtre et pagine la liste des projets avec leurs tags associés, retourne le rendu de `common/_tags_rows.html.twig`.
  - `addProjectTag(): string` : valide les données (`projectName`, `tag`), appelle `RepositoryService::addProjectTag()` et renvoie une réponse JSON `{ "success": true, "tags": [...] }`.
  - `removeProjectTag(): string` : valide les données (`projectName`, `tag`), appelle `RepositoryService::removeProjectTag()` et renvoie une réponse JSON `{ "success": true, "tags": [...] }`.

### 2.2 Enregistrement des Services et Routage
- **Configuration des services :** `src/config/services.yaml`
  - Déclaration du service `App\controller\TagAdminController` injectant `RepositoryService`, `Twig\Environment` et `LoggerFactory`.
- **Routage :** `src/router/IndexRouter.php`
  - Ajout de la route `tags` associée au `TagAdminController`.

---

## 3. Navigation (`templates/base.html.twig`)

Pour les administrateurs (`session.user_role == 'ROLE_ADMIN'`), le menu de navigation remplace le lien simple "Utilisateurs" par un menu déroulant Bootstrap :
- **Libellé :** `<i class="fas fa-tools mr-1"></i> Administration <i class="fas fa-caret-down ml-1"></i>`
- **État actif :** actif si `current_route == 'users'` ou `current_route == 'tags'`.
- **Éléments du menu déroulant :**
  1. **Utilisateurs :** `<a class="dropdown-item" href="?page=users"><i class="fas fa-users-cog mr-2"></i>Utilisateurs</a>`
  2. **Gestion des tags :** `<a class="dropdown-item" href="?page=tags"><i class="fas fa-tags mr-2"></i>Gestion des tags</a>`

---

## 4. Interface Utilisateur & Modale Alpine (`templates/tags.html.twig`)

### 4.1 Page Principale
- Titre de carte : `<i class="fas fa-tags mr-2" style="color: #17a2b8;"></i>Gestion des Tags`
- Composant Alpine : `tagsDatagrid()`
- **Tableau des projets :**
  - Colonne **#** : index ou icône.
  - Colonne **Projet** : nom du projet, cliquable ou accompagné de ses métadonnées.
  - Colonne **Tags associés** : liste des badges de tags du projet (affichage seul).
  - Colonne **Actions** : bouton `<button class="btn btn-outline-primary btn-sm" @click="openManageTagsModal(row)"><i class="fas fa-tags mr-1"></i> Gérer les tags</button>`.
- **Filtres de colonnes en en-tête :**
  - Filtre par nom de projet (`filter_project`).
  - Filtre par tag (`filter_tag`).
  - Bouton de réinitialisation des filtres.
- **Pagination :** sélection du nombre de lignes par page (10, 25, 50) et pagination standard.

### 4.2 Modale Alpine de Gestion des Tags
- **En-tête de la modale :**
  - Titre : `Gérer les tags - [Nom du projet sélectionné]`
- **Corps de la modale :**
  - **Tags actuels :**
    - Affichage sous forme de badges.
    - Chaque badge comporte une icône `fa-times` cliquable pour supprimer le tag.
    - Clic sur suppression : appel API immédiat vers `?page=tags&action=removeProjectTag`. Au succès, retrait visuel immédiat dans la modale et mise à jour des tags du projet dans le tableau principal.
  - **Ajout d'un nouveau tag :**
    - Champ texte de saisie lié à un `<datalist id="available-tags-list">` alimenté par la liste globale des tags existants.
    - Validation client et serveur : 3 à 50 caractères alphanumériques et tirets.
    - Bouton `Ajouter` (ou validation par la touche Entrée).
    - Clic sur Ajouter : appel API immédiat vers `?page=tags&action=addProjectTag`. Au succès, ajout du badge dans la modale, réinitialisation du champ de saisie, et mise à jour des tags du projet dans le tableau principal.
  - **Messages de feedback :** Affichage d'un retour discret en cas d'erreur ou de succès.
- **Pied de la modale :**
  - Bouton `Fermer`.

---

## 5. Nettoyage des Interfaces Existantes

### 5.1 Templates Lignes (`_index_rows.html.twig`, `_monitoring_rows.html.twig`, `_rundeck_rows.html.twig`)
- Retrait des éléments d'édition réservés aux administrateurs :
  - Suppression du bouton d'ajout de tag `+` (`tag-add-btn`).
  - Suppression de la croix de suppression `x` sur les badges (`tag-remove-btn`).
- Conservation intégrale de l'affichage des badges et de la directive `@click.stop="filterByTag('...')"` permettant de filtrer instantanément le tableau sur le tag cliqué.

### 5.2 Fichier JavaScript (`public/js/datagrid.js`)
- Nettoyage des gestionnaires d'événements inline (`openTagModal`, `removeTagInline` sur les datagrids publics).
- Définition du composant Alpine `tagsDatagrid` pour la page d'administration des tags (`pageName: 'tags'`), assurant la gestion de la modale, des appels asynchrones et de la synchronisation locale des lignes.

---

## 6. Stratégie de Tests & Validation

1. **Tests Unitaires Contrôleur (`tests/controller/TagAdminControllerTest.php`) :**
   - Test d'accès anonyme (redirection login).
   - Test d'accès utilisateur non-admin `ROLE_USER` (accès refusé 403 / redirection).
   - Test d'accès administrateur `ROLE_ADMIN` (accès autorisé 200, variables de vue correctes).
   - Test des actions API `getDatagridRows`, `addProjectTag`, `removeProjectTag` avec vérification des codes de retour et formats JSON.
2. **Tests des Templates (`tests/templates/TagsRowTemplateTest.php`) :**
   - Vérification que pour `_index_rows`, `_monitoring_rows` et `_rundeck_rows`, les badges de tags sont rendus sans bouton `+` ni croix de suppression `x`, même en présence d'une session administrateur.
3. **Tests Non-Régression :**
   - Exécution de toute la suite de tests PHPUnit (`./vendor/bin/phpunit`).
   - Analyse statique PHPStan (`./vendor/bin/phpstan`).
