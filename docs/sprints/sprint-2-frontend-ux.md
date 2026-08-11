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