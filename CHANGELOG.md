# Changelog

Historique de tous les changements notables du projet dev-console

Format basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).


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

