# ADR 004 : Isolation de la racine Web (Document Root)

## Statut
Accepté

## Contexte
Afin de sécuriser les données internes, les fichiers de configuration et le cache local (ex. `data/javaProjects.json`), l'application ne doit pas exposer l'ensemble de son code source au public par accès HTTP direct. 

## Décision
Le point d'entrée du serveur web (`DocumentRoot`) DOIT être configuré pour pointer explicitement sur le dossier `public/` du projet.

## Configuration requise (Exemple Apache / XAMPP)

Dans le fichier `httpd-vhosts.conf` de votre serveur, ajoutez ou modifiez le Virtual Host pour le projet de cette façon :

```apache
<VirtualHost *:80>
    ServerName dev-console.local
    DocumentRoot "C:/xamp/xampp_lite_8_5/www/dev-console/public"
    
    <Directory "C:/xamp/xampp_lite_8_5/www/dev-console/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Conséquences

### Positives
- **Sécurité accrue :** Impossible d'accéder au fichier `javaProjects.json` ou au fichier `.env` via un navigateur, car la racine du serveur web est isolée au sous-dossier `public/`.
- **Propreté de l'exposition :** Seuls les assets publics (`css`, `js`, `images`) et le point d'entrée unique `index.php` sont exposés directement.

### Négatives
- **Configuration requise :** Nécessite une configuration manuelle de l'administrateur système ou du développeur sur l'environnement de serveurs (Virtual Host). En cas d'impossibilité, un fichier de repli `.htaccess` à la racine doit être utilisé.
