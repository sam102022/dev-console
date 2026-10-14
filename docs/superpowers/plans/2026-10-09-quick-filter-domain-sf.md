# Filtrage Rapide sur Clic des Colonnes Domaine et SF - Plan d'Implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre aux utilisateurs de filtrer instantanément par Domaine ou par Sous-fonctionnalité (SF) en cliquant directement sur la valeur affichée dans n'importe quelle ligne des tableaux GitLab, Monitoring, Rundeck et Tags.

**Architecture:** Les valeurs de domaine et de sous-fonctionnalité sont affichées dans les partiels Twig sous la forme de liens textuels interactifs (`.quick-filter-link`) portant des attributs HTML5 (`data-filter-field`, `data-filter-value`, `data-filter-domain`). Dans `public/js/datagrid.js`, une écoute déléguée des clics (`click delegation`) intercepte l'événement, synchronise les filtres du composant Alpine.js (`filterByDomain` et `filterBySf`), et recharge dynamiquement les lignes du tableau via `fetchData()`.

**Tech Stack:** PHP 8.4, Twig 3, Alpine.js, Bootstrap 4, PHPUnit 11, Playwright (TypeScript).

---

## Fichiers Modifiés et Créés

| Fichier | Rôle / Responsabilité |
|---|---|
| `templates/base.html.twig` | Définition de la classe CSS `.quick-filter-link` (style, curseur pointer, hover). |
| `templates/common/_index_rows.html.twig` | Balisage des cellules Domaine et SF avec `.quick-filter-link` pour la vue GitLab. |
| `templates/common/_monitoring_rows.html.twig` | Balisage des cellules Domaine et SF avec `.quick-filter-link` pour la vue Monitoring. |
| `templates/common/_rundeck_rows.html.twig` | Balisage des cellules Domaine et SF avec `.quick-filter-link` pour la vue Rundeck. |
| `templates/common/_tags_rows.html.twig` | Balisage des cellules Domaine et SF avec `.quick-filter-link` pour la vue Tags. |
| `public/js/datagrid.js` | Méthodes Alpine `filterByDomain` / `filterBySf`, délégation d'événement globale et helpers `window`. |
| `tests/templates/QuickFilterTemplateTest.php` | Tests unitaires PHPUnit validant le rendu HTML des balises interactives de filtrage rapide. |
| `tests/e2e/console.spec.ts` | Tests End-to-End Playwright vérifiant le comportement au clic sur Domaine et SF. |

---

### Task 1: Tests Unitaires Twig pour le Rendu des Liens de Filtrage Rapide

**Files:**
- Create: `tests/templates/QuickFilterTemplateTest.php`

- [ ] **Step 1: Écrire le test unitaire pour les 4 templates de lignes**

Créer le fichier `tests/templates/QuickFilterTemplateTest.php` :

```php
<?php
declare(strict_types=1);

namespace App\tests\templates;

use App\tests\AbstractTestCase;

class QuickFilterTemplateTest extends AbstractTestCase
{
    private array $sampleResults = [
        [
            'name' => 'api-orders',
            'domain' => 'pdv',
            'sf' => 'stores-stock',
            'cloudGCP' => true,
            'springBoot' => '3.2.0',
            'java' => '21',
            'mdmWorkloadVersion' => '1.0.0',
            'techno' => 'java',
            'archived' => false,
            'webUrl' => 'https://gitlab.com/api-orders',
            'tags' => ['paiement'],
            'urlActuatorInfo' => ['dev' => ''],
            'urlHealthCheck' => ['dev' => ''],
            'urlLogs' => ['dev' => ''],
            'urlsRundeck' => ['dev' => '']
        ]
    ];

    private array $emptyResults = [
        [
            'name' => 'empty-project',
            'domain' => '',
            'sf' => '',
            'cloudGCP' => false,
            'techno' => '',
            'archived' => false,
            'webUrl' => '',
            'tags' => []
        ]
    ];

    public function testIndexRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_index_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testMonitoringRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_monitoring_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testRundeckRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_rundeck_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }

    public function testTagsRowsRendersQuickFilterLinks(): void
    {
        $html = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $this->sampleResults,
            'offset' => 0
        ]);

        $this->assertStringContainsString('class="quick-filter-link text-primary"', $html);
        $this->assertStringContainsString('data-filter-field="domain"', $html);
        $this->assertStringContainsString('data-filter-value="pdv"', $html);
        $this->assertStringContainsString('data-filter-field="sf"', $html);
        $this->assertStringContainsString('data-filter-value="stores-stock"', $html);
        $this->assertStringContainsString('data-filter-domain="pdv"', $html);

        $emptyHtml = self::$twig->render('common/_tags_rows.html.twig', [
            'results' => $this->emptyResults,
            'offset' => 0
        ]);
        $this->assertStringNotContainsString('data-filter-field="domain"', $emptyHtml);
        $this->assertStringNotContainsString('data-filter-field="sf"', $emptyHtml);
    }
}
```

