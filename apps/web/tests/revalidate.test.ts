import event from '@websage/contracts/fixtures/revalidation-event.json';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { signWebhook, verifyWebhook } from '@/lib/revalidate/verify';

const cache = vi.hoisted(() => ({ revalidateTag: vi.fn() }));
vi.mock('next/cache', () => cache);
const { POST } = await import('@/app/api/revalidate/route');

const SECRET = 'r'.repeat(40);
const NOW = 1_800_000_000;
const ID = '86c1e718-ef94-4ca8-8e28-043850eedfa9';
const body = JSON.stringify(event);

interface Overrides {
  body?: string;
  timestamp?: string | null;
  eventId?: string | null;
  signature?: string | null;
}

function input(overrides: Overrides = {}) {
  const base = {
    body,
    timestamp: String(NOW),
    eventId: ID,
    signature: signWebhook(SECRET, NOW, ID, body),
  };
  return { ...base, ...overrides };
}

/** Changes the first hex digit, keeping the signature well-formed. */
const flip = (hex: string) => (hex[0] === 'a' ? 'b' : 'a') + hex.slice(1);

describe('verifyWebhook', () => {
  it('accepts a correctly signed, fresh event', () => {
    expect(verifyWebhook(input(), SECRET, NOW)).toEqual({ ok: true, event });
  });

  it.each([
    ['mutated body', { body: body.replace('about', 'admin') }],
    ['mutated timestamp', { timestamp: String(NOW + 1) }],
    ['mutated event id', { eventId: ID.replace('8', '9') }],
    [
      'mutated signature',
      { signature: flip(signWebhook(SECRET, NOW, ID, body)) },
    ],
    ['wrong secret', { signature: signWebhook('x'.repeat(40), NOW, ID, body) }],
    ['missing signature', { signature: null }],
  ])('rejects %s with 401', (_name, overrides) => {
    expect(verifyWebhook(input(overrides), SECRET, NOW)).toMatchObject({
      ok: false,
      status: 401,
    });
  });

  it('rejects timestamps outside ±300 s', () => {
    const old = NOW - 301;
    expect(
      verifyWebhook(
        input({
          timestamp: String(old),
          signature: signWebhook(SECRET, old, ID, body),
        }),
        SECRET,
        NOW,
      ),
    ).toMatchObject({ ok: false, status: 401 });
  });

  it('rejects oversized bodies with 413 and bad events with 400', () => {
    const big = 'x'.repeat(65_537);
    expect(verifyWebhook(input({ body: big }), SECRET, NOW)).toMatchObject({
      status: 413,
    });

    const bad = JSON.stringify({ ...event, tags: ['page:../admin'] });
    expect(
      verifyWebhook(
        input({ body: bad, signature: signWebhook(SECRET, NOW, ID, bad) }),
        SECRET,
        NOW,
      ),
    ).toMatchObject({ ok: false, status: 400 });
  });
});

describe('POST /api/revalidate', () => {
  beforeEach(() => vi.stubEnv('WSH_REVALIDATION_SECRET', SECRET));
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.useRealTimers();
    cache.revalidateTag.mockReset();
  });

  function request(headers: Record<string, string>, payload = body) {
    return new Request('https://www.example.com/api/revalidate', {
      method: 'POST',
      body: payload,
      headers,
    });
  }

  it('expires exactly the event tags, immediately', async () => {
    vi.useFakeTimers({ now: NOW * 1000 });
    const response = await POST(
      request({
        'x-wsh-timestamp': String(NOW),
        'x-wsh-event-id': ID,
        'x-wsh-signature': signWebhook(SECRET, NOW, ID, body),
      }),
    );

    expect(response.status).toBe(200);
    expect(await response.json()).toEqual({ data: { accepted: true } });
    expect(response.headers.get('cache-control')).toBe('no-store');
    expect(cache.revalidateTag.mock.calls).toEqual(
      event.tags.map((tag) => [tag, { expire: 0 }]),
    );
  });

  it('rejects unsigned requests without touching the cache', async () => {
    const response = await POST(request({}));
    expect(response.status).toBe(401);
    expect(cache.revalidateTag).not.toHaveBeenCalled();
  });

  it('is unavailable without a configured secret', async () => {
    vi.stubEnv('WSH_REVALIDATION_SECRET', 'short');
    expect((await POST(request({}))).status).toBe(503);
  });
});
