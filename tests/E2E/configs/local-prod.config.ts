import { defineConfig } from '@playwright/test';
import baseConfig from '../playwright.config';

/**
 * Configuration for testing against local production environment (localhost:8082)
 */
export default defineConfig({
  ...baseConfig,
  use: {
    ...baseConfig.use,
    baseURL: 'http://localhost:8082',
  },
});
