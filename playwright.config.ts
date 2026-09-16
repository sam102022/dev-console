import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false, // Run sequentially to avoid session conflicts in tests
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1, // Single worker to avoid SQLite database locking and session collisions
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    headless: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        baseURL: 'http://127.0.0.1:8000',
      },
    },
  ],
  webServer: {
    command: 'php -d xdebug.mode=off -S 127.0.0.1:8000 -t public',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 15 * 1000, // Increased timeout for slower CI environments
    env: {
      APP_ENV: 'test',
    },
  },
});
