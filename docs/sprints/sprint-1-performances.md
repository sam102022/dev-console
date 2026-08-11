# Sprint 1 : Optimisations des Performances 🚀

## 🎯 Objectif
Réduire drastiquement le temps d'exécution de la commande de scan (`app:scan`) et diminuer l'empreinte mémoire de l'application lors de la lecture des données en cache.

## 📝 Contexte
Actuellement, le scan GitLab souffre du problème "N+1 requêtes" : pour chaque projet, plusieurs appels HTTP synchrones sont faits pour vérifier l'existence de fichiers (`pom.xml`, `Chart.yaml`, etc.). De plus, le stockage et la lecture du cache via de gros fichiers JSON (`javaProjects.json`) pèsent sur la RAM (utilisation de `json_encode`/`json_decode` sur des chaînes massives).

## 🛠️ Tâches (User Stories)

1. **Remplacer les requêtes synchrones par des requêtes asynchrones (Guzzle)**
   - *Description* : Modifier `GitlabService` pour utiliser les requêtes concurrentes (Promises ou Pool) de Guzzle.
   - *Tâche technique* : Regrouper les vérifications de fichiers par projet et les lancer en parallèle.
   
2. **Migration du système de Cache (JSON vers Symfony Cache)**
   - *Description* : Abandonner `file_put_contents` et `json_decode` au profit du composant `symfony/cache` (déjà présent dans le `composer.json`).
   - *Tâche technique* : Implémenter le `FilesystemAdapter` ou le `RedisAdapter` dans le `RepositoryService`. Cela permettra de lire/écrire les projets individuellement au lieu de charger un énorme tableau en mémoire.

## ✅ Definition of Done (DoD)
- Le temps d'exécution de la commande `php bin/console.php app:scan` est divisé par au moins 5.
- La mémoire RAM utilisée lors du chargement des pages web (Index, Monitoring, Rundeck) est réduite.
- Les tests unitaires de `GitlabService` et `RepositoryService` passent avec succès.