# ADR 002: Persistance par fichiers JSON (Flat File Database)

## Statut
Accepté

## Contexte
L'application "Dev Console" a besoin de stocker l'inventaire des projets GitLab, leurs composants et leurs métadonnées. L'API GitLab est assujettie au rate-limiting (limite de requêtes) et met du temps à répondre lorsque des centaines de fichiers (`pom.xml`, `package.json`, `Chart.yaml`) doivent être analysés à la volée. Un système de cache est donc indispensable.

## Décision
Nous avons décidé d'utiliser un stockage sous forme de fichiers plats au format JSON (dans le répertoire `data/`) plutôt que de mettre en place une base de données relationnelle (MySQL/PostgreSQL) ou un store en mémoire (Redis/Memcached).

Cette décision est matérialisée par la classe `RepositoryService` qui utilise `file_get_contents` et `json_decode` pour charger la donnée.

## Conséquences

### Positives
- **Simplicité d'installation (Zero-Config) :** L'application ne requiert aucun service externe (ni SGBD, ni serveur Redis). Elle est "Plug & Play" sur tout environnement supportant PHP.
- **Portabilité :** Les données de cache peuvent être facilement inspectées, sauvegardées ou supprimées directement depuis l'explorateur de fichiers.
- **Vitesse de développement :** La sérialisation/désérialisation d'objets en JSON vers le système de fichiers évite la complexité d'un ORM (Doctrine/Eloquent) et les migrations de bases de données.

### Négatives
- **Empreinte Mémoire (RAM) :** La lecture de la base nécessite de charger le fichier `.json` complet en RAM, puis de le désérialiser en un énorme tableau associatif, ce qui n'est pas scalable (problèmes observés à partir de plusieurs milliers d'entrées).
- **Concurrency (Race Conditions) :** Si plusieurs requêtes/utilisateurs (ou un Cron et un utilisateur) tentent d'écrire en même temps dans le fichier JSON, les données peuvent être corrompues ou écrasées.
- **Recherches non optimisées :** L'absence d'index oblige l'application à itérer sur *tous* les tableaux PHP (`array_find()`, `foreach`) pour trouver un projet spécifique.