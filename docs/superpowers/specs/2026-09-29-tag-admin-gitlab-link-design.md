# Spécification de Conception : Lien GitLab sur le Nom du Projet dans la Gestion des Tags

Date : 2026-09-29  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

La page d'administration des tags (`/?page=tags`) affiche la liste des projets dans un tableau pour gérer leurs associations de tags.
Dans cette table, le nom du projet est actuellement rendu sous forme de texte statique en gras :
```html
<td>
    <strong>{{ r.name }}</strong>
</td>
```

Dans l'interface de **Monitoring** (`/?page=monitoring`), ainsi que dans la page d'accueil (`/?page=index`) et Rundeck (`/?page=rundeck`), le nom du projet est cliquable et renvoie vers le dépôt GitLab distant correspondant avec les caractéristiques suivantes :
- Troncature du nom à 35 caractères (`slice(0, 35) ~ '...'`) si sa longueur dépasse 35 caractères.
- Icône GitLab dorée : `<i class="fa-brands fa-gitlab text-warning"></i>`.
- Lien hypertexte ouvrant dans un nouvel onglet : `target="_blank"`.
- Classes CSS : `url-link text-decoration-none`.
- Infobulle descriptive : `title="Voir le projet Gitlab{% if r.archived %} archivé{% endif %} {{ r.name }}"`.
- Fallback textuel simple si `webUrl` n'est pas défini ou est vide.

L'objectif est d'aligner le rendu de la colonne *Projet* dans la gestion des tags (`templates/common/_tags_rows.html.twig`) sur l'interface Monitoring.

---

## 2. Architecture & Backend (`TagAdminController`)

### 2.1 Méthode `getDatagridRows(): string`
Dans `src/controller/TagAdminController.php`, la boucle construisant `$items` pour le datagrid doit inclure la propriété `webUrl` issue de l'objet `$project` (instance de `GitlabProject` ou `Project`) :

```php
foreach ($projects as $project) {
    $projectName = $project->getName();
    $tags = $tagsByProject[$projectName] ?? $project->getTags() ?? [];
    $items[] = [
        'name' => $projectName,
        'domain' => $project->getDomain() ?? '',
        'sf' => $project->getSf() ?? '',
        'archived' => $project->isArchived(),
        'webUrl' => $project->getWebUrl(),
        'tags' => array_values($tags)
    ];
}
```

### 2.2 Robustesse des Modèles (`Project.php`, `GitlabProject.php`)
Afin de prévenir tout avertissement ou erreur d'accès à une propriété typée non initialisée (notamment lors de l'instanciation de projets fictifs dans les tests unitaires sans appel à `setWebUrl()`), la propriété `$webUrl` est initialisée par défaut avec une chaîne vide :
```php
private string $webUrl = '';
```

---

## 3. Interface Utilisateur & Templates Twig

### 3.1 `templates/common/_tags_rows.html.twig`
Dans `templates/common/_tags_rows.html.twig`, la cellule du nom de projet est enrichie avec la classe standard `col-name` et le template identique à `_monitoring_rows.html.twig` :

```twig
<td class="col-name">
    {% set projectName = r.name|length > 35
        ? r.name|slice(0, 35) ~ '...'
        : r.name
    %}
    {% if r.webUrl is defined and r.webUrl != '' %}
        <a href="{{ r.webUrl }}"
           class="url-link text-decoration-none"
           target="_blank"
           title="Voir le projet Gitlab{% if r.archived %} archivé{% endif %} {{ r.name }}">
            <i class="fa-brands fa-gitlab text-warning"></i> {{ projectName }}
        </a>
    {% else %}
        {{ projectName }}
    {% endif %}
</td>
```

---

## 4. Stratégie de Tests et Validation

### 4.1 Tests Unitaires & Intégration
1. **`tests/templates/TagAdminTemplateTest.php`** :
   - Mettre à jour le jeu de données de test avec `webUrl`.
   - Vérifier la présence du lien `<a href="https://gitlab.com/...">`, de l'icône `fa-brands fa-gitlab text-warning`, de la classe `url-link text-decoration-none`, de `target="_blank"`, et du texte tronqué à 35 caractères si applicable.
   - Vérifier le cas de fallback (lorsque `webUrl` est absent ou vide).

2. **`tests/controller/TagAdminControllerTest.php`** :
   - Vérifier que `getDatagridRows()` transmet bien la clé `webUrl` dans la liste paginée rendue par Twig.
   - S'assurer de la rétrocompatibilité des tests existants instanciant des `new Project()` sans appel préalable à `setWebUrl()`.

3. **Exécution globale de la suite de tests** :
   - Valider que les 533 tests PHPUnit passent avec succès.
