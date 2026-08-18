# Sprint 2 : Frontend & UI/UX 🎨

## 🎯 Objectif
Améliorer la maintenabilité du code côté client en évitant la redondance et potentiellement décharger le serveur lors des vérifications de santé.

## 📝 Contexte
Les fichiers Twig (`index.html.twig`, `monitoring.html.twig`, `rundeck.html.twig`) contiennent des centaines de lignes de JavaScript presque identiques (gestion des filtres dynamiques, système de tri sur les colonnes, pagination, `sessionStorage`).

## 🛠️ Tâches (User Stories)

1. **Refactoring : Centralisation du JavaScript (DRY)**
   - *Description* : Extraire toute la logique de `datatable` (tri, pagination, filtres globaux) dans des fichiers JavaScript statiques.
   - *Tâche technique* : Créer un ou plusieurs fichiers dans `public/js/` (ex: `datagrid.js`, `filters.js`), exposer des fonctions configurables et les inclure à la fin des fichiers Twig.
   
2. **Étude et POC : Health Checks côté Client (Optionnel/À valider)**
   - *Description* : Actuellement, le client fait un fetch vers `/?action=getMonitoringData` et le serveur PHP fait la requête HTTP vers les Actuators. Si les règles CORS de l'entreprise le permettent, le navigateur pourrait ping directement les serveurs.
   - *Tâche technique* : Réaliser un Proof of Concept pour vérifier si un appel `fetch()` JavaScript direct vers un actuator de l'environnement DEV passe sans erreur CORS.

## ✅ Definition of Done (DoD)
- Le poids des fichiers `*.html.twig` est considérablement réduit.
- Toute modification sur le système de pagination se fait à un seul endroit.
- Aucune régression sur les fonctionnalités de filtrage, tri et synchronisation des sessions.

--- 

✦ D'après l'analyse du document de spécification du Sprint 2 (docs/sprints/sprint-2-frontend-ux.md) et de l'architecture réseau/applicative du projet, la mise en place d'un Health Check directement côté client (navigateur) est
fortement déconseillée, voire techniquement impossible pour plusieurs raisons majeures :

1. L'obstacle du CORS (Cross-Origin Resource Sharing)
* Le problème : Votre console de développement s'exécute sur une origine spécifique (ex: http://localhost ou http://dev-console.maisonsdumonde.net). Les URLs des Actuators ciblés se trouvent sur des sous-domaines différents
  (ex: https://management-[projet].[env].maisonsdumonde.net).
* L'impact : Par mesure de sécurité, le navigateur bloquera automatiquement toute requête fetch() directe vers ces Actuators, à moins que chaque microservice de l'entreprise n'ait été spécifiquement configuré pour renvoyer
  l'en-tête Access-Control-Allow-Origin autorisant votre console. Maintenir cette configuration sur des dizaines de projets est irréaliste et peu sécurisé.

2. La gestion des certificats SSL (Auto-signés ou PKI interne)
* Le problème : En environnement de développement (DEV ou REC), les Actuators utilisent souvent des certificats SSL émis par une autorité de certification (CA) interne à l'entreprise ou auto-signés.
* L'impact : Le navigateur d'un développeur lèvera une alerte de sécurité ("Connexion non privée") et bloquera les appels fetch() en arrière-plan. L'utilisateur devrait alors ouvrir manuellement l'URL de l'Actuator de chaque
  projet dans un nouvel onglet pour "Accepter le risque" afin que le Javascript de la console puisse enfin le contacter. Côté backend, Guzzle gère cela de manière transparente en utilisant le bundle de CA de l'entreprise ou
  en désactivant proprement la vérification si nécessaire.

3. Les restrictions réseau et cloisonnement des Firewalls
* Le problème : Les endpoints de management (/actuator/...) sont critiques. Ils sont souvent cloisonnés dans des sous-réseaux privés (VPCs, clusters GKE privés) ou restreints aux adresses IP de serveurs spécifiques.
* L'impact : Bien que le serveur hébergeant la dev-console possède les autorisations de routage nécessaires pour interroger ces adresses privées, le poste local d'un développeur (même connecté au VPN) se verra fréquemment
  refuser l'accès direct par les Firewalls de l'entreprise, en particulier pour les environnements de production (PROD).

4. Les limites de connexions simultanées du navigateur
* Le problème : Les navigateurs modernes limitent drastiquement le nombre de connexions HTTP simultanées (généralement 6 connexions maximum).
* L'impact : Si vous lancez le Health Check de 30 projets en parallèle directement depuis le navigateur, les requêtes vont être mises en file d'attente (queuing), provoquant des lenteurs et de faux faux-positifs de Timeout
  (T/O). Le backend, quant à lui, peut paralléliser ces requêtes sans cette limite du navigateur.

  ---

💡 Recommandation
Comme le suggérait la tâche optionnelle du Sprint 2 ("À valider / POC"), il est indispensable de conserver l'approche actuelle de Proxy Backend (?action=getMonitoringData).

Le serveur PHP (via Guzzle) sert de passerelle sécurisée et robuste :
1. Pas de CORS : Les restrictions CORS s'appliquent uniquement aux navigateurs, pas aux communications serveur à serveur.
2. Routage réseau garanti : Le serveur a un accès direct et privilégié aux infrastructures (GCP, clusters GKE, etc.).
3. Sécurité : Aucune clé, certificat ou configuration réseau interne n'est exposée au client.

