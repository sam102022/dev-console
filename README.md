# Documentation - Dev Console & Monitoring

## 📋 Présentation
Application développée en PHP 8.4 avec Twig, servant de tableau de bord (dashboard) centralisé pour le suivi des microservices et des projets hébergés sur GitLab. 

Elle offre une vue d'ensemble instantanée sur l'écosystème technique, les versions déployées (Java, Spring Boot, MDM Workload), et propose des outils de monitoring avec accès rapides en temps réel.

## 🚀 Fonctionnalités Principales

L'interface se divise en 3 écrans principaux :

1. **Projets GitLab** (`/`): 
   - Inventaire de tous les projets scannés.
   - Remontée d'indicateurs clés : langage (techno), version de Java, de Spring Boot, présence sur GCP (Google Cloud Platform), etc.
   - Alertes et statuts de santé technologique (ex: "Java obsolète", "Spring Boot ancien").

2. **Monitoring & Liens** (`/?action=monitoring`) :
   - Regroupement de liens pratiques par environnement (DEV, REC, PP, PROD) : Frontends, accès aux Logs, PubSub, console GCP, et tableaux de bord New Relic.
   - **Vérification de santé (Health Checks)** : Des appels asynchrones (ping via les endpoints Actuator de Spring Boot) sont réalisés vers les différents environnements. L'interface affiche si le service est `UP` ou `DOWN`, et quelle version précise y est actuellement déployée.

3. **Rundeck** (`/?action=rundeck`) :
   - Vue dédiée aux jobs Rundeck associés aux projets (souvent des projets de type batch), avec des liens pointant directement vers les environnements associés.

*Note : L'expérience utilisateur est optimisée par une persistance des filtres de recherche (Domaine, Sous-fonction, etc.). Ces filtres sont partagés de manière synchronisée entre les 3 pages via le `sessionStorage` du navigateur.*

## 🏗️ Architecture Technique

L'application repose sur un mini-framework MVC (Model-View-Controller) robuste et fait sur-mesure pour être léger et très rapide :

- **Kernel & Injection de dépendances** : Le point d'entrée `Kernel.php` amorce l'application. Un conteneur d'injection de dépendances (`Container.php`, `AbstractContainer.php`) gère l'instanciation des contrôleurs, des parseurs, des services et des clients HTTP.
- **Routing** : Des routeurs distincts existent pour le web (`IndexRouter`) et pour la CLI (`ConsoleRouter`).
- **Moteur de Template** : Les vues HTML sont générées en utilisant le standard **Twig** (`templates/*.html.twig`), alimentées par des **ViewModels** (`src/viewModel/*`).
- **Mise en cache (Repositories & Data)** : Afin de rester performante et d'éviter les limitations d'appels API (rate-limiting) de GitLab, l'application met les résultats de ses analyses en cache sous forme de fichiers JSON dans le dossier `data/` (ex: `gitlabProjects.json`, `javaProjects.json`).

### Mécanisme de Scan GitLab (`GitlabService`)
Lorsqu'un scan est déclenché, l'application parcourt l'arborescence des groupes et projets de GitLab :
1. Extraction du **Domaine** et de la **Sous-Fonction (SF)** à partir de la structure hiérarchique du groupe GitLab.
2. Analyse de fichiers de configuration clés au sein des repositories :
   - `pom.xml` (Maven/Java) ou `package.json` (NPM/JS) pour cibler la stack technique et récupérer la version des frameworks (ex: Spring Boot).
   - `chart/Chart.yaml` pour analyser la version MdmWorkload et savoir si le projet a été migré sur Google Cloud (GCP).
   - `deploy/conf/dev/deploy.yml` pour les informations de sondes (liveness probes).
   - `src/main/resources/application.yml` pour détecter d'éventuels paramètres d'abonnement.
3. Déduction intelligente des URL des divers environnements (Santé, Logs, Front) à l'aide de helpers dédiés (`MonitoringUtils`).

## ⚙️ Configuration & Installation

### Prérequis
- Serveur ou environnement **PHP 8.4+**
- Extensions PHP activées : `curl`, `json`, `mysqli`, `simplexml`.
- **Composer** (pour les dépendances PHP : Monolog, Twig, Guzzle).

### Paramétrage via `.env`
Toute la configuration est pilotée via un fichier d'environnement (ex: `.env`). La classe `EnvLoader` est chargée de parser ces propriétés et d'alimenter `AppConfig` et `ParamGitLab`.

Variables majeures :
- `gitlab_url`, `gitlab_token` : Identifiants pour dialoguer avec l'API GitLab.
- `gitlab_path_group_default` : Le groupe/namespace GitLab racine à partir duquel le scan en profondeur débute (ex: `core/dev/pdv`).
- `exclude_projects` : Noms de projets spécifiques à ne pas afficher et ne pas scanner (séparés par des virgules).
- `exclude_domains` : Permet d'ignorer tout un pan fonctionnel du dashboard. Supporte la correspondance par expressions de type "wildcard" grâce à `fnmatch` (ex: `exclude_domains=sandbox,mdm-*`).
- Informations New Relic (`new_relic_account_id`, `new_relic_api_key`), E107...

## 💻 Ligne de Commande (CLI)

L'application expose des commandes exécutables en console. Celles-ci sont primordiales pour actualiser le dashboard de manière programmée.

- **Mettre à jour le cache (Scanner)** : 
  `php bin/console.php app:scan`
  Cette commande force le parcours de l'API GitLab, parse tous les nouveaux fichiers et écrase les fichiers de cache `.json`. 
- Il est fortement recommandé d'exécuter cette commande via un job planifié (Cron) plusieurs fois par jour.

## 🧪 Tests Automatisés

Le projet dispose d'une suite exhaustive de tests unitaires et d'intégration basés sur **PHPUnit** (disponible dans le dossier `tests/`).
- Pour lancer les tests, exécutez la commande : `vendor/bin/phpunit`
- Les fichiers de configuration PHPUnit (`phpunit.xml`, `phpunit-it.xml`) définissent les suites et règles de couverture de code.