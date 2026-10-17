import input from '@websage/contracts/fixtures/contact-input.json';
import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clientHash, clientIp, ProxyTrustError } from '@/lib/contact/client-ip';

const submit = vi.hoisted(() => ({ submitToWordPress: vi.fn() }));
vi.mock('@/lib/contact/submit', () => submit);
const { POST } = await import('@/app/api/contact/route');

const ORIGIN = 'https://www.example.com';

function request(body: unknown, headers: Record<string, string> = {}) {
  return new NextRequest(`${ORIGIN}/api/contact`, {
    method: 'POST',
    body: typeof body === 'string' ? body : JSON.stringify(body),
    headers: { origin: ORIGIN, 'x-forwarded-for': '203.0.113.9', ...headers },
  });
}

beforeEach(() => {
  vi.stubEnv('WSH_RATE_LIMIT_SECRET', 'k'.repeat(40));
  vi.stubEnv('WSH_CLIENT_IP_HEADER', 'x-forwarded-for');
  submit.submitToWordPress.mockResolvedValue({ status: 'accepted' });
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.clearAllMocks();
});

describe('POST /api/contact', () => {
  it('forwards valid submissions with a keyed client hash, never the raw IP', async () => {
    const response = await POST(request(input));

    expect(response.status).toBe(200);
    expect(await response.json()).toEqual({ data: { accepted: true } });
    const [sent, hash] = submit.submitToWordPress.mock.calls[0]!;
    expect(sent).toEqual(input);
    expect(hash).toBe(clientHash('203.0.113.9', 'k'.repeat(40)));
    expect(hash).not.toContain('203.0.113.9');
  });

  it('refuses cross-site posts', async () => {
    expect(
      (await POST(request(input, { origin: 'https://evil.example' }))).status,
    ).toBe(403);
    expect(submit.submitToWordPress).not.toHaveBeenCalled();
  });

  it('returns field codes for invalid input without contacting WordPress', async () => {
    const response = await POST(
      request({ ...input, email: 'nope', consent: false }),
    );
    expect(response.status).toBe(422);
    expect((await response.json()).error.fields).toEqual({
      email: 'invalid',
      consent: 'required',
    });
    expect(submit.submitToWordPress).not.toHaveBeenCalled();
  });

  it('rejects oversized and malformed bodies', async () => {
    expect(
      (await POST(request({ ...input, message: 'x'.repeat(17_000) }))).status,
    ).toBe(413);
    expect((await POST(request('{nope'))).status).toBe(400);
  });

  it('maps WordPress outcomes', async () => {
    submit.submitToWordPress.mockResolvedValueOnce({
      status: 'rate_limited',
      retryAfter: 120,
    });
    const limited = await POST(request(input));
    expect(limited.status).toBe(429);
    expect(limited.headers.get('retry-after')).toBe('120');

    submit.submitToWordPress.mockResolvedValueOnce({ status: 'unavailable' });
    expect((await POST(request(input))).status).toBe(503);
    submit.submitToWordPress.mockResolvedValueOnce({ status: 'conflict' });
    expect((await POST(request(input))).status).toBe(409);
  });

  it('fails closed when proxy trust is not configured in production', async () => {
    vi.stubEnv('WSH_CLIENT_IP_HEADER', '');
    vi.stubEnv('NODE_ENV', 'production');
    expect((await POST(request(input))).status).toBe(503);
    expect(submit.submitToWordPress).not.toHaveBeenCalled();
  });
});

describe('clientIp', () => {
  const headers = (xff: string) =>
    new Headers({ 'x-forwarded-for': xff, 'x-real-ip': '198.51.100.7' });

  it('takes the address appended by the trusted proxies', () => {
    const env = {
      WSH_CLIENT_IP_HEADER: 'x-forwarded-for',
      NODE_ENV: 'production',
    } as NodeJS.ProcessEnv;
    expect(clientIp(headers('1.1.1.1, 203.0.113.9'), env)).toBe('203.0.113.9');
    expect(
      clientIp(headers('1.1.1.1, 203.0.113.9, 10.0.0.2'), {
        ...env,
        WSH_TRUSTED_PROXY_HOPS: '2',
      }),
    ).toBe('203.0.113.9');
    expect(
      clientIp(headers('x'), { ...env, WSH_CLIENT_IP_HEADER: 'X-Real-IP' }),
    ).toBe('198.51.100.7');
  });

  it('refuses spoofable or missing configuration', () => {
    expect(() =>
      clientIp(headers('1.1.1.1'), {
        NODE_ENV: 'production',
      } as NodeJS.ProcessEnv),
    ).toThrow(ProxyTrustError);
    expect(() =>
      clientIp(new Headers(), {
        WSH_CLIENT_IP_HEADER: 'cf-connecting-ip',
        NODE_ENV: 'production',
      } as NodeJS.ProcessEnv),
    ).toThrow(ProxyTrustError);
  });

  it('changes the hash daily', () => {
    const a = clientHash('203.0.113.9', 's', new Date('2026-10-16T23:59:59Z'));
    const b = clientHash('203.0.113.9', 's', new Date('2026-10-17T00:00:01Z'));
    expect(a).not.toBe(b);
  });
});
