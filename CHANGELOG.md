# Changelog

Historique de tous les changements notables du projet dev-console

Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).


## [1.9.0] - 08/09/2026

### Added

- Introduction d'un système d'Authentification complet et sécurisé avec page de connexion (`/login`) et gestion sécurisée des sessions.
- Mise en place du Contrôle d'Accès basé sur les Rôles (RBAC) distinguant `ROLE_USER` (dashboards en lecture seule) et `ROLE_ADMIN` (administration avancée et CRUD utilisateurs).
- Création d'une interface d'administration complète et réactive de Gestion des Utilisateurs (CRUD) réservée aux administrateurs.
- Implémentation du flux autonome de récupération de Mot de Passe Perdu avec génération cryptographique de jeton (token) d'entropie élevée (expire après 1 heure) et simulation d'envoi d'email sécurisé (consigné dans les logs `var/logs/forgot_password.log`).

### Changed

- Centralisation et renforcement de l'initialisation des cookies de session PHP (`cookie_secure`, `cookie_httponly` et SameSite `Strict`) au point d'entrée de l'application (`public/index.php`).
- Mise à jour du routeur central `IndexRouter` pour servir de Middleware de sécurité : interdiction absolue d'accès aux non-authentifiés (redirection automatique) et restriction des routes admins aux seuls `ROLE_ADMIN` (renvoi de statut HTTP 403).
- Intégration de la table `users` et de l'administrateur par défaut (`admin@mdm.com` / `admin`) au schéma de la base SQLite gérée par le `RepositoryService`.
- Amélioration de l'en-tête global `base.html.twig` pour masquer la barre de navigation aux utilisateurs anonymes, et afficher l'identité, le rôle de l'utilisateur connecté et un bouton de déconnexion.

### Fixed

- Correction et adaptation des tests unitaires existants (notamment `IndexRouterTest`) pour s'exécuter sous un contexte de session d'administration simulée et d'autowiring conforme.

## [1.8.0] - 08/09/2026

### Added

- Intégration globale d'Alpine.js (`3.x` via CDN) pour moderniser l'interactivité de l'interface utilisateur.
- Création d'un composant de grille dynamique unifié et hautement réactif nommé `datagrid` sous Alpine.js.
- Implémentation d'une pagination entièrement déclarative et réactive pilotée par l'état d'Alpine, éliminant la génération d'HTML dans le code JS.
- Support de la multi-sélection des projets pour la page de monitoring avec gestion réactive de l'état "Tout cocher/décocher" (`checkedProjects`).

### Changed

- Refonte complète de `public/js/datagrid.js` pour éliminer l'impératif Vanilla JS au profit de la réactivité déclarative d'Alpine.js.
- Allègement massif des templates Twig (`monitoring.html.twig` réduit de plus de 50% en taille) en extrayant et encapsulant l'intégralité du JavaScript comportemental (monitoring health checks, timeouts, New Relic url loading) au sein du composant Alpine.
- Amélioration de la gestion de l'affichage des colonnes : les en-têtes et filtres de colonnes masquées se cachent désormais dynamiquement à l'aide de directives `x-show`.

### Fixed

- Résolution des problèmes de désynchronisation d'état grâce à l'association bidirectionnelle des filtres et contrôles de tri via `x-model`.

## [1.7.0] - 08/09/2026

### Added

- Introduction d'un système de base de données relationnelle SQLite zero-configuration (`data/database.sqlite`), résolvant les problèmes de concurrence et d'empreinte mémoire liés aux fichiers plats JSON.
- Création automatique du schéma des tables (`projects`, `gitlab_projects`, `rundeck_projects`, `cache_store`) et des index SQL associés.
- Implémentation d'une nouvelle méthode hautement performante `findProjectByName` dans `RepositoryService` pour une recherche directe indexée.

### Changed

- Refactoring complet du `RepositoryService` pour s'interfacer avec SQLite à l'aide de requêtes préparées PDO sécurisées et de transactions transactionnelles par lot.
- Optimisation de la récupération individuelle d'un projet dans `ProjectRepository::findByCode` (recherche en $O(1)$) en remplaçant la lecture globale du cache de fichiers JSON par une requête SQL ciblée.
- Amélioration de l'adaptabilité du `RepositoryService` avec un mécanisme transparent de repli (fallback) sur un cache de fichiers plat (`FilesystemAdapter`) si PDO/SQLite n'est pas disponible.

### Fixed

- Mise à jour et correction de la suite de tests unitaires (`ProjectRepositoryTest` et `RepositoryServiceTest`) pour intégrer proprement le comportement SQLite sur système de fichiers virtuel (vfsStream).

## [1.6.0] - 02/09/2026

### Changed

- Remplacement du framework MVC et conteneur d'injection de dépendances customisé "maison" par le Micro-Kernel standard de Symfony (autowiring/autoconfigure).
- Déportation de l'instanciation des services vers un fichier `src/config/services.yaml` et un nouveau service d'usine `App\factory\ServiceFactory`.
- Suppression des classes de conteneurs obsolètes (`AbstractContainer`, `Container`, `ContainerConsole`).
- Remplacement des requêtes et injections de conteneurs manuels par l'interface `ContainerInterface` de Symfony.
- Alignement global de tous les composants Symfony en version `^8.0` dans le fichier `composer.json`.

### Fixed

- Correction d'un avertissement d'index non défini (`serviceName`) dans `ProjectMapper.php`.
- Sécurisation du parseur `XmlParser.php` avec `libxml_use_internal_errors` pour éliminer les warnings de parseur XML lors des tests unitaires d'XML invalides.

## [1.5.0] - 02/09/2026

### Added

- Création d'un fichier `.htaccess` racine pour bloquer l'accès direct aux répertoires sensibles (`data/`, `src/`, `vendor/`, `.git/`) et fichiers de configuration (`.env`).
- Rédaction d'un rapport de décision d'architecture (ADR 004) pour l'isolation du Document Root.
- Planification de la feuille de route future avec la définition des Sprints 5 à 9.

## [1.4.0] - 11/08/2026

### Changed

- Centralisation du JavaScript (DRY)

## [1.3.0] - 11/08/2026

### Changed

- Remplacement des requêtes synchrones par des requêtes asynchrones (Guzzle)

## [1.2.0] - 17/06/2026

### Added

- Interface rundeck

## [1.1.0] - 17/06/2026

### Added

- Interface monitoring

## [1.0.0] - 22/05/2026

### Added

- Initialisation du projet - 

