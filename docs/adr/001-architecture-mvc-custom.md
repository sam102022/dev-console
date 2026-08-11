# ADR 001: Architecture MVC sur-mesure (sans Framework complet)

## Statut
Accepté

## Contexte
Le projet "Dev Console" est un dashboard interne visant à consolider et afficher l'état de l'écosystème technique (Projets GitLab, monitoring Actuator, Rundeck). Le besoin initial exigeait une application légère, rapide à déployer, et avec très peu d'adhérence externe (overkill d'utiliser Laravel ou Symfony complet pour un simple affichage).

## Décision
Nous avons pris la décision de développer un mini-framework MVC (Model-View-Controller) "maison" plutôt que de reposer sur un framework complet.

Cette décision implique :
- Un fichier `Kernel.php` dédié à l'amorçage.
- Un conteneur d'injection de dépendances (DI) personnalisé (`Container.php`, `AbstractContainer.php`).
- Un système de routing natif gérant à la fois la CLI (`ConsoleRouter`) et le Web (`IndexRouter`).
- L'utilisation ciblée de composants tiers spécifiques via Composer (Twig pour les vues, Monolog pour les logs, Guzzle pour l'HTTP) sans embarquer l'intégralité d'un framework.

## Conséquences

### Positives
- **Légèreté et Performance :** L'application n'a quasiment aucun temps de démarrage (boot time) car elle ne charge que le strict nécessaire.
- **Contrôle total :** L'équipe maîtrise 100% de la chaîne d'exécution de la requête.
- **Déploiement simplifié :** Moins de configuration serveur requise (pas de compilation de conteneurs complexes, peu de variables d'environnement restrictives).

### Négatives
- **Maintenabilité à long terme :** Le routing, le conteneur de dépendance et la gestion des arguments de console (`$argv`) doivent être maintenus manuellement.
- **Courbe d'apprentissage :** Les nouveaux développeurs doivent apprendre un framework propriétaire au lieu de s'appuyer sur la documentation standard de l'industrie (Symfony/Laravel).
- **Réinvention de la roue :** Des fonctionnalités basiques (gestion des middlewares, sécurité, validation des formulaires) nécessiteront un développement spécifique si le besoin s'en fait sentir.