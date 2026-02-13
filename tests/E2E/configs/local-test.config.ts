import { defineConfig } from '@playwright/test';
import baseConfig from '../playwright.config';

/**
 * Configuration for testing against local test environment (localhost:8081)
 */
export default defineConfig({
  ...baseConfig,
  use: {
    ...baseConfig.use,
    baseURL: 'http://localhost:8081',
  },
});
