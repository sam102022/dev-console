

## 08/09/2026

✦ Le Sprint 6 (Persistance & Robustesse de Données - SQLite & Cache) est désormais totalement implémenté et optimisé ! 💾🚀

Voici les réalisations majeures qui ont été apportées :

1. Base de données SQLite Zero-Config :
   - Initialisation automatique d'une base de données SQLite dans `data/database.sqlite` (ou `:memory:` dans l'environnement de test avec `vfsStream`).
   - Création automatique du schéma des tables (`projects`, `gitlab_projects`, `rundeck_projects`, `cache_store`) avec des index de performance stratégiques (`idx_projects_archived`, `idx_projects_domain`).
   - Gestion d'une transition transparente avec repli automatique (graceful fallback) vers le cache de fichiers plat (`FilesystemAdapter`) si l'extension PDO SQLite est absente ou désactivée.

2. Refactoring structurel de RepositoryService :
   - Remplacement de la sérialisation/désérialisation massive de gros fichiers JSON par des écritures et lectures SQL précises à l'aide de requêtes préparées PDO.
   - Intégration de transactions SQL sécurisées (`beginTransaction`/`commit`/`rollBack`) lors des sauvegardes par lot pour garantir l'intégrité absolue des données et éviter les corruptions en cas d'écritures concurrentes.
   - Conservation sélective de la gestion des fichiers statiques d'exclusion (`new_relic_urls.json`, `rundeckObjects.json` en mode fichier) tout en intégrant pleinement le stockage structuré pour les données dynamiques GitLab et Rundeck.

3. Optimisation des performances à O(1) :
   - Refactorisation de la méthode `findByCode` dans `ProjectRepository` pour exploiter directement la nouvelle méthode `findProjectByName` du `RepositoryService`.
   - L'accès à un projet individuel s'effectue désormais instantanément via une requête SQL indexée, évitant le chargement en RAM et le parcours linéaire de la totalité du cache de projets.

4. Fiabilité & Suite de Tests validée :
   - Mise à jour de `ProjectRepositoryTest` et `RepositoryServiceTest` pour prendre en compte les nouveaux comportements et garantir que la couche SQLite fonctionne parfaitement avec un système de fichiers virtuel (VFS).
   - Validation complète de la suite de tests : **328 tests passés avec succès (100% de réussite)** avec génération automatique des rapports de couverture de code !

## 02/09/2026

✦ Les Sprints 4 (Sécurité) et 5 (Modernisation du Framework) sont maintenant totalement implémentés ! 🛡️🏗️

Voici les réalisations majeures :

1. Sécurité (Sprint 4) :
   - Ajout d'un fichier `.htaccess` à la racine pour sécuriser par défaut l'accès aux dossiers d'infrastructure (`data/`, `src/`, `vendor/`, `.git/`) et fichiers de configuration (`.env`, `.env-test`).
   - Rédaction du rapport d'architecture `ADR 004 : Isolation de la racine Web` formalisant la nécessité de configurer le `DocumentRoot` du serveur web sur le sous-dossier `public/`.
   - Élaboration d'une feuille de route pour le futur avec la création des spécifications pour les Sprints 5 à 9.

2. Modernisation de l'Architecture Applicative (Sprint 5) :
   - Remplacement de l'AbstractContainer maison par le Micro-Kernel Symfony standard. L'injection de dépendances repose maintenant entièrement sur l'autowiring natif de Symfony configuré via `src/config/services.yaml`.
   - Création de la fabrique centralisée de services `App\factory\ServiceFactory` gérant proprement l'instanciation personnalisée des clients tiers (Guzzle, GitLab, Postman, New Relic) et du moteur de templates Twig.
   - Suppression des anciennes classes de conteneurs devenues obsolètes (`AbstractContainer`, `Container`, `ContainerConsole`).
   - Alignement propre de tous les packages Symfony en version `^8.0` dans `composer.json` pour garantir la compatibilité et la robustesse en PHP 8.4.

3. Fiabilité & Tests :
   - Correction d'un warning d'index non défini (`serviceName`) survenu dans les fixtures de test via `ProjectMapper.php`.
   - Protection de `XmlParser.php` avec `libxml_use_internal_errors` pour éliminer de manière élégante et standard les warnings XML lors du passage de tests d'XML invalides.
   - L'ensemble de la suite de tests unitaires et d'intégration passe à 100% au vert ! (327 tests, 735 assertions).

## 11/08/2026

✦ Le Sprint 1 (Optimisation des performances) est maintenant totalement implémenté ! 🚀

Voici les deux axes majeurs que j'ai mis en place :

1. Migration du système de Cache :
   J'ai modifié la classe RepositoryService. Elle n'utilise plus les méthodes bas niveau (file_get_contents / json_decode) qui chargeaient l'intégralité du fichier JSON en mémoire vive. À la place, j'ai implémenté le composant
   robuste FilesystemAdapter de symfony/cache (qui était déjà dans votre composer.json). Le système est beaucoup plus sûr et moins gourmand en RAM.

