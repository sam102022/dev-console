# Sprint 4 : Sécurité 🔒

## 🎯 Objectif
Sécuriser les données internes de l'application en empêchant tout accès HTTP direct non autorisé aux fichiers de configuration et au cache local.

## 📝 Contexte
L'application stocke les résultats de scan de GitLab (pouvant contenir des noms de domaines sensibles, des noms d'infrastructure et l'architecture des sous-projets) dans des fichiers locaux au format JSON (`data/javaProjects.json`). Actuellement, si le serveur web est configuré pour pointer sur la racine de l'application, un attaquant pourrait lire ces fichiers directement en saisissant `http://domaine.com/data/javaProjects.json`. La même vulnérabilité s'applique au dossier `src/`, `config/` ou au `.env`.

## 🛠️ Tâches (User Stories)

1. **Isolation de la racine Web (Document Root)**
   - *Description* : Le serveur web ne devrait exposer au public que le dossier `public/`.
   - *Tâche technique* : Revoir la configuration du Virtual Host (Apache/Nginx/XAMPP) pour s'assurer que le point d'entrée (`DocumentRoot`) est bien `C:\xamp\xampp_lite_8_5\www\dev-console\public`.

2. **Restriction d'accès par répertoire (.htaccess / config serveur)**
   - *Description* : Mettre en place des règles strictes de refus d'accès pour les dossiers sensibles (au cas où la racine web n'aurait pas pu être isolée).
   - *Tâche technique* : Créer ou mettre à jour un fichier `.htaccess` à la racine interdisant `Deny from all` pour l'accès direct aux dossiers `data`, `src`, `vendor`, `.git` et aux fichiers `.env`.

## ✅ Definition of Done (DoD)
- Impossible d'accéder au fichier `javaProjects.json` via un navigateur (Renvoie une erreur 403 Forbidden ou 404 Not Found).
- Seuls les assets (`css`, `js`, `images`) situés dans `public/` sont téléchargeables publiquement.