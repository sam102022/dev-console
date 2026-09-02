# Sprint 6 : Persistance & Robustesse de Données (SQLite & Cache) 💾

## 🎯 Objectif
Migrer le système de persistance sur fichiers JSON (`data/javaProjects.json`) vers une solution de base de données relationnelle légère (SQLite) ou un cache Key-Value plus robuste afin de résoudre les problèmes de concurrence et d'empreinte mémoire.

## 📝 Contexte
Actuellement, l'application lit et écrit la totalité du cache GitLab sous forme d'un gros fichier JSON unique, ce qui impose de charger tout le jeu de données en RAM et pose de gros risques d'écrasement ou de corruption lors d'écritures concurrentes.

## 🛠️ Tâches (User Stories)

1. **Mise en place d'une base de données SQLite (Zero-Config)**
   - *Description* : Introduire SQLite pour stocker de manière structurée et indexée les projets, leurs composants et leurs métadonnées.
   - *Tâche technique* : Créer le schéma de base de données pour la table des projets et configurer un service de connexion PDO/SQLite sans dépendances externes lourdes.

2. **Refactoring de RepositoryService**
   - *Description* : Réécrire `RepositoryService` et les repositories enfants (`GitLabRepository`, `RundeckRepository`) pour lire et écrire dans la base SQLite plutôt que dans des fichiers plats JSON.
   - *Tâche technique* : Implémenter des requêtes SQL ciblées avec requêtes préparées pour la sélection, l'insertion et la mise à jour sélective d'un projet, supprimant ainsi le besoin de réécrire tout le fichier à chaque modification.

3. **Intégration d'un cache Key-Value performant**
   - *Description* : Optimiser le temps de lecture des informations chaudes (statuts Actuator, configurations temporaires) via `symfony/cache`.
   - *Tâche technique* : Utiliser `FilesystemAdapter` ou `ApcuAdapter` pour mettre en cache des objets spécifiques avec un TTL (Time to Live) défini.

## ✅ Definition of Done (DoD)
- Plus aucun gros fichier JSON global n'est écrit ou lu en entier lors de la navigation ou d'un scan.
- Les données de projets sont persistées de manière persistante et sécurisée dans une base de données SQLite (`data/database.sqlite`).
- Le temps d'accès à un projet individuel est instantané (recherche indexée $O(1)$ ou $O(\log N)$).
- Les tests d'intégration et les repositories fonctionnent parfaitement avec la nouvelle couche d'accès aux données.