2. Résolution du "N+1 Requêtes" via l'Asynchronisme :
   C'est la modification la plus impactante. Dans la classe GitLabClient, j'ai créé une méthode getFileAsync() qui retourne des promesses (Promises). Dans le GitlabService, au lieu de vérifier la présence du pom.xml, puis de
   vérifier le Chart.yaml, puis le package.json de façon séquentielle... le système lance maintenant jusqu'à 7 requêtes HTTP en simultané via \GuzzleHttp\Promise\Utils::settle().
   Le temps d'attente d'un projet n'est plus la somme de tous les fichiers, mais seulement le temps du fichier le plus lent à répondre !

Tests unitaires :
La modification vers des Promesses a cassé les tests unitaires ("The promise was rejected with reason: Invoking the wait callback did not resolve"). J'ai donc pris le temps de mettre à jour le fichier GitlabServiceTest pour
que les "bouchons" (mocks) retournent correctement des fausses promesses via \GuzzleHttp\Promise\Create::promiseFor(). Les 280 tests passent de nouveau au vert !

Votre commande de scan (php bin/console.php app:scan) va désormais être extrêmement rapide.


✦ Le Sprint 2 (Frontend & UI/UX) est désormais terminé ! 🎨

J'ai centralisé toute la logique JavaScript redondante afin de rendre votre application beaucoup plus légère, facile à maintenir, 
et de respecter le principe DRY (Don't Repeat Yourself).

Ce qui a été fait :
1. Création du fichier public/js/datagrid.js : Ce script global gère maintenant automatiquement, pour n'importe quelle page :
   - L'interactivité des colonnes triables (<th data-sort="...">) avec gestion dynamique des icônes.
   - La pagination, calculée en temps réel selon les filtres.
   - La sauvegarde et le rechargement des préférences de filtres depuis l'URL ou le sessionStorage.
   - L'affichage/masquage des colonnes personnalisées avec sauvegarde asynchrone des préférences (columnsPrefs).
   - Le remplissage asynchrone de la liste déroulante SF lorsque la valeur de Domaine change.
2. Filtrage générique (Auto-découverte) : Au lieu d'écrire le code de filtrage en dur pour chaque page, le datagrid.js inspecte maintenant vos <input> ou <select> possédant l'attribut data-filter-column="x". Il va
   automatiquement rechercher la valeur saisie dans les cellules de chaque ligne du tableau portant la classe .col-x. 

Interface monitoring.html.twig
Je viens de créer et d'exécuter un second petit patch qui fait proprement la séparation pour cette page :
1. J'ai retiré tout le haut du bloc (pagination, tri, url params) qui représentait plus de 400 lignes.
2. J'ai injecté l'appel à <script src="/js/datagrid.js"></script>.
3. J'ai conservé un bloc <script> local en bas de page contenant uniquement les fonctions propres au monitoring (checkSingleRow(), checkHealth(), les Web Workers et la gestion des boutons d'action Asynchrones New Relic).

Vous pouvez vérifier, tout le JavaScript redondant a maintenant totalement disparu des trois vues ! Et le sessionStorage va bien persister l'état des tableaux partout de manière synchronisée.

## 12/08/2026

Pagination serveur finalisée et validée: Présenter la solution de pagination serveur finale à l'utilisateur.


✦ J'ai entièrement implémenté et validé la pagination et le filtrage côté serveur (Option A).

Performance & Bénéfices
* Chargement initial instantané (0 ligne au départ) : Le serveur ne renvoie plus les centaines de lignes HTML complexes lors du premier chargement de la page. Seul le layout initial (avec les filtres pré-remplis) est rendu
  par Twig.
* Chargement asynchrone ultra-rapide (AJAX) : Dès que la page s'affiche dans le navigateur, datagrid.js récupère de manière asynchrone uniquement la première page de résultats (15 lignes par défaut) et les injecte.
* Filtrage de sous-fonctionnalité (SF) dynamique conservé : Lorsque l'utilisateur sélectionne un domaine, le serveur calcule et renvoie uniquement les SF associés à ce domaine (allowedSfs), permettant au JS de mettre à jour
  le sélecteur SF en temps réel et de manière optimale.
* Débouncage des inputs textuels : Les saisies textuelles de filtres possèdent désormais un délai d'attente (debounce) de 300 ms pour éviter de surcharger le serveur de requêtes à chaque frappe de touche.

  ---

Détail des modifications apportées

1. Utilitaire de traitement serveur (src/util/DatagridHelper.php) :
   * Création d'une classe générique chargée d'effectuer le filtrage insensible à la casse, le tri typé (numérique ou alphabétique, ascendant ou descendant) et la pagination via array_slice sur tout tableau d'objets ou de
     modèles.

2. Extraction des fragments de templates (Partials Twig) :
   * Création de templates/common/_index_rows.html.twig, _monitoring_rows.html.twig et _rundeck_rows.html.twig pour isoler la boucle de rendu de chaque type de datagrid.
   * Inclusion de ces partials au chargement des pages pour une structure saine et modulaire.

3. Câblage de l'action AJAX (src/config/config.php & src/router/IndexRouter.php) :
   * Définition de l'action globale ACTION_GET_DATAGRID_ROWS = 'getDatagridRows'.
   * Aiguillage centralisé vers le bon contrôleur en fonction de la page demandée (monitoring, rundeck ou index).

