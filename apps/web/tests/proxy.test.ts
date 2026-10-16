import routes from '@websage/contracts/fixtures/routes.json';
import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const fetchMock = vi.fn<typeof fetch>();

async function freshProxy() {
  vi.resetModules();
  return (await import('@/proxy')).proxy;
}

function rewrittenTo(response: Response): string | null {
  return response.headers.get('x-middleware-rewrite');
}

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
  vi.stubEnv('WSH_FIXTURE_MODE', 'false');
  vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.useRealTimers();
  fetchMock.mockReset();
});

const request = (path: string, cookie?: string) =>
  new NextRequest(
    `https://www.example.com${path}`,
    cookie ? { headers: { cookie } } : undefined,
  );

describe('proxy', () => {
  it('passes known routes and rewrites unknown ones to a real 404', async () => {
    fetchMock.mockImplementation(async () => Response.json(routes));
    const proxy = await freshProxy();

    expect(rewrittenTo(await proxy(request('/')))).toBeNull();
    expect(rewrittenTo(await proxy(request('/blog/hello/')))).toBeNull();
    expect(rewrittenTo(await proxy(request('/missing')))).toBe(
      'https://www.example.com/wsh-404',
    );
  });

  it('fails open when WordPress is unreachable', async () => {
    fetchMock.mockRejectedValue(new TypeError('connect ECONNREFUSED'));
    const proxy = await freshProxy();
    expect(rewrittenTo(await proxy(request('/anything')))).toBeNull();
  });

  it('lets previews through without consulting routes', async () => {
    const proxy = await freshProxy();
    expect(
      rewrittenTo(await proxy(request('/unpublished', 'wsh_preview=abc'))),
    ).toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('refetches on a miss so new pages work immediately', async () => {
    vi.useFakeTimers({ now: 1_000_000 });
    fetchMock.mockImplementation(async () => Response.json(routes));
    const proxy = await freshProxy();
    await proxy(request('/'));

    const added = structuredClone(routes);
    added.data.items.push({
      path: '/new',
      kind: 'page',
      modified: '2026-10-16T12:00:00Z',
      noindex: false,
    });
    fetchMock.mockImplementation(async () => Response.json(added));
    vi.setSystemTime(1_000_000 + 6_000);

    expect(rewrittenTo(await proxy(request('/new')))).toBeNull();
  });
});
