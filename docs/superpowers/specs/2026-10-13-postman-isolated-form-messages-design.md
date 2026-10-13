# Spécification de Conception : Messages d'Alerte Indépendants par Formulaire Postman

**Date :** 2026-10-13  
**Statut :** Validé  

---

## 1. Contexte & Problématique

Sur l'interface de gestion des collections Postman (`?page=postman`), les alertes et messages d'information (succès, avertissement, erreur) sont actuellement tous injectés dans un unique conteneur global situé en haut de la page :
```html
<div id="output" class="alert alert-secondary d-none"></div>
```

Lorsqu'un utilisateur effectue une action sur l'un des formulaires (par exemple la création d'un environnement ou l'import d'une spécification OpenAPI), le message de retour s'affiche tout en haut de la page, loin du contexte immédiat de l'action, et écrase les messages des autres formulaires.

### Objectif
Rendre l'affichage des messages d'alerte (succès, warning, erreur) entièrement indépendant et localisé à l'intérieur de chaque cadre (`card`) de formulaire :
1. **Création de Workspace**
2. **Création d'Environnement**
3. **Import OpenAPI**

---

## 2. Architecture & Composants

### 2.1 Structure HTML (`templates/postman.html.twig`)

1. **Suppression du conteneur global :**
   - Retirer l'élément `#output` situé en haut du corps principal.

2. **Ajout des conteneurs d'alertes dédiés :**
   - **Formulaire Créer un Workspace** :
     Insérer au tout début du `card-body` :
     ```html
     <div id="workspaceOutput" class="alert d-none mb-3"></div>
     ```
   - **Formulaire Créer un Environnement** :
     Insérer au tout début du `card-body` :
     ```html
     <div id="envOutput" class="alert d-none mb-3"></div>
     ```
   - **Formulaire Importer un fichier OpenAPI** :
     Insérer au tout début de la colonne du formulaire (`col-md-4`) dans le `card-body` :
     ```html
     <div id="openapiOutput" class="alert d-none mb-3"></div>
     ```

Chaque conteneur possède par défaut les classes `alert d-none mb-3` pour ne pas occuper d'espace lorsqu'aucun message n'est actif.

### 2.2 Logique JavaScript (`public/js/postman.js`)

#### Refonte des fonctions d'alerte
1. **`clearMessage(targetId)`** :
   Réinitialise le conteneur spécifié en le vidant et en lui réattribuant la classe `alert d-none mb-3`.
   ```javascript
   function clearMessage(targetId) {
       const el = document.getElementById(targetId);
       if (!el) return;
       el.className = 'alert d-none mb-3';
       el.innerHTML = '';
   }
   ```

2. **`showMessage(targetId, msg, type = 'secondary')`** :
   Injecte le message dans l'élément ciblé avec l'icône correspondante et le bouton de fermeture Bootstrap dismissible :
   ```javascript
   function showMessage(targetId, msg, type = 'secondary') {
       const el = document.getElementById(targetId);
       if (!el) return;
       const icon = type === 'success' ? 'fa-check-circle' : (type === 'danger' ? 'fa-exclamation-triangle' : 'fa-info-circle');
       el.className = `alert alert-${type} alert-dismissible fade show mb-3`;
       el.innerHTML = `
           <i class="fas ${icon} mr-2"></i>
           <span>${msg}</span>
           <button type="button" class="close" data-dismiss="alert" aria-label="Close">
               <span aria-hidden="true">&times;</span>
           </button>
       `;
   }
   ```

#### Routage des événements par formulaire
- **`createWorkspace(btn)`** :
  - `clearMessage('workspaceOutput')` dès le début de l'exécution.
  - Validation du champ nom : `showMessage('workspaceOutput', "Le nom du workspace est obligatoire", "warning")`.
  - Succès : `showMessage('workspaceOutput', "Le workspace ... a été créé avec succès...", "success")`.
  - Erreur : `showMessage('workspaceOutput', "Échec de la création du workspace : ...", "danger")`.

- **`beforeCreateEnvironment(btn)`** :
  - `clearMessage('envOutput')` dès le début de l'exécution.
  - Validation nom/variables : `showMessage('envOutput', "Nom et variables obligatoires", "warning")`.
  - Validation workspace : `showMessage('envOutput', "Workspace obligatoire", "warning")`.
  - Succès : `showMessage('envOutput', "L'environnement ... a été configuré avec succès !", "success")`.
  - Erreur : `showMessage('envOutput', "Échec de la création de l'environnement : ...", "danger")`.

- **`importOpenApi(btn)`** :
  - `clearMessage('openapiOutput')` dès le début de l'exécution.
  - Validation contenu OpenAPI : `showMessage('openapiOutput', "Veuillez sélectionner un fichier OpenAPI ou un contrat GitLab", "warning")`.
  - Validation workspace : `showMessage('openapiOutput', "Veuillez sélectionner un workspace", "warning")`.
  - Succès : `showMessage('openapiOutput', "Le contrat OpenAPI ... a été importé avec succès sous forme de collection !", "success")`.
  - Erreur : `showMessage('openapiOutput', "Échec de l'importation de l'OpenAPI : ...", "danger")`.

---

## 3. Gestion des Erreurs et Robustesse

- Si un identifiant `targetId` inexistant est fourni à `showMessage` ou `clearMessage`, la fonction retourne immédiatement sans déclencher d'exception JavaScript (`if (!el) return;`).
- Chaque formulaire dispose de son propre état de message : soumettre ou générer une erreur sur "Créer un Environnement" ne supprime ni n'affecte un message d'information ou de succès affiché sur "Créer un Workspace".
- Le bouton croix (`.close`) propre à chaque alerte permet à l'utilisateur de fermer manuellement le message de la carte concernée.

---

## 4. Stratégie de Tests et Validation

1. **Test unitaire de Template Twig (`tests/templates/PostmanTemplateTest.php`)** :
   - Tester le rendu de `postman.html.twig`.
   - Vérifier la présence des conteneurs `#workspaceOutput`, `#envOutput` et `#openapiOutput`.
   - Vérifier l'absence du conteneur global `#output`.
   - Vérifier les classes par défaut `alert d-none mb-3`.
2. **Validation syntaxique JavaScript** :
   - Exécuter `node -c public/js/postman.js` pour valider l'absence d'erreurs de syntaxe.
3. **Non-régression globale** :
   - Exécution de l'ensemble de la suite PHPUnit (`php vendor/bin/phpunit`).
