import { createHmac } from 'node:crypto';

/**
 * Client identification for rate limiting.
 *
 * The trusted client IP comes only from a header the deployment names
 * explicitly (WSH_CLIENT_IP_HEADER), because any other request header can be
 * forged. For X-Forwarded-For, WSH_TRUSTED_PROXY_HOPS says how many proxies
 * you run; the address they appended is used. Unconfigured production
 * deployments fail closed instead of trusting a spoofable value.
 */
export class ProxyTrustError extends Error {
  override name = 'ProxyTrustError';
}

export function clientIp(
  headers: Headers,
  env: NodeJS.ProcessEnv = process.env,
): string {
  const header = env.WSH_CLIENT_IP_HEADER?.trim().toLowerCase();
  if (!header) {
    if (env.NODE_ENV === 'production') {
      throw new ProxyTrustError(
        'Set WSH_CLIENT_IP_HEADER to the header your proxy uses for the client IP.',
      );
    }
    return '127.0.0.1';
  }

  const raw = headers.get(header);
  if (!raw) {
    throw new ProxyTrustError(`Trusted header ${header} is missing.`);
  }
  if (header !== 'x-forwarded-for') return raw.trim();

  const hops = Math.max(1, Number(env.WSH_TRUSTED_PROXY_HOPS ?? '1') || 1);
  const chain = raw
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean);
  const ip = chain[chain.length - hops];
  if (!ip)
    throw new ProxyTrustError(
      'X-Forwarded-For is shorter than the configured hops.',
    );
  return ip;
}

/** HMAC of IP and UTC date: WordPress never sees the raw address. */
export function clientHash(
  ip: string,
  secret: string,
  now = new Date(),
): string {
  const day = now.toISOString().slice(0, 10);
  return createHmac('sha256', secret).update(`${ip}|${day}`).digest('hex');
}
