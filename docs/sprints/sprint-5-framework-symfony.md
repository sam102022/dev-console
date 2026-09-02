# Sprint 5 : Modernisation du Framework (Symfony Micro-Kernel) 🏗️

## 🎯 Objectif
Remplacer le framework MVC "maison" par un Micro-Kernel Symfony standard afin d'améliorer la maintenabilité, faciliter l'onboarding et tirer parti des outils éprouvés de l'écosystème PHP.

## 📝 Contexte
L'application s'appuie sur une structure MVC customisée (Kernel, Container d'injection de dépendances manuel et routeur propriétaire). Bien que performante, cette approche exige de maintenir manuellement des mécanismes d'infrastructure bas niveau qui existent déjà de manière plus robuste et standardisée dans des frameworks comme Symfony.

## 🛠️ Tâches (User Stories)

1. **Amorçage du Micro-Kernel Symfony**
   - *Description* : Mettre en place la classe de Kernel standard de Symfony et configurer les points d'entrée (Web et CLI) pour qu'ils l'utilisent.
   - *Tâche technique* : Installer `symfony/http-kernel` et `symfony/framework-bundle` si nécessaire, initialiser `src/Kernel.php` étendant `Symfony\Component\HttpKernel\Kernel`, et rediriger `public/index.php` et `bin/console.php`.

2. **Migration vers le Container d'Injection de Dépendances Standard (Autowiring)**
   - *Description* : Abandonner les conteneurs manuels (`Container.php`, `AbstractContainer.php`) au profit du conteneur de services natif de Symfony avec configuration automatique (autowiring/autoconfigure).
   - *Tâche technique* : Déclarer les services dans un fichier `config/services.yaml` standard et supprimer les classes de conteneur personnalisées obsolètes.

3. **Intégration du composant Console Standard**
   - *Description* : Migrer le routeur console propriétaire vers `symfony/console`.
   - *Tâche technique* : Adapter `ScanCommand` et `ScanSymfonyCommand` pour hériter directement de `Symfony\Component\Console\Command\Command` et les enregistrer dans le conteneur.

## ✅ Definition of Done (DoD)
- L'application démarre et répond avec succès via le Micro-Kernel Symfony.
- L'injection de dépendances utilise l'autowiring standard de Symfony sans erreur.
- La suite de tests unitaires PHPUnit passe entièrement avec la nouvelle configuration de services.
