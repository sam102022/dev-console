# ADR 003: Front-end en Twig et Vanilla JavaScript

## Statut
Accepté

## Contexte
La "Dev Console" possède trois interfaces clés nécessitant une forte interactivité : filtrage en temps réel sur des tableaux, persistance des choix, tris multicritères et requêtes asynchrones pour le "Health Check" des Actuators. 

## Décision
Plutôt que de construire une architecture Single Page Application (SPA) avec un framework frontend lourd (React, Vue.js, Angular) communiquant avec une API backend, nous avons choisi une approche **Server-Side Rendering (SSR)** couplée à du **Vanilla JavaScript**.

- **Rendu HTML :** Le composant `twig/twig` génère le HTML brut depuis le serveur PHP.
- **Interactivité :** Du JavaScript natif (ES6+), intégré directement dans les balises `<script>` des templates ou via des fichiers séparés, manipule le DOM (ex: cacher/afficher des lignes de tableau (`display: none`)).
- **Asynchronisme :** L'API `fetch()` native est utilisée pour interroger les endpoints locaux (`?action=getMonitoringData`).

## Conséquences

### Positives
- **Simplicité de la stack :** Pas de processus de build côté front-end (Webpack, Vite, Babel). Le code écrit est directement exécuté par le navigateur.
- **Référencement (SEO) / Accessibilité :** Bien que peu critique pour un outil interne, le HTML est généré côté serveur.
- **Déploiement direct :** Une seule codebase (PHP/HTML/JS) à déployer sur le serveur web.

### Négatives
- **Duplication de logique (Manque de composants) :** Contrairement à React ou Vue.js, le Vanilla JS couplé à Twig favorise la duplication de code (ex: la logique complexe des datatables, filtres et pagination dupliquée entre les différentes vues).
- **Gestion d'état complexe :** Synchroniser l'état (State) entre l'URL, le `sessionStorage`, le DOM des `select`, et l'affichage des lignes du tableau est fastidieux et sujet aux bugs.
- **Taille des templates :** Les fichiers comme `monitoring.html.twig` deviennent extrêmement lourds (+900 lignes) car ils concentrent la structure HTML et la logique comportementale JS.