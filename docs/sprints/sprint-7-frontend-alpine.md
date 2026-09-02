# Sprint 7 : Composantisation & Interactivité Front-end 🎨

## 🎯 Objectif
Améliorer la maintenabilité du code côté client (JavaScript) en éliminant les duplications dans les fichiers de templates Twig et en introduisant une gestion d'état légère et réactive.

## 📝 Contexte
Les pages `index`, `monitoring` et `rundeck` possèdent des fonctionnalités d'affichage dynamique très similaires (tri par colonne, filtrage en temps réel, pagination). Actuellement, une grande partie du JavaScript manipulant le DOM est dupliquée ou intégrée directement dans des balises `<script>` au sein des templates Twig, rendant la maintenance extrêmement laborieuse (ADR 003).

## 🛠️ Tâches (User Stories)

1. **Extraction et Centralisation de la Logique Datatable (Vanilla JS / DRY)**
   - *Description* : Extraire les fonctions de tri, filtrage textuel et pagination dans une classe ou un module JavaScript partagé.
   - *Tâche technique* : Créer un fichier `public/js/datagrid.js` contenant un composant réutilisable, l'importer dans `base.html.twig` et l'instancier sur chaque page de tableau.

2. **Intégration d'Alpine.js pour une réactivité sans lourdeur**
   - *Description* : Faciliter la synchronisation de l'état UI (les select de filtre, l'input de recherche, les pages actives) avec le DOM en utilisant Alpine.js, qui s'intègre parfaitement avec le rendu côté serveur (Twig) sans nécessiter de chaîne de compilation (Vite/Webpack).
   - *Tâche technique* : Charger Alpine.js via CDN ou asset local, et réécrire les parties interactives des formulaires et des tableaux à l'aide de directives déclaratives comme `x-data`, `x-model`, et `x-show`.

3. **Optimisation et Allègement des Fichiers Twig**
   - *Description* : Réduire la taille des fichiers templates Twig en supprimant les blocs JS monolithiques internes.
   - *Tâche technique* : Nettoyer `monitoring.html.twig` et d'autres fichiers pour déporter l'intégralité du JS comportemental dans les fichiers statiques de `public/js/`.

## ✅ Definition of Done (DoD)
- Le poids global et la complexité des fichiers `*.html.twig` sont réduits d'au moins 40%.
- Toutes les tables dynamiques de l'application partagent les mêmes fonctions de tri et de filtrage.
- L'affichage se met à jour de façon fluide et réactive via Alpine.js sans bugs de désynchronisation de l'état de l'interface.
