# Sprint 8 : Authentification & Sécurité d'Accès (RBAC) 🔐

## 🎯 Objectif
Sécuriser l'accès au tableau de bord "Dev Console" en mettant en place une authentification des utilisateurs et un contrôle d'accès basé sur les rôles (RBAC).

## 📝 Contexte
L'application expose des informations sensibles sur l'infrastructure interne (domaines, variables d'environnements, configurations de builds) mais ne possède pas de mécanisme d'authentification utilisateur global. 
Actuellement, n'importe qui ayant accès au réseau interne peut consulter ou modifier des données d'administration (`AdminContext`).

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

4. **Interface de Gestion des Utilisateurs (CRUD) 👥**
   - *Description* : Fournir une interface d'administration réservée aux administrateurs pour créer, lister, modifier et supprimer des comptes utilisateurs.
   - *Tâche technique* : Créer une table `users` en base SQLite (avec colonnes `id`, `email`, `password_hash`, `role`, `created_at`, `reset_token`, `reset_token_expires_at`). Implémenter les vues Twig de gestion, un contrôleur `UserAdminController` protégé par un contrôle de rôle `ROLE_ADMIN`, et des formulaires de modification de rôles/mots de passe.

5. **Flux de Mot de Passe Perdu (Lost Password) 📧**
   - *Description* : Permettre à un utilisateur d'initier une réinitialisation de mot de passe de manière autonome via l'envoi d'un token à usage unique par email.
   - *Tâche technique* : Ajouter un lien "Mot de passe oublié ?" sur l'écran de login. Créer un endpoint qui génère un jeton (token) cryptographique sécurisé d'une durée de validité limitée (ex: 1 heure), l'enregistre en base et simule l'envoi d'un email contenant un lien unique `/reset-password?token=XYZ`. Créer la vue et l'action de réinitialisation finale de mot de passe.

6. **Interface de Paramètres Utilisateur & Clé Postman Personnelle ⚙️**
   - *Description* : Permettre à chaque utilisateur connecté (sans distinction de rôle) de configurer ses informations personnelles et sa propre clé d'API Postman dans son profil.
   - *Tâche technique* : Créer une page de paramètres `?page=settings`, gérée par un `SettingsController` et une vue Twig `settings.html.twig`. Ajouter le champ `postman_api_key` dans la table `users` et adapter le service d'injection de dépendances (`ServiceFactory`) pour que le client Postman utilise dynamiquement la clé de l'utilisateur connecté s'il y en a une, sinon se replier sur la clé globale `.env`.

## ✅ Definition of Done (DoD)
- Impossible d'accéder à l'application sans être authentifié (redirection automatique vers `/login`).
- Les utilisateurs anonymes ou munis de `ROLE_USER` se voient refuser l'accès aux pages d'administration (comme le CRUD utilisateur) et aux endpoints correspondants (renvoie un statut HTTP 403 ou redirection).
- Les cookies de session PHP sont configurés avec les flags de sécurité optimaux.
- Les mots de passe sont hashés en base SQLite à l'aide d'un algorithme robuste (`password_hash` standard de PHP avec `PASSWORD_BCRYPT` ou `PASSWORD_ARGON2ID`).
- Seuls les utilisateurs connectés possédant le rôle `ROLE_ADMIN` peuvent accéder aux actions de création/modification/suppression d'utilisateurs.
- Le flux de mot de passe perdu utilise des jetons sécurisés à durée de validité limitée, détruits immédiatement après utilisation.
- Chaque utilisateur connecté (Admin ou User) peut configurer et masquer sa propre clé d'API Postman de manière isolée dans l'interface des paramètres.
- Le client Postman résout dynamiquement la clé de l'utilisateur connecté de manière sécurisée en session, avec un repli transparent sur la clé globale d'environnement si elle est vide.
