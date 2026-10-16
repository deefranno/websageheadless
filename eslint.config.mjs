import nextVitals from './apps/web/node_modules/eslint-config-next/core-web-vitals.js';
import nextTypescript from './apps/web/node_modules/eslint-config-next/typescript.js';
export default [
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
  { settings: { next: { rootDir: 'apps/web/' } } },
];
