# Sprint 9 : Automatisation de la Qualité & Tests E2E 🧪

## 🎯 Objectif
Introduire des tests de bout en bout (End-to-End) afin de garantir la robustesse des interactions complexes du frontend (tri, filtrage, asynchronisme) et automatiser la validation de la qualité de code.

## 📝 Contexte
L'application dispose d'une bonne suite de tests unitaires PHPUnit sur la couche métier (services, parseurs), mais aucune validation n'est automatisée sur l'interface graphique. Or, une grande partie de la valeur d'usage repose sur des comportements côté client (JS fetch des Health Checks, masquage dynamique de lignes, persistance sessionStorage).

## 🛠️ Tâches (User Stories)

1. **Intégration d'un Framework de Test E2E (Playwright ou Cypress)**
   - *Description* : Configurer une suite de tests automatisés qui lance un navigateur headless pour interagir avec la console comme un véritable utilisateur.
   - *Tâche technique* : Initialiser Playwright ou Cypress dans le répertoire racine de l'application et configurer l'URL de base locale pour les scénarios.

2. **Écriture des Scénarios de Test UI Clés**
   - *Description* : Couvrir les parcours utilisateurs indispensables pour éviter toute régression visuelle ou interactive.
   - *Tâche technique* : Écrire des scénarios de test validant :
     - La recherche textuelle dans la grille de projets et le masquage correct des lignes non correspondantes.
     - L'activation correcte d'un filtre par environnement ou technologie et la persistance de cette sélection au rechargement de la page.
     - Le comportement du bouton d'actualisation de la page de monitoring (asynchronisme du fetch).

3. **Mise en place d'une CI/CD complète**
   - *Description* : Lancer automatiquement l'analyse de qualité et les tests à chaque commit ou Pull Request.
   - *Tâche technique* : Enrichir le workflow GitHub Actions (`.github/workflows/php.yml`) pour exécuter l'analyse statique PHPStan, PHPUnit, et lancer la suite de tests E2E.

## ✅ Definition of Done (DoD)
- La suite de tests E2E est installée et configurable via une simple commande (ex: `npm run test:e2e`).
- Les scénarios clés de l'interface (recherche, filtres, monitoring) sont validés de bout en bout avec succès.
- La CI Github Actions bloque la fusion des Pull Requests si l'un des tests E2E, tests unitaires ou règles d'analyse statique échoue.
