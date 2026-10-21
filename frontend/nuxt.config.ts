// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui'
  ],

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    // Server-side (SSR) API base, overridden by NUXT_API_BASE
    apiBase: 'http://localhost:8001/api',
    apiToken: 'Bo0NmQKBkj',

    public: {
      // Browser API base, overridden by NUXT_PUBLIC_API_BASE
      apiBase: 'http://localhost:8000/api',
      apiToken: 'Bo0NmQKBkj',
    }
  },

  routeRules: {
    '/': { prerender: true }
  },

  compatibilityDate: '2026-06-30',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  }
})
