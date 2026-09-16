import { test, expect } from '@playwright/test';

// ==========================================
// UN-AUTHENTICATED PUBLIC FLOWS
// ==========================================
test.describe('Dev Console Public Flows', () => {

  test('Login Failure with Wrong Credentials', async ({ page }) => {
    await page.goto('/?page=login');
    await page.fill('#email', 'wrong@mdm.com');
    await page.fill('#password', 'bad_pass');
    await page.click('button[type="submit"]');

    const alert = page.locator('.alert-danger');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('E-mail ou mot de passe incorrect.');
  });

  test('Forgot Password Form Success', async ({ page }) => {
    await page.goto('/?page=forgotPassword');
    await page.fill('input[type="email"]', 'some_email@mdm.com');
    await page.click('button[type="submit"]');

    const alert = page.locator('.alert-success');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('un lien de réinitialisation vous a été envoyé');
  });
});

// ==========================================
// AUTHENTICATED ADMINISTRATIVE FLOWS
// ==========================================
test.describe('Dev Console Authenticated Admin Flows', () => {
  
  test.beforeEach(async ({ page }) => {
    // Perform login before each administrative test scenario
    await page.goto('/?page=login');
    await page.fill('#email', 'admin@mdm.com');
    await page.fill('#password', 'admin');
    await page.click('button[type="submit"]');
    
    // Ensure we are redirected to the index page and table is visible
    await expect(page.locator('#projects-table')).toBeVisible({ timeout: 5000 });
  });

  test('User can search and filter the GitLab projects grid', async ({ page }) => {
    // Locate the search input
    const searchInput = page.locator('#filter_project_name');
    await expect(searchInput).toBeVisible();

    // Type a specific project name to filter
    await searchInput.fill('api-accounting-business-partner');

    // Assert that the filtered results contain the matched project and exclude others
    const tableBody = page.locator('#projects-tbody');
    await expect(tableBody).toContainText('api-accounting-business-partner', { timeout: 10000 });
    await expect(tableBody).not.toContainText('contract-async-api');

    // Filter by another project (this clears the previous input first)
    await searchInput.fill('contract-async-api');

    // Assert that the filtered results contain the new project and exclude the old one
    await expect(tableBody).toContainText('contract-async-api', { timeout: 10000 });
    await expect(tableBody).not.toContainText('api-accounting-business-partner');

    // Click the clear button next to the input to reset filter
    const clearButton = page.locator('th:has(#filter_project_name) button[title="Effacer"]');
    await clearButton.click();

    // Verify both projects are visible again (since both belong to page 1 of default view)
    await expect(tableBody).toContainText('api-accounting-business-partner', { timeout: 10000 });
    await expect(tableBody).toContainText('contract-async-api', { timeout: 10000 });
  });

  test('SF selections in drop-down filters persist across page reloads', async ({ page }) => {
    const sfSelect = page.locator('#filter_sf');
    await expect(sfSelect).toBeVisible();

    // Select the "accounting" option
    await sfSelect.selectOption('accounting');

    // Reload the page
    await page.reload();

    // Assert that the SF select box still has "accounting" selected and URL contains sf=accounting
    await expect(sfSelect).toHaveValue('accounting', { timeout: 5000 });
    await expect(page.url()).toContain('sf=accounting');

    // Reset the filter back to "Tous"
    await sfSelect.selectOption('all');
  });

  test('User can trigger health check checks on the Monitoring page', async ({ page }) => {
    // Navigate to the monitoring page
    await page.goto('/?page=monitoring');
    await expect(page.locator('#projects-table')).toBeVisible();

    // Ensure the rows are fully loaded before selecting them
    const firstCheckbox = page.locator('.row-checkbox').first();
    await expect(firstCheckbox).toBeVisible({ timeout: 10000 });

    // Select all projects to check
    const selectAllCheckbox = page.locator('input[title="Tout cocher/décocher"]');
    await selectAllCheckbox.click();

    // Locate the "Vérifier cochés" refresh button and "Arrêter" button
    const checkBtn = page.locator('button[title="Vérifier cochés"]');
    const stopBtn = page.locator('button[title="Arrêter"]');

    await expect(checkBtn).toBeVisible();
    await expect(stopBtn).toHaveClass(/d-none/); // should be hidden initially

    // Click the health check refresh button
    await checkBtn.click();

    // The refresh button should immediately hide (adds d-none), and "Arrêter" should become visible
    await expect(checkBtn).toHaveClass(/d-none/);
    await expect(stopBtn).not.toHaveClass(/d-none/);

    // Stop the check or let it run
    await stopBtn.click();

    // Buttons should return to initial state
    await expect(checkBtn).not.toHaveClass(/d-none/);
    await expect(stopBtn).toHaveClass(/d-none/);
  });

  test('Settings Page API Key update and persistence', async ({ page }) => {
    await page.goto('/?page=settings');
    
    const keyInput = page.locator('#postman_api_key');
    await expect(keyInput).toBeVisible();

    // Type new API Key
    const testKey = 'e2e_postman_api_key_' + Date.now();
    await keyInput.fill(testKey);
    await page.click('button[type="submit"]');

    // Expect success message
    const alert = page.locator('.alert-success');
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('Vos paramètres ont été enregistrés avec succès');

    // Reload page and assert key is preserved
    await page.reload();
    await expect(keyInput).toHaveValue(testKey);

    // Clear key for cleanup
    await keyInput.fill('');
    await page.click('button[type="submit"]');
    await expect(alert).toBeVisible();
    await page.reload();
    await expect(keyInput).toHaveValue('');
  });

  test('User Administration CRUD Operations', async ({ page }) => {
    await page.goto('/?page=users');
    
    const uniqueEmail = 'e2e_user_' + Date.now() + '@mdm.com';

    // 1. CREATE USER
    await page.click('button:has-text("Ajouter un utilisateur")');
    const modal = page.locator('.modal');
    await expect(modal).toBeVisible();

    await modal.locator('input[type="email"]').fill(uniqueEmail);
    await modal.locator('input[type="password"]').fill('password123');
    await modal.locator('select').selectOption('ROLE_USER');
    await modal.locator('button[type="submit"]').click();

    // Wait for modal to close and new user to appear in datagrid
    await expect(modal).not.toBeVisible();
    const tableBody = page.locator('#projects-tbody');
    await expect(tableBody).toContainText(uniqueEmail, { timeout: 10000 });

    // 2. FILTER USER BY EMAIL
    const filterInput = page.locator('#filter_email');
    await filterInput.fill(uniqueEmail);
    await page.waitForTimeout(500); // Wait for debounce and fetch
    
    // Check that we only see the newly created user
    const rows = page.locator('.user-row');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText(uniqueEmail);

    // Reset filter
    const clearFilterBtn = page.locator('th:has(#filter_email) button[title="Effacer"]');
    await clearFilterBtn.click();
    await page.waitForTimeout(500);

    // 3. EDIT USER (Change role to ADMIN)
    const targetRow = page.locator('.user-row', { hasText: uniqueEmail });
    await targetRow.locator('button[title="Modifier"]').click();
    await expect(modal).toBeVisible();
    await modal.locator('select').selectOption('ROLE_ADMIN');
    await modal.locator('button[type="submit"]').click();

    // Wait for modal to close and badge to update
    await expect(modal).not.toBeVisible();
    await expect(targetRow.locator('.badge-danger')).toContainText('Admin');

    // 4. DELETE USER
    // Setup dialog listener to accept the confirm dialog
    page.once('dialog', async dialog => {
      expect(dialog.message()).toContain('supprimer');
      await dialog.accept();
    });

    await targetRow.locator('button[title="Supprimer"]').click();
    await page.waitForTimeout(500); // Wait for fetch

    // Verify user is removed from datagrid
    await expect(tableBody).not.toContainText(uniqueEmail);
  });
});
