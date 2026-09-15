# ADR 005: Systématisation des tests unitaires (complets et paramétrés)

## Statut
Accepté

## Contexte
L'application "Dev Console" s'enrichit rapidement de nouvelles fonctionnalités à chaque sprint (gestion des utilisateurs, authentification, paramètres, intégration Rundeck/Postman). À mesure que la base de code grandit, le risque de régressions lors des refactorisations ou des évolutions de sprints augmente de manière significative.
Pour garantir la robustesse à long terme de l'application et sécuriser la livraison continue, il est indispensable de disposer d'une couverture de tests rigoureuse et automatisée.

## Décision
Nous prenons la décision de systématiser l'écriture de tests unitaires et d'intégration complets pour chaque nouvelle classe et chaque nouvelle méthode créée.

Cette décision implique l'application stricte des règles suivantes :
- **Couverture systématique :** Chaque nouvelle classe (contrôleur, service, utilitaire, router) doit s'accompagner d'une classe de test unitaire correspondante dans le répertoire `tests/`.
- **Privilégier les tests paramétrés (Parameterized Tests) :** Pour chaque méthode testée, utiliser systématiquement les Data Providers de PHPUnit (via l'attribut `#[DataProvider]`) pour valider une large matrice de scénarios (cas nominaux, valeurs limites, cas d'erreurs, injections de données inattendues) en minimisant la duplication de code de test.
- **Isolation par double de test (Mocking) :** Isoler le code testé en mockant rigoureusement ses dépendances (base de données SQLite via `RepositoryService`, appels HTTP via clients d'API, moteur de rendu Twig, etc.) afin d'avoir des tests déterministes, rapides et indépendants de l'environnement extérieur.
- **Validation avant intégration :** La suite de tests globale doit être exécutée et être intégralement verte en local (`composer test`) avant toute validation de Pull Request ou de déploiement en production.

## Conséquences

### Positives
- **Excellente non-régression :** Détection instantanée de tout effet de bord indésirable lors de l'ajout de fonctionnalités ou du nettoyage du code.
- **Documentation vivante par le code :** Les tests paramétrés décrivent de manière explicite et exécutable l'ensemble des comportements et cas limites gérés par une méthode.
- **Rigueur de conception :** Concevoir le code pour qu'il soit testable pousse naturellement les développeurs à écrire des classes plus découpées, respectant le principe de responsabilité unique (SRP) et limitant le couplage fort.
- **Confiance absolue lors des refactorisations :** Offre aux équipes une liberté totale pour améliorer l'architecture existante sous la protection de la suite de tests.

### Négatives
- **Effort initial accru :** Écrire des tests complets et paramétrés pour chaque méthode requiert un temps de conception et de codage supplémentaire lors du développement.
- **Coût de maintenance :** Les tests et les mocks associés doivent être maintenus à jour en cas de modification des signatures de méthodes ou d'évolutions de l'architecture logicielle.
