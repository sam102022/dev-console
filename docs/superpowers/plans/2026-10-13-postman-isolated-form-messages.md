# Messages d'Alerte Indépendants par Formulaire Postman Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre les alertes (succès, warning, erreur) indépendantes et localisées dans chaque formulaire de l'interface Postman (Workspace, Environnement, OpenAPI) en supprimant le bandeau global.

**Architecture:** Supprimer le conteneur global `#output` de `postman.html.twig` et insérer 3 conteneurs locaux (`#workspaceOutput`, `#envOutput`, `#openapiOutput`) en haut de chaque carte de formulaire. Dans `public/js/postman.js`, refondre `showMessage(targetId, msg, type)` et ajouter `clearMessage(targetId)` pour cibler le conteneur approprié à chaque action.

**Tech Stack:** PHP 8.4, Twig 3, Bootstrap 4, JavaScript ES6+, PHPUnit 11.

---

### Task 1: Test de Template Twig pour les Conteneurs d'Alerte

**Files:**
- Create: `tests/templates/PostmanTemplateTest.php`

- [x] **Step 1: Écrire le test unitaire Twig vérifiant la présence des nouveaux conteneurs et l'absence du conteneur global**

Dans `tests/templates/PostmanTemplateTest.php` :
```php
<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class PostmanTemplateTest extends AbstractTestCase
{
    public function testPostmanTemplateHasIndependentAlertContainersAndNoGlobalOutput(): void
    {
        $html = self::$twig->render('postman.html.twig', [
            'has_postman_api_key' => true,
            'lang' => 'fr',
            'current_route' => 'postman',
        ]);

        // L'ancien conteneur global ne doit plus exister
        $this->assertStringNotContainsString('id="output"', $html);

        // Les 3 conteneurs dédiés doivent être présents avec la classe alert d-none mb-3
        $this->assertStringContainsString('id="workspaceOutput"', $html);
        $this->assertStringContainsString('id="envOutput"', $html);
        $this->assertStringContainsString('id="openapiOutput"', $html);
    }
}
```

- [x] **Step 2: Exécuter le test pour vérifier qu'il échoue (RED)**

Run: `php vendor/bin/phpunit --filter PostmanTemplateTest`  
Expected: FAIL car `id="output"` est encore présent et les nouveaux conteneurs n'existent pas encore.

---

### Task 2: Mise à jour du Template Twig (`templates/postman.html.twig`)

**Files:**
- Modify: `templates/postman.html.twig`
- Test: `tests/templates/PostmanTemplateTest.php`

- [x] **Step 1: Mettre à jour `templates/postman.html.twig`**

1. Supprimer la ligne 37 :
```html
<div id="output" class="alert alert-secondary d-none"></div>
```

2. Dans la carte "Créer un Workspace", insérer au début de `card-body` :
```html
<div id="workspaceOutput" class="alert d-none mb-3"></div>
```

3. Dans la carte "Créer un Environnement", insérer au début de `card-body` :
```html
<div id="envOutput" class="alert d-none mb-3"></div>
```

4. Dans la carte "Importer un fichier OpenAPI", insérer au début de la colonne `<div class="col-md-4">` :
```html
<div id="openapiOutput" class="alert d-none mb-3"></div>
```

- [x] **Step 2: Exécuter le test de template pour vérifier qu'il passe (GREEN)**

Run: `php vendor/bin/phpunit --filter PostmanTemplateTest`  
Expected: PASS (1 test, 4 assertions).

- [x] **Step 3: Commit des modifications du template**

```bash
git add templates/postman.html.twig tests/templates/PostmanTemplateTest.php
git commit -m "feat: ajouter les conteneurs d'alertes independants par formulaire dans postman.html.twig"
```

---

### Task 3: Refactorisation JavaScript des Messages d'Alerte (`public/js/postman.js`)

**Files:**
- Modify: `public/js/postman.js`

- [x] **Step 1: Mettre à jour les fonctions de gestion d'alertes et les appels dans `public/js/postman.js`**

1. Remplacer la constante `const output = ...` et la fonction `showMessage` par :
```javascript
// 📢 Helper pour nettoyer un message dans un conteneur dédié
function clearMessage(targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    el.className = 'alert d-none mb-3';
    el.innerHTML = '';
}

// 📢 Afficher des messages formatés élégamment dans un conteneur dédié
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

2. Mettre à jour `createWorkspace(btn)` :
   - Au début de la fonction : `clearMessage('workspaceOutput');`
   - Validation nom : `showMessage('workspaceOutput', "Le nom du workspace est obligatoire", "warning");`
   - Succès : `showMessage('workspaceOutput', \`Le workspace <strong>\${wsName}</strong> a été créé avec succès (ID: \${wsId}).\`, 'success');`
   - Erreur : `showMessage('workspaceOutput', \`Échec de la création du workspace : \${error.message}\`, 'danger');`

3. Mettre à jour `beforeCreateEnvironment(btn)` :
   - Au début de la fonction : `clearMessage('envOutput');`
   - Validation nom/vars : `showMessage('envOutput', "Nom et variables obligatoires", "warning");`
   - Validation workspace : `showMessage('envOutput', "Workspace obligatoire", "warning");`
   - Succès : `showMessage('envOutput', \`L'environnement <strong>\${name}</strong> a été configuré avec succès !\`, 'success');`
   - Erreur : `showMessage('envOutput', \`Échec de la création de l'environnement : \${error.message}\`, 'danger');`

4. Mettre à jour `importOpenApi(btn)` :
   - Au début de la fonction : `clearMessage('openapiOutput');`
   - Validation contenu : `showMessage('openapiOutput', "Veuillez sélectionner un fichier OpenAPI ou un contrat GitLab", "warning");`
   - Validation workspace : `showMessage('openapiOutput', "Veuillez sélectionner un workspace", "warning");`
   - Succès : `showMessage('openapiOutput', \`Le contrat OpenAPI <strong>\${nameCollection}</strong> a été importé avec succès sous forme de collection !\`, 'success');`
   - Erreur : `showMessage('openapiOutput', \`Échec de l'importation de l'OpenAPI : \${error.message}\`, 'danger');`

- [x] **Step 2: Vérifier la syntaxe du fichier JavaScript**

Run: `node -c public/js/postman.js`  
Expected: Sortie vide, code 0 (aucune erreur de syntaxe).

- [x] **Step 3: Commit des modifications JavaScript**

```bash
git add public/js/postman.js
git commit -m "feat: isoler les alertes par formulaire avec ciblage targetId dans postman.js"
```

---

### Task 4: Validation Globale et Non-Régression

**Files:**
- Verification only

- [x] **Step 1: Exécuter la suite complète de tests PHPUnit**

Run: `php vendor/bin/phpunit`  
Expected: OK (tous les tests passent sans échec).

- [x] **Step 2: Vérifier le statut git**

Run: `git status`  
Expected: Arbre propre (hormis les fichiers non trackés préexistants).
