import nextVitals from 'eslint-config-next/core-web-vitals';
import nextTypescript from 'eslint-config-next/typescript';

const config = [
  ...nextVitals,
  ...nextTypescript,
  {
    ignores: [
      '.tools/**',
      '.superpowers/**',
      '**/node_modules/**',
      '**/.next/**',
      '**/dist/**',
      '**/vendor/**',
      '**/build/**',
      '.wordpress/**',
    ],
  },
  {
    settings: { next: { rootDir: 'apps/web/' }, react: { version: '19.3' } },
    // App Router only; the Pages Router rule has nothing to check.
    rules: { '@next/next/no-html-link-for-pages': 'off' },
  },
];

export default config;