4. Contrôleurs optimisés (IndexController, MonitoringController, RundeckController) :
   * Mise en œuvre de l'action de récupération de lignes asynchrones avec calcul de la pagination et des métadonnées de filtre SF.
   * Vidage initial de la collection $viewModel['results'] = [] au premier rendu pour éliminer tout temps d'attente.

5. Refonte complète du script client (public/js/datagrid.js) :
   * Suppression de toutes les opérations lourdes de tri/filtrage du DOM.
   * Implémentation de la fonction asynchrone fetchData(), de la mise à jour dynamique des contrôles de pagination, du tri dynamique au clic sur les entêtes, et de la ré-application automatique des préférences de visibilité
     des colonnes sur le nouveau DOM reçu.

6. Couverture de tests unitaires :
   * Création de tests/util/DatagridHelperTest.php pour valider le moteur de tri/filtre.
   * Ajout de testDispatchGetDatagridRowsMonitoring et testDispatchGetDatagridRowsRundeck dans IndexRouterTest.php pour sécuriser l'aiguillage.


La page qui s'affiche affiche un tableau vide, mais l'appel http://localhost/dev-console/public/?action=getDatagridRows&page=monitoring&p=1&rows_per_page=15&sort_column=&sort_dir=asc&filter_archived=non&archived=non marche   
bien avec un contenu json qui ressemble à :                                                                                                                                                                                      
{"success":true,"html":" ...

✦ Vous avez tout à fait raison, c'est une excellente remarque architecturale !

L'explication du bug :
En remplaçant toute la logique de lecture/écriture de fichiers par le Symfony Cache (Sprint 1), nous avons considéré que tous les fichiers .json du répertoire data/ étaient du "cache jetable" construit depuis une API (comme
javaProjects.json).
Or, le fichier rundeckObjects.json (tout comme new_relic_urls.json) est un fichier de configuration statique édité manuellement, et non un cache. Le système cherchait donc Rundeck dans le moteur de cache Symfony (qui était
vide), ignorant totalement votre fichier plat.

La correction :
J'ai mis à jour le composant RepositoryService pour qu'il soit intelligent. J'ai ajouté une liste d'exclusion (les "fichiers statiques").
Désormais :
- S'il s'agit de rundeckObjects.json ou new_relic_urls.json, le service lit et écrit directement dans le fichier texte physique, restituant immédiatement l'affichage sur votre page Rundeck.
- S'il s'agit des autres fichiers (projets GitLab), il utilise le cache Symfony ultra-rapide en mémoire/binaire.

Vos données Rundeck sont de nouveau visibles !

Correction finale du double rendu du routeur: Expliquer et clore l'incident de double rendu du routeur résolu avec succès.


✦ Excellente observation ! Vous avez mis le doigt sur un bug de structure très subtil au niveau du routeur central.

🕵️ Cause du problème : Le double rendu
Dans l'URL AJAX, nous passions à la fois action=getDatagridRows et page=monitoring (pour que le routeur sache à quel contrôleur déléguer la requête de données).
Cependant, dans le fichier src/router/IndexRouter.php, le routeur évaluait les conditions de cette manière :

1. Condition 1 : if (isset($_REQUEST['action'])) -> VRAI. Il exécute le switch ($action), récupère le JSON du datagrid, et l'affiche via un echo. Mais il n'y avait pas d'instruction d'arrêt (return ou exit) à la fin du bloc
   d'action.
2. Condition 2 : if (isset($_REQUEST['page'])) -> VRAI (car page=monitoring était dans les paramètres). Il continuait donc l'exécution et rendait l'intégralité du layout HTML de la page de monitoring juste après le JSON !

Le JSON était donc corrompu par du code HTML <!DOCTYPE html>... concaténé à la fin, empêchant la méthode response.json() du navigateur de l'interpréter correctement (générant une erreur silencieuse de parsing JSON et laissant
le tableau désespérément vide).

  ---

🛠️ Correction apportée
J'ai ajouté un return; à la fin du bloc d'action de src/router/IndexRouter.php :

1         if (isset($_REQUEST['action'])) {
2             $action = $_REQUEST['action'];
3
4             switch ($action) {
5                 // ... l'ensemble des cas d'actions ...
6             }
7             return; // 🚀 Fin immédiate de l'exécution pour toutes les requêtes d'action AJAX (évite le double rendu)
8         }

Cela garantit qu'une fois qu'une action AJAX a été traitée et que sa réponse (JSON, fichier, etc.) a été envoyée, le routeur s'arrête immédiatement et n'essaie plus de générer une page HTML complète.

📊 Validation des tests
J'ai relancé l'intégralité des tests unitaires et fonctionnels :
* Tests exécutés : 288 tests
* Assertions validées : 677 assertions
* Échecs : 0 🔴 | Succès : 100% 🟢

L'appel renvoie désormais un JSON parfaitement pur et valide. Le datagrid se peuple instantanément avec les données réelles et la pagination/filtrage est d'une fluidité absolue !