- [ ] **Step 2: Exécuter le test pour vérifier qu'il échoue**

Run: `vendor\bin\phpunit tests/templates/QuickFilterTemplateTest.php`  
Expected: Échec (FAILURES), les assertions `assertStringContainsString('class="quick-filter-link text-primary"', $html)` échouent car les templates ne contiennent pas encore ces éléments.

---

### Task 2: Mise à Jour des Templates Twig et des Styles CSS

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `templates/common/_index_rows.html.twig`
- Modify: `templates/common/_monitoring_rows.html.twig`
- Modify: `templates/common/_rundeck_rows.html.twig`
- Modify: `templates/common/_tags_rows.html.twig`

- [ ] **Step 1: Ajouter le style CSS `.quick-filter-link` dans `templates/base.html.twig`**

Dans le bloc `<style>` de `templates/base.html.twig`, ajouter les règles CSS :

```css
        .quick-filter-link {
            cursor: pointer;
            text-decoration: none;
            transition: color 0.15s ease-in-out, text-decoration 0.15s ease-in-out;
        }
        .quick-filter-link:hover {
            text-decoration: underline;
            color: #0056b3 !important;
        }
```

- [ ] **Step 2: Modifier `templates/common/_index_rows.html.twig`**

Remplacer les lignes de Domaine et SF :
```twig
        <td class="col-domain" data-value="{{ r.domain }}">
            {% if r.domain is defined and r.domain != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="domain" data-filter-value="{{ r.domain }}" role="button" tabindex="0" title="Filtrer par le domaine {{ r.domain }}">{{ r.domain }}</span>
            {% endif %}
        </td>
        <td class="col-sf" data-value="{{ r.sf }}">
            {% if r.sf is defined and r.sf != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="sf" data-filter-value="{{ r.sf }}" data-filter-domain="{{ r.domain|default('') }}" role="button" tabindex="0" title="Filtrer par la sous-fonctionnalité {{ r.sf }}">{{ r.sf }}</span>
            {% endif %}
        </td>
```

- [ ] **Step 3: Modifier `templates/common/_monitoring_rows.html.twig`**

Remplacer les cellules Domaine et SF :
```twig
        <td class="col-domain" data-column="domain" data-value="{{ r.domain }}">
            {% if r.domain is defined and r.domain != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="domain" data-filter-value="{{ r.domain }}" role="button" tabindex="0" title="Filtrer par le domaine {{ r.domain }}">{{ r.domain }}</span>
            {% endif %}
        </td>
        <td class="col-sf" data-column="sf" data-value="{{ r.sf }}">
            {% if r.sf is defined and r.sf != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="sf" data-filter-value="{{ r.sf }}" data-filter-domain="{{ r.domain|default('') }}" role="button" tabindex="0" title="Filtrer par la sous-fonctionnalité {{ r.sf }}">{{ r.sf }}</span>
            {% endif %}
        </td>
```

- [ ] **Step 4: Modifier `templates/common/_rundeck_rows.html.twig`**

Remplacer les cellules Domaine et SF :
```twig
        <td class="col-domain" data-column="domain" data-value="{{ r.domain }}">
            {% if r.domain is defined and r.domain != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="domain" data-filter-value="{{ r.domain }}" role="button" tabindex="0" title="Filtrer par le domaine {{ r.domain }}">{{ r.domain }}</span>
            {% endif %}
        </td>
        <td class="col-sf" data-column="sf" data-value="{{ r.sf }}">
            {% if r.sf is defined and r.sf != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="sf" data-filter-value="{{ r.sf }}" data-filter-domain="{{ r.domain|default('') }}" role="button" tabindex="0" title="Filtrer par la sous-fonctionnalité {{ r.sf }}">{{ r.sf }}</span>
            {% endif %}
        </td>
```

- [ ] **Step 5: Modifier `templates/common/_tags_rows.html.twig`**

Remplacer les cellules Domaine et SF :
```twig
        <td class="col-domain">
            {% if r.domain is defined and r.domain != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="domain" data-filter-value="{{ r.domain }}" role="button" tabindex="0" title="Filtrer par le domaine {{ r.domain }}">{{ r.domain }}</span>
            {% endif %}
        </td>
        <td class="col-sf">
            {% if r.sf is defined and r.sf != '' %}
                <span class="quick-filter-link text-primary" data-filter-field="sf" data-filter-value="{{ r.sf }}" data-filter-domain="{{ r.domain|default('') }}" role="button" tabindex="0" title="Filtrer par la sous-fonctionnalité {{ r.sf }}">{{ r.sf }}</span>
            {% endif %}
        </td>
```

- [ ] **Step 6: Exécuter les tests unitaires pour vérifier qu'ils passent**

Run: `vendor\bin\phpunit tests/templates/QuickFilterTemplateTest.php`  
Expected: OK (4 tests, 28 assertions).

