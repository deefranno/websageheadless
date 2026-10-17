import { z } from 'zod';

const HttpsOrigin = z
  .url({ protocol: /^https$/ })
  .transform((value) => new URL(value))
  .refine(
    (url) =>
      url.pathname === '/' &&
      !url.search &&
      !url.hash &&
      !url.username &&
      !url.password,
    'Expected a bare HTTPS origin',
  )
  .transform((url) => url.origin);

const EnvSchema = z.object({
  WP_API_ORIGIN: HttpsOrigin,
  WSH_FIXTURE_MODE: z.enum(['true', 'false']).default('false'),
});

export interface ServerConfig {
  /** WordPress origin; never derived from request data. */
  cmsOrigin: string;
  /** Serve shared contract fixtures instead of calling WordPress. */
  fixtureMode: boolean;
}

/**
 * Server-only secret, or null when unset or shorter than 32 characters.
 * Never use a NEXT_PUBLIC_ prefix for these.
 */
export function getSecret(
  name:
    | 'WSH_SERVER_API_SECRET'
    | 'WSH_PREVIEW_SECRET'
    | 'WSH_REVALIDATION_SECRET'
    | 'WSH_RATE_LIMIT_SECRET',
  env: NodeJS.ProcessEnv = process.env,
): string | null {
  const value = env[name];
  return typeof value === 'string' && value.length >= 32 ? value : null;
}

export class ConfigError extends Error {
  override name = 'ConfigError';
}

/**
 * Reads deployment configuration. Fixture mode is a development aid only and
 * is refused in production so demo content can never mask a missing CMS.
 */
export function getServerConfig(
  env: NodeJS.ProcessEnv = process.env,
): ServerConfig {
  const fixtureMode = env.WSH_FIXTURE_MODE === 'true';
  if (fixtureMode) {
    if (env.NODE_ENV === 'production') {
      throw new ConfigError('WSH_FIXTURE_MODE is not allowed in production.');
    }
    return { cmsOrigin: 'https://fixtures.invalid', fixtureMode: true };
  }

  const parsed = EnvSchema.safeParse(env);
  if (!parsed.success) {
    throw new ConfigError(
      'WP_API_ORIGIN must be set to the HTTPS origin of the WordPress site.',
    );
  }
  return { cmsOrigin: parsed.data.WP_API_ORIGIN, fixtureMode: false };
}
