import { defineConfig } from '@playwright/test';
import { readFileSync } from 'node:fs';

const database = JSON.parse(readFileSync('storage/app/browser-database.json', 'utf8'));
if (!/^airline_test_browser_[a-zA-Z0-9_]+$/.test(database.database)) throw new Error('Unsafe browser database');
export default defineConfig({
    testDir: './tests/Acceptance',
    workers: 1,
    retries: 0,
    reporter: [['list'], ['junit', { outputFile: (process.env.TEST_EVIDENCE_DIR || 'docs/testing/evidence/2026-10-18') + '/browser.xml' }]],
    outputDir: 'storage/app/browser-results',
    use: {
        baseURL: 'http://127.0.0.1:8018',
        channel: process.env.PLAYWRIGHT_CHANNEL || (process.platform === 'win32' ? 'chrome' : undefined),
        headless: true,
        trace: 'off', // Network traces contain session secrets; store sanitized screenshots instead.
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8018',
        url: 'http://127.0.0.1:8018/login',
        reuseExistingServer: false,
        env: { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', APP_URL: 'http://127.0.0.1:8018',
            DB_CONNECTION: 'mysql', DB_HOST: database.host, DB_PORT: String(database.port), DB_DATABASE: database.database,
            DB_USERNAME: database.username, DB_PASSWORD: process.env.TEST_MYSQL_PASSWORD || '', DB_URL: '',
            SESSION_DRIVER: 'database', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync', APP_CONFIG_CACHE: process.cwd() + '/storage/app/no-browser-config.php' },
    },
});