---

### Task 3: Implémentation du Comportement JavaScript dans `public/js/datagrid.js`

**Files:**
- Modify: `public/js/datagrid.js`

- [ ] **Step 1: Ajouter les méthodes `filterByDomain` et `filterBySf` à l'objet Alpine `datagrid`**

Dans `window.datagrid = (config) => ({ ... })` (vers la méthode `filterByTag` ligne 580) :

```javascript
        filterByDomain(domain) {
            if (!domain) return;
            this.filters.domain = domain;
            this.currentPage = 1;
            this.syncFiltersToUI();
            this.fetchData();
        },

        filterBySf(sf, domain = null) {
            if (!sf) return;
            if (domain) {
                this.filters.domain = domain;
            }
            this.filters.sf = sf;
            this.currentPage = 1;
            this.syncFiltersToUI();
            this.fetchData();
        },
```

- [ ] **Step 2: Ajouter la délégation d'événements pour `.quick-filter-link` dans `init()`**

Dans la méthode `init()` de `datagrid.js` (après le listener sur `.new-relic-link`) :

```javascript
            // Click delegation for quick-filter links on domain and sf
            document.addEventListener('click', (e) => {
                const quickFilterEl = e.target.closest('.quick-filter-link');
                if (quickFilterEl) {
                    e.preventDefault();
                    const field = quickFilterEl.getAttribute('data-filter-field');
                    const val = quickFilterEl.getAttribute('data-filter-value');
                    if (field === 'domain') {
                        this.filterByDomain(val);
                    } else if (field === 'sf') {
                        const domain = quickFilterEl.getAttribute('data-filter-domain');
                        this.filterBySf(val, domain);
                    }
                }
            });
```

- [ ] **Step 3: Ajouter les helpers globaux `window.filterByDomain` et `window.filterBySf`**

Vers la ligne 740 (à côté de `window.filterByTag`) :

```javascript
window.filterByDomain = function(domain) {
    const cardEl = document.getElementById('projects-card') || document.querySelector('[x-data*="datagrid"]');
    if (cardEl && typeof Alpine !== 'undefined') {
        const alpineData = Alpine.$data(cardEl);
        if (alpineData) {
            alpineData.filterByDomain(domain);
        }
    }
};

window.filterBySf = function(sf, domain) {
    const cardEl = document.getElementById('projects-card') || document.querySelector('[x-data*="datagrid"]');
    if (cardEl && typeof Alpine !== 'undefined') {
        const alpineData = Alpine.$data(cardEl);
        if (alpineData) {
            alpineData.filterBySf(sf, domain);
        }
    }
};
```

---

### Task 4: Tests E2E Playwright

**Files:**
- Modify: `tests/e2e/console.spec.ts`

- [ ] **Step 1: Ajouter le scénario de test E2E pour le filtrage rapide Domaine et SF**

Dans `tests/e2e/console.spec.ts`, ajouter les tests suivants :

```typescript
  test('User can quickly filter by domain and sf by clicking on row values', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#projects-table')).toBeVisible();

    // Attendre qu'au moins une ligne soit chargée
    const firstDomainLink = page.locator('.quick-filter-link[data-filter-field="domain"]').first();
    await expect(firstDomainLink).toBeVisible({ timeout: 10000 });

    const domainValue = (await firstDomainLink.getAttribute('data-filter-value')) || '';
    expect(domainValue).not.toBe('');

    // Clic sur le domaine
    await firstDomainLink.click();

    // Vérifier que le sélecteur #filter_domain a pris la valeur
    const domainSelect = page.locator('#filter_domain');
    await expect(domainSelect).toHaveValue(domainValue, { timeout: 5000 });
    await expect(page.url()).toContain(`domain=${domainValue}`);

    // Attendre le rechargement et tester le clic sur une SF
    const firstSfLink = page.locator('.quick-filter-link[data-filter-field="sf"]').first();
    if (await firstSfLink.count() > 0) {
      const sfValue = (await firstSfLink.getAttribute('data-filter-value')) || '';
      const sfDomain = (await firstSfLink.getAttribute('data-filter-domain')) || '';
      
      await firstSfLink.click();

      const sfSelect = page.locator('#filter_sf');
      await expect(sfSelect).toHaveValue(sfValue, { timeout: 5000 });
      await expect(page.url()).toContain(`sf=${sfValue}`);
      if (sfDomain) {
        await expect(domainSelect).toHaveValue(sfDomain);
      }
    }
  });
```

---

### Task 5: Validation Globale et Non-Régression

**Files:**
- None (exécution de commandes de validation)

- [ ] **Step 1: Exécuter la suite complète de tests PHPUnit**

Run: `vendor\bin\phpunit`  
Expected: Tous les tests passent (OK).

- [ ] **Step 2: Exécuter l'analyse statique PHPStan**

Run: `vendor\bin\phpstan analyse`  
Expected: Aucune erreur (No errors).
