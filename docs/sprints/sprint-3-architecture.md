# Sprint 3 : Architecture & Maintenabilité 🏗️

## 🎯 Objectif
Moderniser les fondations de l'application, résoudre les problèmes d'encodage et augmenter la fiabilité grâce à de meilleurs tests.

## 📝 Contexte
L'application utilise un framework MVC "maison" (Kernel, Container d'Injection de Dépendances manuel, Routeur personnalisé). 
Bien qu'efficace, ce choix limite l'intégration d'outils standards de l'écosystème PHP et rend la courbe d'apprentissage plus complexe pour un nouveau développeur. 

## 🛠️ Tâches (User Stories)

1. **Assainissement des Encodages**
   - *Description* : Certains fichiers PHP ont été enregistrés avec un mauvais encodage, provoquant des caractères corrompus (ex: `cÅ“ur`, `dÃ©pendances` dans `Kernel.php`).
   - *Tâche technique* : Ré-enregistrer l'intégralité du code source (particulièrement `src/Kernel.php` et les traductions) en `UTF-8 sans BOM`.

2. **Migration douce vers des composants Symfony standards (Optionnel mais recommandé)**
   - *Description* : Tirer parti de Symfony Flex / Micro-Kernel pour la base applicative, sachant que PHP 8.4 et des bundles Symfony (twig-bundle) sont déjà configurés.
   - *Tâche technique* : Remplacer l'`AbstractContainer` manuel par l'Autowiring natif de Symfony. Remplacer `ConsoleRouter` par le composant `symfony/console` (`make:command`).

3. **Couverture de tests unitaires**
   - *Description* : Atteindre 80% de couverture de code pour assurer une résilience totale lors des refactorings.
   - *Tâche technique* : Cibler en priorité la classe `AppConfig` (10% actuellement) et `FileService` (71%).

## ✅ Definition of Done (DoD)
- Tous les fichiers du code source sont strictement en UTF-8, sans caractères illisibles.
- Le `composer.json` et les services sont alignés sur des standards modernes si la migration du Kernel est actée.
- La commande `phpunit --coverage-text` affiche une couverture métier > 80% sur la couche Service/Configuration.