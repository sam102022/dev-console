# Spécification de Conception : Filtrage Rapide sur Clic des Colonnes Domaine et SF

Date : 2026-10-09  
Statut : Validé  
Auteur : Agent IA & Samuel BRIAND  

---

## 1. Contexte & Objectif

Dans l'application Dev Console, les interfaces principales affichent la liste des microservices et projets dans des tableaux paginés et filtrables :
- **GitLab** (`/` ou `/?page=index`)
- **Monitoring** (`/?page=monitoring`)
- **Rundeck** (`/?page=rundeck`)
- **Tags** (`/?page=tags`)

Chacune de ces interfaces possède déjà un sélecteur déroulant de filtre pour le **Domaine** (`#filter_domain`) et pour la **Sous-fonctionnalité** (SF, `#filter_sf`).
Actuellement, pour filtrer sur un domaine ou une SF spécifique visible dans le tableau, l'utilisateur doit faire défiler le menu déroulant d'en-tête et chercher la valeur manuellement.

L'objectif est d'offrir une ergonomie accrue via un **filtrage rapide en 1 clic** directement depuis les cellules du tableau :
- En cliquant sur une valeur de domaine (ex: `pdv`), le filtre `#filter_domain` est automatiquement renseigné avec cette valeur et la grille se rafraîchit.
- En cliquant sur une valeur de sous-fonctionnalité (ex: `stores-stock`), le filtre `#filter_sf` est renseigné avec cette valeur, le filtre `#filter_domain` est synchronisé avec le domaine de la ligne, et la grille se rafraîchit.

---

## 2. Comportement Utilisateur & Règles Métier

1. **Apparence visuelle** :
   - Les valeurs textuelles non vides de domaine et de sous-fonctionnalité s'affichent sous forme de texte interactif avec la classe CSS `.quick-filter-link`.
   - Au survol : curseur de type main (`pointer`), soulignement du texte (`text-decoration: underline`), et teinte bleutée accentuée.
   - Infobulle explicative native (`title`) :
     - Pour Domaine : `"Filtrer par le domaine {domain}"`
     - Pour SF : `"Filtrer par la sous-fonctionnalité {sf}"`
   - Si la valeur est vide ou nulle, aucun lien cliquable n'est généré (affichage standard vide).

2. **Action au clic sur un Domaine** :
   - Mise à jour de `filters.domain = clickedDomain`.
   - Réinitialisation de la pagination à la page 1 (`currentPage = 1`).
   - Persistance dans l'URL (`?domain=...`) et dans le `sessionStorage`.
   - Exécution de `fetchData()` pour recharger les données filtrées. Le serveur retourne également les `allowedSfs` pour mettre à jour la liste des SF disponibles dans le dropdown.

3. **Action au clic sur une SF** :
   - Synchronisation du domaine parent si présent : `filters.domain = rowDomain`.
   - Mise à jour de `filters.sf = clickedSf`.
   - Réinitialisation de la pagination à la page 1 (`currentPage = 1`).
   - Persistance dans l'URL (`?domain=...&sf=...`) et dans le `sessionStorage`.
   - Exécution de `fetchData()` pour recharger les données filtrées.

4. **Comportement en cas de filtre déjà actif** :
   - Application directe stricte (pas de toggle de désélection au clic répété).
   - Pour retirer un filtre appliqué, l'utilisateur utilise le bouton "Réinitialiser les filtres" ou sélectionne "Tous" dans le menu déroulant.

---

## 3. Architecture Technique

### 3.1 JavaScript Alpine.js (`public/js/datagrid.js`)

1. **Méthodes du composant Alpine `datagrid`** :
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
   }
   ```

2. **Délégation d'événements centralisée (Click delegation)** :
   Dans la méthode `init()` de `datagrid`, ajout d'un écouteur d'événements global :
   ```javascript
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

3. **Fonctions globales pour interopérabilité** :
   Pour permettre un appel externe ou des tests unitaires simples :
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

### 3.2 Templates Twig & Styles

1. **Feuille de style commune (`templates/base.html.twig`)** :
   Ajout de la classe utilitaire :
   ```css
   .quick-filter-link {
       cursor: pointer;
       text-decoration: none;
       color: #0d6efd;
       transition: color 0.15s ease-in-out, text-decoration 0.15s ease-in-out;
   }
   .quick-filter-link:hover {
       text-decoration: underline;
       color: #0a58ca;
   }
   ```

2. **Partiels de lignes de tableau (`templates/common/`)** :
   - `_index_rows.html.twig` :
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
   - `_monitoring_rows.html.twig` :
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
   - `_rundeck_rows.html.twig` :
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
   - `_tags_rows.html.twig` :
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

---

## 4. Stratégie de Tests

1. **Tests Unitaires Twig (`tests/templates/QuickFilterTemplateTest.php`)** :
   - Tester le rendu des 4 templates partiels (`_index_rows`, `_monitoring_rows`, `_rundeck_rows`, `_tags_rows`) avec des données de test.
   - Vérifier la présence des balises `.quick-filter-link` avec les bons attributs `data-filter-field`, `data-filter-value` et `data-filter-domain`.
   - Vérifier l'absence d'élément cliquable quand le champ est vide ou absent.

2. **Tests End-to-End Playwright (`tests/e2e/console.spec.ts`)** :
   - Test de clic sur un lien Domaine : vérification de la mise à jour de `#filter_domain`, de l'URL et du filtrage des lignes.
   - Test de clic sur un lien SF : vérification de la synchronisation de `#filter_sf` et `#filter_domain`, de l'URL et du filtrage.

3. **Validation globale** :
   - Exécution de PHPUnit (`vendor/bin/phpunit`).
   - Exécution de PHPStan (`vendor/bin/phpstan analyse`).
