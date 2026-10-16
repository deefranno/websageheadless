import type { NextConfig } from 'next';

const isProduction = process.env.NODE_ENV === 'production';

/** WordPress media host; images from anywhere else are refused. */
const mediaHost = process.env.WORDPRESS_MEDIA_HOST?.trim();
if (mediaHost && !/^[a-z0-9.-]+(?::\d+)?$/i.test(mediaHost)) {
  throw new Error(
    'WORDPRESS_MEDIA_HOST must be a bare host name, e.g. cms.example.com',
  );
}

/*
 * Static-compatible baseline CSP (approved in ADR-005). Public pages are
 * cacheable, so scripts cannot carry per-request nonces; Next.js inline
 * bootstrap scripts therefore need 'unsafe-inline'. A nonce policy arrives
 * with preview routes in Phase 3.
 */
const csp = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isProduction ? '' : " 'unsafe-eval'"}`,
  "style-src 'self' 'unsafe-inline'",
  `img-src 'self' data: blob:${mediaHost ? ` https://${mediaHost}` : ''}`,
  "font-src 'self'",
  `connect-src 'self'${isProduction ? '' : ' ws:'}`,
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  ...(isProduction ? ['upgrade-insecure-requests'] : []),
].join('; ');

const securityHeaders = [
  { key: 'Content-Security-Policy', value: csp },
  { key: 'X-Content-Type-Options', value: 'nosniff' },
  { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
  {
    key: 'Permissions-Policy',
    value: 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
  },
  { key: 'X-Frame-Options', value: 'DENY' },
  ...(isProduction
    ? [
        {
          key: 'Strict-Transport-Security',
          value: 'max-age=63072000; includeSubDomains',
        },
      ]
    : []),
];

const nextConfig: NextConfig = {
  output: 'standalone',
  cacheComponents: true,
  partialPrefetching: true,
  poweredByHeader: false,
  transpilePackages: ['@websage/contracts'],
  images: {
    remotePatterns: mediaHost
      ? [
          {
            protocol: 'https',
            hostname: mediaHost.split(':')[0]!,
            pathname: '/wp-content/uploads/**',
          },
        ]
      : [],
  },
  async headers() {
    return [{ source: '/:path*', headers: securityHeaders }];
  },
};

export default nextConfig;
