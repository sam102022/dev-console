# Sprint 8 : Authentification & Sécurité d'Accès (RBAC) 🔐

## 🎯 Objectif
Sécuriser l'accès au tableau de bord "Dev Console" en mettant en place une authentification des utilisateurs et un contrôle d'accès basé sur les rôles (RBAC).

## 📝 Contexte
L'application expose des informations sensibles sur l'infrastructure interne (domaines, variables d'environnements, configurations de builds) mais ne possède pas de mécanisme d'authentification utilisateur global. Actuellement, n'importe qui ayant accès au réseau interne peut consulter ou modifier des données d'administration (`AdminContext`).

## 🛠️ Tâches (User Stories)

1. **Création d'un système de Login et Session**
   - *Description* : Ajouter une page de connexion (`/login`) et gérer les sessions utilisateur de manière sécurisée.
   - *Tâche technique* : Créer un contrôleur `AuthController`, un template Twig de login et vérifier l'existence de la session de l'utilisateur sur les routes protégées via un mécanisme de Middleware ou d'Event Listener (idéalement fourni par Symfony Security si migré).

2. **Mise en place du Contrôle d'Accès par Rôles (RBAC)**
   - *Description* : Définir deux rôles principaux : `ROLE_USER` (accès en lecture seule aux dashboards) et `ROLE_ADMIN` (accès aux fonctionnalités d'administration et aux configurations avancées).
   - *Tâche technique* : Structurer l'objet utilisateur, ajouter une vérification de rôle sur les actions d'administration (`AdminController`), et masquer/afficher les boutons d'action d'administration dans les templates Twig selon le rôle de l'utilisateur connecté.

3. **Protection des Sessions & Cookies**
   - *Description* : Sécuriser les cookies de session pour empêcher les vols de session et les attaques XSS/CSRF.
   - *Tâche technique* : Configurer les cookies de session avec les attributs `Secure`, `HttpOnly` et `SameSite=Strict`.

## ✅ Definition of Done (DoD)
- Impossible d'accéder à l'application sans être authentifié (redirection automatique vers `/login`).
- Les utilisateurs anonymes ou munis de `ROLE_USER` se voient refuser l'accès aux pages et endpoints d'administration (renvoie un statut HTTP 403 ou redirection).
- Les cookies de session PHP sont configurés avec les flags de sécurité optimaux.
- Les mots de passe stockés en configuration ou base de données sont hashés à l'aide d'un algorithme robuste (bcrypt / argon2id).
